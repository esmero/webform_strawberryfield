<?php

namespace Drupal\webform_strawberryfield;

use Drupal\Core\File\Event\FileUploadSanitizeNameEvent;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\File\FileExists;
use Drupal\webform_strawberryfield\Event\WebformStrawberryFieldTusUploadedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use TusPhp\Cache\AbstractCache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Utility\Token;
use Drupal\file\FileUsage\FileUsageInterface;
use TusPhp\Events\TusEvent;
use TusPhp\Events\UploadComplete;
use TusPhp\Exception\FileException;
use TusPhp\Tus\Server;
use Drupal\file\Entity\File;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class TusServer.
 */
class WebformStrawberryTusServerService {


  /**
   * Instance of Token.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Instance of FileUsage.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface
   */
  protected $fileUsage;

  /**
   * Instance of EntityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Instance of FileSystem.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Tus settings config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Tus cache directory URI.
   *
   * @var string
   */
  protected $tusCacheDir;

  /**
   * Instance of AccountProxy.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Instance of EventDispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a new TusServer object.
   */
  public function __construct(Token $token, FileUsageInterface $file_usage, EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system, ConfigFactoryInterface $config_factory, AccountProxyInterface $current_user, EventDispatcherInterface $event_dispatcher, private readonly AbstractCache $tusCacheService) {
    $this->token = $token;
    $this->fileUsage = $file_usage;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->config = $config_factory->get('webform_strawberryfield');
    $this->tusCacheDir = $this->config->get('tus_upload_path') ?? 'private://webform_strawberryfield/tus/';
    $this->currentUser = $current_user;
    $this->eventDispatcher = $event_dispatcher;
    if (!$this->fileSystem->prepareDirectory($this->tusCacheDir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      throw new HttpException(500, sprintf('TUS Upload Path folder "%s" is not writable.', $this->tusCacheDir));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getServer($route_path, string $upload_key = '', array $post_data = []): Server {
    $server = $this->getTusServer($route_path, $upload_key);
    return $server;
  }

  /**
   * Get a configured instance of Tus Server.
   *
   * @param string $upload_key
   *   Upload key from tus if available.
   * @param string $path
   *   The actual path we are serving from. Reason is our paths are variable
   *   Based on the Webform and the upload Key.
   *
   * @return \TusPhp\Tus\Server
   *   Configured instance of Tus server
   *
   * @throws \ReflectionException
   */
  protected function getTusServer(string $route_path, string $upload_key = ''): Server {

    // Initialize TUS server using our shared temp store cache wrapper.
    $server = new Server($this->tusCacheService);
    $server->setApiPath($route_path);

    if ($upload_key) {
      $server->setUploadKey($upload_key);
    }

    $server->event()->addListener(UploadComplete::NAME, [
      $this,
      'uploadComplete'
    ]);

    return $server;
  }

  /**
   * {@inheritdoc}
   */
  public function uploadComplete(TusEvent $event): void {
    $tus_file = $event->getFile();
    $file_name = $tus_file->getName() ?? 'unknown file';
    $error = FALSE;
    // To overcome the File URI upload length limit imposed by Drupal
    // WE will have to move the file out of our path
    // which is scheme://webform/{{webform_id}}/_sid_/{{user}}/tus/{{webform_element_key}}/{uuid}/
    // Drupal's File Paths are limited to 255 characters. This is bananas.
    $file_path = $tus_file->getFilePath();
    $clean_file_path = static::sanitizeURI($file_path);
    if ($clean_file_path != $file_path) {
      try {
        $file_path = $this->fileSystem->move($file_path, $clean_file_path, FileExists::Replace);
      }
      catch (\Exception $e) {
        throw new FileException($e->getMessage());
      }
    }

		$uuid = $tus_file->getKey();
    $metadata = $tus_file->details()['metadata'];
    $token = $event->getRequest()->getRequest()->headers->get('x-csrf-token');
    $valid_extensions =  $event->getRequest()->getRequest()->headers->get('x-tus-extensions');
    if (!$valid_extensions) {
      throw new FileException("Invalid Extensions passed");
    }
    $event_filename = new FileUploadSanitizeNameEvent($file_name, $valid_extensions);
    $this->eventDispatcher->dispatch($event_filename);
    $file_name = $event_filename->getFilename();

    // Check if the file already exists.
    $file_query = $this->entityTypeManager->getStorage('file')->getQuery();
    $file_query->condition('uri', $file_path);
    $file_query->accessCheck(TRUE);
    $results = $file_query->execute();

    if (!empty($results)) {
      return;
    }
    try {
      /** @var \Drupal\file\FileInterface $file */
      $file = File::create([
        'uid' => $this->currentUser->id(),
        'filename' => $file_name,
        'uri' => $file_path,
        'filemime' => mime_content_type($file_path),
        'filesize' => filesize($file_path),
      ]);
      $file->save();
    }
    catch (\Exception $e) {
      // We could not persist the File. Some data might be wrong, DB locked up, etc.
      $error = $e->getMessage();
    }
    // Clear the temporary cache
    $client = new \TusPhp\Tus\Client('/webform_strawberry/tus_upload_complete');
    if ($client) {
      $client->setKey($uuid);
      // Effectively purges the progress Cache, if any lingering.
      $client->getCache()->delete($uuid);
    }
    else {
      throw new FileException("TUS Client is gone. Contact your server admin.");
    }
    if ($error) {
      throw new FileException($error);
    }

    // Dispatch an event for other modules to act on.
    $event = new WebformStrawberryFieldTusUploadedEvent($file);
    $this->eventDispatcher->dispatch($event);
  }


  public static function sanitizeURI($file_path) {
    if (strlen($file_path) > 254) {
      $new_path_parts = pathinfo($file_path);
      $new_dir_path = $new_path_parts['dirname'];
      $new_dir_path = explode("//", $new_path_parts['dirname'], 2);
      $schema = $new_dir_path[0] ?? "temporary:";

      $new_dir_path = explode("/", $new_dir_path[1] ?? '');
      $new_dir_path = array_slice($new_dir_path, 0, 4);
      $new_dir_path = implode("/", $new_dir_path);
      $new_dir_path = $schema . '//' . $new_dir_path . "/";
      $new_file_name = $new_path_parts['basename'] ?? 'unknown';
      $file_path = $new_dir_path . $new_file_name;
      if (strlen($file_path) > 254) {
        // max length first
        // Be super conservative. I could allow 254 but if i have to rename this might be messy.
        $max_length = 250 - strlen($new_dir_path);
        $new_file_name = substr($new_file_name, $max_length * -1);
        $file_path = $new_dir_path . $new_file_name;
      }
    }
    return $file_path;
  }
}
