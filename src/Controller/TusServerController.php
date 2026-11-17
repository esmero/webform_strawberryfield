<?php


namespace Drupal\webform_strawberryfield\Controller;


use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\webform\Plugin\WebformElement\WebformManagedFileBase;
use Drupal\webform\WebformInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Drupal\webform_strawberryfield\WebformStrawberryTusServerService;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\webform\Plugin\WebformElementManagerInterface;
use Drupal\webform\Entity\Webform;

/**
 * Class TusServerController.
 */
class TusServerController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The injected WebformStrawberryTusServerService.
   *
   * @var \Drupal\webform_strawberryfield\WebformStrawberryTusServerService
   */
  protected $tusServerService;


  /**
   * The webform element manager.
   *
   * @var \Drupal\webform\Plugin\WebformElementManagerInterface
   */
  protected $webformElementManager;

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The available serialization formats.
   *
   * @var array
   */
  protected $serializerFormats = [];

  /**
   * Constructs a new TusController object.
   */
  public function __construct(WebformStrawberryTusServerService $tus_server, Serializer $serializer, array $serializer_formats, WebformElementManagerInterface $webformManager, private readonly FileSystemInterface $fileSystem) {
    $this->tusServerService = $tus_server;
    $this->serializer = $serializer;
    $this->serializerFormats = $serializer_formats;
    $this->webformElementManager = $webformManager;
  }


  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    if ($container->hasParameter('serializer.formats') && $container->has('serializer')) {
      $serializer = $container->get('serializer');
      $formats = $container->getParameter('serializer.formats');
    }
    else {
      $formats = ['json'];
      $encoders = [new JsonEncoder()];
      $serializer = new Serializer([], $encoders);
    }

    return new static(
      $container->get( 'webform_strawberryfield.tus.server'),
      $serializer,
      $formats,
      $container->get('plugin.manager.webform.element'),
      $container->get('file_system')
    );
  }

  /**
   * Gets the format of the current request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return string
   *   The format of the request.
   */
  protected function getRequestFormat(Request $request): ?string {
    $format = $request->getRequestFormat();
    if (!in_array($format, $this->serializerFormats)) {
      throw new BadRequestHttpException(sprintf('Unrecognized format: %s.', $format));
    }
    return $format;
  }

  /**
   * Upload a file via TUS protocol.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param string $uuid
   *   UUID for the file being uploaded.
   *
   * @return \TusPhp\Tus\Server
   *   Tus server ready to receive file upload.
   */
  public function uploadToWebform(Request $request, WebformInterface $webform, string $key, string $uuid) {
    $meta_values = $this->getMetaValuesFromRequest($request);
    $current_path = $request->getPathInfo();

    // UUID is passed on PATCH and other certain calls, or as the
    // header upload-key on others.
    $uuid ??= $request->headers->get('upload-key') ?? '';
    $server = $this->tusServerService->getServer($current_path, $uuid, $meta_values);

    if (strtolower($server->getRequest()->method()) === 'post') {
      // Post is called on creation.
      $uuid = $server->getUploadKey();
    }


    // Each webform might have a different upload destination/key based on their settings
    // We can't upload to S3 though, so in this case all will go to private?
    // See \Drupal\webform\Plugin\WebformElement\WebformManagedFileBase::getUploadLocation
    [$destination, $max] = $this->getUploadLocation($webform, $key, $uuid);

    if ($destination) {
      $server->setUploadDir($destination);
      // Get the file destination. WE NEE TO MOVE THIS TO THE CONTROLLER.
      // THAT IS WHERE WE HAVE INFO ABOUT THE WEBFORM/KEY.
      $server->setMaxUploadSize((int) $max * 1048576);
      return $server->serve();
    }
    else {
      throw new UnprocessableEntityHttpException(sprintf('Webform element %s can not hold your file', $key));
    }
  }


  protected function getUploadLocation(Webform $webform_entity, string $key, string $uuid):array {
    $upload_location = NULL;
    $max_size = 100; // Mbytes... this will be set by the Element.
    /* @var \Drupal\webform\Entity\Webform $webform_entity */
    $element = $webform_entity->getElementInitialized($key);
    if ($element) {
      $element_plugin = $this->webformElementManager->getElementInstance($element);
      if ($element_plugin->getPluginId() == 'webform_tus_file') {
        $upload_location = 'private://webform/' . $webform_entity->id() . '/_sid_/' . $this->currentUser()
            ->getAccountName() . '/tus/' . $key .'/'. $uuid;
        //  If we use a custom element we can set this directly $upload_location = $element['#upload_location'];
        $this->fileSystem->prepareDirectory($upload_location, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        $max_size = $element['#max_filesize_tus'] ?? $max_size;
      }
    }
    return [$upload_location, $max_size];
  }


  /**
   * Get an array of meta values from.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The original request.
   *
   * @return array
   *   An array of metadata values passed in with the request.
   */
  protected function getMetaValuesFromRequest(Request $request): array {
    $result = [];
    if ($metadata = $request->headers->get('upload-metadata')) {
      $metadata_array = explode(',', $metadata);
      foreach ($metadata_array as $piece) {
        if (is_string($piece)) {
          $metadata_pieces =  explode(' ', $piece);
          if (isset($metadata_pieces[0]) && isset($metadata_pieces[1]) && is_string($metadata_pieces[1])) {
            $result[$metadata_pieces[0]] = base64_decode($metadata_pieces[1]);
          }
        }
      }
    }

    return $result;
  }

  /**
   * Get the file ID of the uploaded file.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The created file details.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function uploadToWebformComplete(Request $request, WebformInterface $webform, string $key): Response {
    $response = [];
    $post_data = $this->serializer->decode($request->getContent(), $this->getRequestFormat($request));
    if (($post_data['fileName'] ?? NULL) && ($request->get('uuid') ?? NULL)) {
      // Not sure what to do with the $request->get('uuid') yet?
      // Maybe use it If the first query call fails?
      [$upload_location, $max_size] = $this->getUploadLocation($webform, $key, $request->get('uuid'));
      // The file URI might exceed the Drupal limits and might have been modified by The Upload Event subscriber
      // So we do we will use the same op
      $file_path =  $upload_location . '/' . $post_data['fileName'];
      $clean_file_path = \Drupal\webform_strawberryfield\WebformStrawberryTusServerService::sanitizeURI($file_path);
      $file_storage = $this->entityTypeManager()->getStorage('file');
      /* @var $existing \Drupal\file\Entity\File[] */
      $existing = $file_storage->loadByProperties(["uri" => $clean_file_path]);

      if ($existing) {
        $file = reset($existing);
      }
      else {
        throw new NotFoundHttpException("We could not find your File on the backend. Please try again or contact your Admin, you might be running out of space.");
      }

      $response['fid'] = $file->id();
      $jsonResponse = new CacheableJsonResponse();
      $jsonResponse->setMaxAge(10);
      $jsonResponse->setData($response);
      // And we delete here
      $client = new \TusPhp\Tus\Client($request->getPathInfo());
      $client->setKey($request->get('uuid'));
      // Effectively purges the progress Cache, if any lingering.
      $client->getCache()->delete($request->get('uuid'));
      return $jsonResponse;
    }
    else {
      throw new NotAcceptableHttpException("Missing arguments. Filename and UUID.");
    }
  }
}

