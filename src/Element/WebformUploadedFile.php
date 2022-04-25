<?php

namespace Drupal\webform_strawberryfield\Element;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\Render\Element;
use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\file\Entity\File;
use Drupal\webform\Element\WebformManagedFileBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a webform element for an 'document_file' element.
 *
 * @FormElement("webform_uploaded_file")
 */
class WebformUploadedFile extends WebformManagedFileBase {

  public function getInfo() {
    $info = parent::getInfo();
    //$info['#pre_render'][] = [get_class($this), 'preRenderWebformManagedFile'];
    $info['#process'][] = [get_class($this), 'processUploaded'];
    $info['#process'][] = [get_class($this), 'processGroup'];
    $info['#pre_render'][] = [get_class($this), 'preRenderGroup'];
    return $info;
    /*
     * return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'processManagedFile'],
      ],
      '#element_validate' => [
        [$class, 'validateManagedFile'],
      ],
      '#pre_render' => [
        [$class, 'preRenderManagedFile'],
      ],
      '#theme' => 'file_managed_file',
      '#theme_wrappers' => ['form_element'],
      '#progress_indicator' => 'throbber',
      '#progress_message' => NULL,
      '#upload_validators' => [],
      '#upload_location' => NULL,
      '#size' => 22,
      '#multiple' => FALSE,
      '#extended' => FALSE,
      '#attached' => [
        'library' => ['file/drupal.file'],
      ],
      '#accept' => NULL,
    ];
     */
  }
  public static function processUploaded(&$element, FormStateInterface $form_state, &$complete_form) {
    $element['#tree'] = TRUE;
    $parents_prefix = implode('_', $element['#parents']);
    $element['path'] = [
      '#type' => 'textfield',
      '#name' => $element['#name'].'[path]',
      '#title' => t('Path of file to connect'),
      '#description' => t('please use a path that starts with a valid stream wrapper, e.g s3://myprefix/myfile.txt .'),
      '#title_display' => 'before',
      '#default_value' => NULL,//$element['#default_value']['path'],
      '#attributes' => $element['#attributes'],
      '#required' => $element['#required'],
      '#size' => 12,
      '#error_no_message' => TRUE,
    ];
    $element['checksum'] = [
      '#type' => 'textfield',
      '#name' => $element['#name'] .'[checksum]',
      '#title' => t('Checksum'),
      '#title_display' => 'before',
      '#description' => t('please provide the MD5 checksum of the file you want to connect.'),
      '#default_value' => NULL, //$element['#default_value']['checksum'],
      '#attributes' => $element['#attributes'],
      '#required' => $element['#required'],
      '#size' => 12,
      '#error_no_message' => TRUE,
    ];
    $ajax_settings = [
      'callback' => [static::class, 'uploadAjaxCallback'],
      'options' => [
        'query' => [
          'element_parents' => implode('/', $element['#array_parents']),
        ],
      ],
      'wrapper' => $element['upload_button']['#ajax']['wrapper'],
      'effect' => 'fade',
      'progress' => [
        'type' => $element['#progress_indicator'],
        'message' => $element['#progress_message'],
      ],
    ];

    // Set up the buttons first since we need to check if they were clicked.
    $element['connect_button'] = [
      '#name' => $parents_prefix . '_connect_button',
      '#type' => 'submit',
      '#value' => t('Connect'),
      //'#attributes' => ['class' => ['js-hide']],
      '#validate' => [],
      '#submit' => ['file_managed_file_submit'],
      '#limit_validation_errors' => [$element['#parents']],
      '#ajax' => $ajax_settings,
      '#weight' => -5,
    ];

    return $element;
  }

  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {$return = parent::valueCallback($element, $input,
      $form_state);

    if (($input !== FALSE) && !empty($input['path']) && !empty($input['checksum'])) {
      $result = WebformUploadedFile::connect_file($element, trim($input['path']), trim($input['checksum']), $form_state);
      if (!empty($result)) {
        $form_state_input = $form_state->getUserInput();
        NestedArray::setValue($form_state_input,
          array_merge($element['#parents'], ['path']), NULL);
        NestedArray::setValue($form_state_input,
          array_merge($element['#parents'], ['checksum']), NULL);
        $form_state->setUserInput($form_state_input);
      }
    }

    return $return;
    // This will deal with the $return['fids'] hidden element but we want to also deal with the exposed
    // path and checksum

   /* public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
      // Find the current value of this field.
      $fids = !empty($input['fids']) ? explode(' ', $input['fids']) : [];
      foreach ($fids as $key => $fid) {
        $fids[$key] = (int) $fid;
      }
      $force_default = FALSE;

      // Process any input and save new uploads.
      if ($input !== FALSE) {
        $input['fids'] = $fids;
        $return = $input;

        // Uploads take priority over all other values.
        if ($files = file_managed_file_save_upload($element, $form_state)) {
          if ($element['#multiple']) {
            $fids = array_merge($fids, array_keys($files));
          }
          else {
            $fids = array_keys($files);
          }
        }
        else {
          // Check for #filefield_value_callback values.
          // Because FAPI does not allow multiple #value_callback values like it
          // does for #element_validate and #process, this fills the missing
          // functionality to allow File fields to be extended through FAPI.
          if (isset($element['#file_value_callbacks'])) {
            foreach ($element['#file_value_callbacks'] as $callback) {
              $callback($element, $input, $form_state);
            }
          }

          // Load files if the FIDs have changed to confirm they exist.
          if (!empty($input['fids'])) {
            $fids = [];
            foreach ($input['fids'] as $fid) {
              if ($file = File::load($fid)) {
                $fids[] = $file->id();
                if (!$file->access('download')) {
                  $force_default = TRUE;
                  break;
                }
                // Temporary files that belong to other users should never be
                // allowed.
                if ($file->isTemporary()) {
                  if ($file->getOwnerId() != \Drupal::currentUser()->id()) {
                    $force_default = TRUE;
                    break;
                  }
                  // Since file ownership can't be determined for anonymous users,
                  // they are not allowed to reuse temporary files at all. But
                  // they do need to be able to reuse their own files from earlier
                  // submissions of the same form, so to allow that, check for the
                  // token added by $this->processManagedFile().
                  elseif (\Drupal::currentUser()->isAnonymous()) {
                    $token = NestedArray::getValue($form_state->getUserInput(), array_merge($element['#parents'], ['file_' . $file->id(), 'fid_token']));
                    $file_hmac = Crypt::hmacBase64('file-' . $file->id(), \Drupal::service('private_key')->get() . Settings::getHashSalt());
                    if ($token === NULL || !hash_equals($file_hmac, $token)) {
                      $force_default = TRUE;
                      break;
                    }
                  }
                }
              }
            }
            if ($force_default) {
              $fids = [];
            }
          }
        }
      }

      // If there is no input or if the default value was requested above, use the
      // default value.
      if ($input === FALSE || $force_default) {
        if ($element['#extended']) {
          $default_fids = isset($element['#default_value']['fids']) ? $element['#default_value']['fids'] : [];
          $return = isset($element['#default_value']) ? $element['#default_value'] : ['fids' => []];
        }
        else {
          $default_fids = isset($element['#default_value']) ? $element['#default_value'] : [];
          $return = ['fids' => []];
        }

        // Confirm that the file exists when used as a default value.
        if (!empty($default_fids)) {
          $fids = [];
          foreach ($default_fids as $fid) {
            if ($file = File::load($fid)) {
              $fids[] = $file->id();
            }
          }
        }
      }

      $return['fids'] = $fids;
      return $return;
    }*/

  }
  /**
   * #ajax callback for uploaded_file upload forms.
   *
   * This ajax callback takes care of the following things:
   *   - Ensures that broken requests due to too big files are caught.
   *   - Adds a class to the response to be able to highlight in the UI, that a
   *     new file got uploaded.
   *
   * @param array $form
   *   The build form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response of the ajax upload.
   */
  public static function connectAjaxCallback(&$form, FormStateInterface &$form_state, Request $request) {
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');

    $form_parents = explode('/', $request->query->get('element_parents'));

    // Sanitize form parents before using them.
    $form_parents = array_filter($form_parents, [Element::class, 'child']);

    // Retrieve the element to be rendered.
    $form = NestedArray::getValue($form, $form_parents);

    // Add the special AJAX class if a new file was added.
    $current_file_count = $form_state->get('file_upload_delta_initial');
    if (isset($form['#file_upload_delta']) && $current_file_count < $form['#file_upload_delta']) {
      $form[$current_file_count]['#attributes']['class'][] = 'ajax-new-content';
    }
    // Otherwise just add the new content class on a placeholder.
    else {
      $form['#suffix'] .= '<span class="ajax-new-content"></span>';
    }

    $status_messages = ['#type' => 'status_messages'];
    $form['#prefix'] .= $renderer->renderRoot($status_messages);
    $output = $renderer->renderRoot($form);

    $response = new AjaxResponse();
    $response->setAttachments($form['#attached']);

    return $response->addCommand(new ReplaceCommand(NULL, $output));
  }


  /**
   * @param array $element
   * @param string $path
   * @param string $checksum
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array|\Drupal\Core\Entity\EntityInterface|\Drupal\file\FileInterface|false|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function connect_file(array $element, string $uri, string $checksum, FormStateInterface $form_state) {
    $upload_name = implode('_', $element['#parents']);
    $parts = explode('://',$uri);
    if (count($parts) == 2) {
        $protocol = $parts[0];
        $key = $parts[1];
    }
    else {
      // WRONG
    }
    // Soft dependency
    if (!empty(\Drupal::hasService('s3fs'))) {

      $s3fsConfig = \Drupal::service('config.factory')->get('s3fs.settings');
      foreach ($s3fsConfig->get() as $prop => $value) {
        $config[$prop] = $value;
      }
      /* @var $s3fs \Drupal\s3fs\S3fsServiceInterface */
      $s3fs = \Drupal::Service('s3fs');
      try {
        $client = $s3fs->getAmazonS3Client($config);

        $args = ['Bucket' => $config['bucket']];

        if (is_subclass_of(\Drupal::service('stream_wrapper_manager')
            ->getClass('private'),
            'Drupal\s3fs\StreamWrapper\S3fsStream') && $protocol == 'private') {
          $key = $config['private_folder'] . '/' . $key;
        }
        elseif (is_subclass_of(\Drupal::service('stream_wrapper_manager')
            ->getClass('public'),
            'Drupal\s3fs\StreamWrapper\S3fsStream') && $protocol == 'public') {
          $key = $config['public_folder'] . '/' . $key;
        }
        if (!empty($config['root_folder'])) {
            $key = $config['root_folder'] . '/' . $key;
        }

        if (mb_strlen(rtrim($key,
              '/')) > 255) {
            return FALSE;}

        $args['Key'] = $key;

        $response = $client->headObject($args);
        $data = $response->toArray();
        if (trim($data['ETag'], '"') === $checksum) {
          error_log('yay!');
        }
      }
      catch (\Aws\S3\Exception\S3Exception $exception) {
        error_log($exception->getCode());
        error_log($exception->getMessage());
        return FALSE;
      }
    }
    $scheme = \Drupal::service('stream_wrapper_manager')->getScheme($uri);
    if (!isset(\Drupal::service('stream_wrapper_manager')->getWrappers(
        StreamWrapperInterface::LOCAL)[$scheme]
    )) {
      if (is_readable(
        \Drupal::service('file_system')->realpath(
          basename($uri)
        )
      )) {
      }
    }

    return FALSE;

    try {
      $file = \Drupal::entityTypeManager()->getStorage('file')->create(
        [
          'uri' => $uri,
          'uid' => $this->currentUser->id(),
          'status' => FILE_STATUS_PERMANENT,
        ]
      );
      // If we are replacing an existing file re-use its database record.
      // @todo Do not create a new entity in order to update it. See
      //   https://www.drupal.org/node/2241865.
      // Check if File with same URI already exists.
      $existing_files = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->loadByProperties(['uri' => $uri]);
      if (count($existing_files)) {
        $existing = reset($existing_files);
        $file->fid = $existing->id();
        $file->setOriginalId($existing->id());
        $file->setFilename($existing->getFilename());
      }

      $file->save();
      return $file;
    }
    catch (FileException $e) {
      return FALSE;
    }

    if (empty($all_files[$upload_name])) {
      return FALSE;
    }
    $file_upload = $all_files[$upload_name];

    // Save attached files to the database.
    $files_uploaded = $element['#multiple'] && count(array_filter($file_upload)) > 0;
    $files_uploaded |= !$element['#multiple'] && !empty($file_upload);
    if ($files_uploaded) {



      if (!$files = _file_save_upload_from_form($element, $form_state)) {
        \Drupal::logger('file')->notice('The file upload failed. %upload', ['%upload' => $upload_name]);
        return [];
      }

      // Value callback expects FIDs to be keys.
      $files = array_filter($files);
      $fids = array_map(function ($file) {
        return $file->id();
      }, $files);

      return empty($files) ? [] : array_combine($fids, $files);
    }

    return [];
  }

  protected function file_connect_single(\SplFileInfo $file_info, $form_field_name, $validators = [], $destination = FALSE, $replace = FileSystemInterface::EXISTS_REPLACE) {
    $user = \Drupal::currentUser();
    // Remember the original filename so we can print a message if it changes.
    $original_file_name = $file_info->getClientOriginalName();
    // Check for file upload errors and return FALSE for this file if a lower
    // level system error occurred. For a complete list of errors:
    // See http://php.net/manual/features.file-upload.errors.php.
    switch ($file_info->getError()) {
      case UPLOAD_ERR_INI_SIZE:
      case UPLOAD_ERR_FORM_SIZE:
        \Drupal::messenger()->addError(t('The file %file could not be saved because it exceeds %maxsize, the maximum allowed size for uploads.', ['%file' => $original_file_name, '%maxsize' => format_size(Environment::getUploadMaxSize())]));
        return FALSE;

      case UPLOAD_ERR_PARTIAL:
      case UPLOAD_ERR_NO_FILE:
        \Drupal::messenger()->addError(t('The file %file could not be saved because the upload did not complete.', ['%file' => $original_file_name]));
        return FALSE;

      case UPLOAD_ERR_OK:
        // Final check that this is a valid upload, if it isn't, use the
        // default error handler.
        if (is_uploaded_file($file_info->getRealPath())) {
          break;
        }

      default:
        // Unknown error
        \Drupal::messenger()->addError(t('The file %file could not be saved. An unknown error has occurred.', ['%file' => $original_file_name]));
        return FALSE;

    }

    // Build a list of allowed extensions.
    $extensions = '';
    if (isset($validators['file_validate_extensions'])) {
      if (isset($validators['file_validate_extensions'][0])) {
        // Build the list of non-munged extensions if the caller provided them.
        $extensions = $validators['file_validate_extensions'][0];
      }
      else {
        // If 'file_validate_extensions' is set and the list is empty then the
        // caller wants to allow any extension. In this case we have to remove the
        // validator or else it will reject all extensions.
        unset($validators['file_validate_extensions']);
      }
    }
    else {
      // No validator was provided, so add one using the default list.
      // Build a default non-munged safe list for
      // \Drupal\system\EventSubscriber\SecurityFileUploadEventSubscriber::sanitizeName().
      $extensions = 'jpg jpeg gif png txt doc xls pdf ppt pps odt ods odp';
      $validators['file_validate_extensions'] = [];
      $validators['file_validate_extensions'][0] = $extensions;
    }

    // If the destination is not provided, use the temporary directory.
    if (empty($destination)) {
      $destination = 'temporary://';
    }

    /** @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager */
    $stream_wrapper_manager = \Drupal::service('stream_wrapper_manager');

    // Assert that the destination contains a valid stream.
    $destination_scheme = $stream_wrapper_manager::getScheme($destination);
    if (!$stream_wrapper_manager->isValidScheme($destination_scheme)) {
      \Drupal::messenger()->addError(t('The file could not be uploaded because the destination %destination is invalid.', ['%destination' => $destination]));
      return FALSE;
    }

    // A file URI may already have a trailing slash or look like "public://".
    if (substr($destination, -1) != '/') {
      $destination .= '/';
    }

    // Call an event to sanitize the filename and to attempt to address security
    // issues caused by common server setups.
    $event = new FileUploadSanitizeNameEvent($original_file_name, $extensions);
    \Drupal::service('event_dispatcher')->dispatch($event);

    // Begin building the file entity.
    $values = [
      'uid' => $user->id(),
      'status' => 0,
      // This will be replaced later with a filename based on the destination.
      'filename' => $event->getFilename(),
      'uri' => $file_info->getRealPath(),
      'filesize' => $file_info->getSize(),
    ];
    /* @var File $file */
    $file = File::create($values);

    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    try {
      // Use the result of the sanitization event as the destination name.
      $file->destination = $file_system->getDestinationFilename($destination . $event->getFilename(), $replace);
    }
    catch (FileException $e) {
      \Drupal::messenger()->addError(t('The file %filename could not be uploaded because the name is invalid.', ['%filename' => $file->getFilename()]));
      return FALSE;
    }

    $guesser = \Drupal::service('file.mime_type.guesser');
    if ($guesser instanceof MimeTypeGuesserInterface) {
      $file->setMimeType($guesser->guessMimeType($values['filename']));
    }
    else {
      $file->setMimeType($guesser->guess($values['filename']));
      @trigger_error('\Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface is deprecated in drupal:9.1.0 and is removed from drupal:10.0.0. Implement \Symfony\Component\Mime\MimeTypeGuesserInterface instead. See https://www.drupal.org/node/3133341', E_USER_DEPRECATED);
    }
    $file->source = $form_field_name;

    // If the destination is FALSE then $replace === FILE_EXISTS_ERROR and
    // there's an existing file, so we need to bail.
    if ($file->destination === FALSE) {
      \Drupal::messenger()->addError(t('The file %source could not be uploaded because a file by that name already exists in the destination %directory.', ['%source' => $form_field_name, '%directory' => $destination]));
      return FALSE;
    }

    // Add in our check of the file name length.
    $validators['file_validate_name_length'] = [];

    // Call the validation functions specified by this function's caller.
    $errors = file_validate($file, $validators);

    // Check for errors.
    if (!empty($errors)) {
      $message = [
        'error' => [
          '#markup' => t('The specified file %name could not be uploaded.', ['%name' => $file->getFilename()]),
        ],
        'item_list' => [
          '#theme' => 'item_list',
          '#items' => $errors,
        ],
      ];
      // @todo Add support for render arrays in
      // \Drupal\Core\Messenger\MessengerInterface::addMessage()?
      // @see https://www.drupal.org/node/2505497.
      \Drupal::messenger()->addError(\Drupal::service('renderer')->renderPlain($message));
      return FALSE;
    }

    $file->setFileUri($file->destination);
    if (!$file_system->moveUploadedFile($file_info->getRealPath(), $file->getFileUri())) {
      \Drupal::messenger()->addError(t('File upload error. Could not move uploaded file.'));
      \Drupal::logger('file')->notice('Upload error. Could not move uploaded file %file to destination %destination.', ['%file' => $file->getFilename(), '%destination' => $file->getFileUri()]);
      return FALSE;
    }

    // Update the filename with any changes as a result of the renaming due to an
    // existing file.
    $file->setFilename(\Drupal::service('file_system')->basename($file->destination));

    // If the filename has been modified, let the user know.
    if ($file->getFilename() !== $original_file_name) {
      if ($event->isSecurityRename()) {
        $message = t('For security reasons, your upload has been renamed to %filename.', ['%filename' => $file->getFilename()]);
      }
      else {
        $message = t('Your upload has been renamed to %filename.', ['%filename' => $file->getFilename()]);
      }
      \Drupal::messenger()->addStatus($message);
    }

    // Set the permissions on the new file.
    $file_system->chmod($file->getFileUri());

    // If we are replacing an existing file re-use its database record.
    // @todo Do not create a new entity in order to update it. See
    //   https://www.drupal.org/node/2241865.
    if ($replace == FileSystemInterface::EXISTS_REPLACE) {
      $existing_files = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['uri' => $file->getFileUri()]);
      if (count($existing_files)) {
        $existing = reset($existing_files);
        $file->fid = $existing->id();
        $file->setOriginalId($existing->id());
      }
    }

    // Update the filename with any changes as a result of security or renaming
    // due to an existing file.
    $file->setFilename(\Drupal::service('file_system')->basename($file->destination));

    // We can now validate the file object itself before it's saved.
    $violations = $file->validate();
    foreach ($violations as $violation) {
      $errors[] = $violation->getMessage();
    }
    if (!empty($errors)) {
      $message = [
        'error' => [
          '#markup' => t('The specified file %name could not be uploaded.', ['%name' => $file->getFilename()]),
        ],
        'item_list' => [
          '#theme' => 'item_list',
          '#items' => $errors,
        ],
      ];
      // @todo Add support for render arrays in
      // \Drupal\Core\Messenger\MessengerInterface::addMessage()?
      // @see https://www.drupal.org/node/2505497.
      \Drupal::messenger()->addError(\Drupal::service('renderer')->renderPlain($message));
      return FALSE;
    }

    // If we made it this far it's safe to record this file in the database.
    $file->save();

    // Allow an anonymous user who creates a non-public file to see it. See
    // \Drupal\file\FileAccessControlHandler::checkAccess().
    if ($user->isAnonymous() && $destination_scheme !== 'public') {
      $session = \Drupal::request()->getSession();
      $allowed_temp_files = $session->get('anonymous_allowed_file_ids', []);
      $allowed_temp_files[$file->id()] = $file->id();
      $session->set('anonymous_allowed_file_ids', $allowed_temp_files);
    }
    return $file;
  }



}
