<?php

namespace Drupal\webform_strawberryfield\Plugin\WebformElement;

use Drupal\Component\Utility\Bytes;
use Drupal\Component\Utility\Environment;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Plugin\WebformElement\WebformManagedFileBase;
use Drupal\Core\Url;

/**
 * Provides a 'webform_tus_file' element.
 *
 * @WebformElement(
 *   id = "webform_tus_file",
 *   label = @Translation("TuS (resumable) file"),
 *   description = @Translation("Provides a form element for uploading and saving an file."),
 *   category = @Translation("File upload elements"),
 *   states_wrapper = TRUE,
 *   dependencies = {
 *     "file",
 *   }
 * )
 */

class WebformTusFile extends WebformManagedFileBase {

  /**
   * {@inheritdoc}
   */
  public function getItemFormats() {
    $formats = parent::getItemFormats();
    return $formats;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineDefaultProperties() {
    $properties = parent::defineDefaultProperties();
    $properties['max_filesize_tus'] = '';
    $properties['chunksize'] = 0;
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(array &$element, WebformSubmissionInterface $webform_submission = NULL) {
    //parent::prepare($element, $webform_submission);
    // Track if this element has been processed because the work-around below
    // for 'Issue #2705471: Webform states File fields' which nests the
    // 'managed_file' element in a basic container, which triggers this element
    // to be processed a second time.
    if (!empty($element['#webform_managed_file_processed'])) {
      return;
    }
    $element['#webform_managed_file_processed'] = TRUE;

    // Must come after #element_validate hook is defined.
    parent::prepare($element, $webform_submission);

    // Check if the URI scheme exists and can be used the upload location.
    $scheme_options = static::getVisibleStreamWrappers();
    $uri_scheme = $this->getUriScheme($element);
    if (!isset($scheme_options[$uri_scheme])) {
      $element['#access'] = FALSE;
      $this->displayDisabledWarning($element);
    }
    elseif ($webform_submission) {
      $element['#upload_location'] = $this->getUploadLocation($element, $webform_submission->getWebform());
    }

    // Get file limit.
    if ($webform_submission) {
      $file_limit = $webform_submission->getWebform()->getSetting('form_file_limit')
        ?: $this->configFactory->get('webform.settings')->get('settings.default_form_file_limit')
          ?: '';
    }
    else {
      $file_limit = '';
    }

    // Validate callbacks.
    $element_validate = [];
    // Convert File entities into file ids (akk fids).
    $element_validate[] = [get_class($this), 'validateManagedFile'];
    // Check file upload limit.
    if ($file_limit) {
      $element_validate[] = [get_class($this), 'validateManagedFileLimit'];
    }
    // NOTE: Using array_splice() to make sure that static::validateManagedFile
    // is executed before all other validation hooks are executed but after
    // \Drupal\file\Element\ManagedFile::validateManagedFile.
    $element['#element_validate'] = $element['#element_validate'] ?? [];
    array_splice($element['#element_validate'], 1, 0, $element_validate);


    // The class used to bind the TUS JS code.
    $element['#attributes']['class'][] = 'webform_strawberryfield_tus';
    // Upload validators.
    // @see webform_preprocess_file_upload_help
    // Deprecated in Drupal 10.2+
    //$element['#upload_validators']['file_validate_size'] = [$this->getMaxFileSizeTus($element)];
    $element['#upload_validators']['FileSizeLimit'] =  ['fileLimit' => $this->getMaxFileSizeTus($element)];
    $element['#upload_validators']['FileExtension'] = ['extensions' => $this->getFileExtensions($element)];
    // Define 'webform_file_validate_extensions' which allows file
    // extensions within webforms to be comma-delimited. The
    // 'webform_file_validate_extensions' will be ignored by file_validate().
    // @see file_validate()
    // Issue #3136578: Comma-separate the list of allowed file extensions.
    // @see https://www.drupal.org/project/drupal/issues/3136578
    $element['#upload_validators']['webform_file_validate_extensions'] = [];
    $element['#upload_validators']['webform_file_validate_name_length'] = [];

    // Add file upload help to the element as #description, #help, or #more.
    // Copy upload validator so that we can add webform's file limit to
    // file upload help only.
    $upload_validators = $element['#upload_validators'];
    if ($file_limit) {
      $upload_validators['webform_file_limit'] = Bytes::toNumber($file_limit);
    }
    $file_upload_help = [
      '#theme' => 'file_upload_help',
      '#upload_validators' => $upload_validators,
      '#cardinality' => (empty($element['#multiple'])) ? 1 : $element['#multiple'],
    ];
    $file_help = $element['#file_help'] ?? 'description';
    if ($file_help !== 'none') {
      if (isset($element["#$file_help"])) {
        if (is_array($element["#$file_help"])) {
          $file_help_content = $element["#$file_help"];
        }
        else {
          $file_help_content = ['#markup' => $element["#$file_help"]];
        }
        $file_help_content += ['#suffix' => '<br/>'];
        $element["#$file_help"] = ['content' => $file_help_content];
      }
      else {
        $element["#$file_help"] = [];
      }
      $element["#$file_help"]['file_upload_help'] = $file_upload_help;
    }

    // Issue #2705471: Webform states File fields.
    // Workaround: Wrap the 'managed_file' element in a basic container.
    if (!empty($element['#prefix'])) {
      $container = [
        '#prefix' => $element['#prefix'],
        '#suffix' => $element['#suffix'],
      ];
      unset($element['#prefix'], $element['#suffix']);
      $container[$element['#webform_key']] = $element + ['#webform_managed_file_processed' => TRUE];
      $element = $container;
    }
    // Add the TUS basic interface
    // We make it initially hidden, that way, when the cap/limit is reached
    // we don't need to initialize the TUS JS client code at all. It stays hidden
    // How do we know? process function will remove the input[type=file]
    // and thus our JS will not kick on the once.
    $element['status'] = [
      '#markup' => '
			  <div class="tus-status hidden visually-hidden">
          <div class="progress progress-striped progress-success tus-progress">
            <div class="bar tus-bar" style="width:0%"></div>
          </div>
          <span class="btn button stop tus-btn w-auto">Chose a file to start an upload</span>
        </div>
      <div class="tus-upload-list"></div>',
      '#weight' => 10,
    ];

    // Allow ManagedFile Ajax callback to disable flexbox wrapper.
    // @see \Drupal\file\Element\ManagedFile::uploadAjaxCallback
    // @see \Drupal\webform\Plugin\WebformElementBase::preRenderFixFlexboxWrapper
    $request_params = \Drupal::request()->request->all();
    if (\Drupal::request()->request->get('_drupal_ajax')
      && (!empty($request_params['files']) || !empty($request_params[$element['#webform_key']]))) {
      $element['#webform_wrapper'] = FALSE;
    }

    // Add process callback.
    // Set element's #process callback so that is not replaced by
    // additional #process callbacks.
    $this->setElementDefaultCallback($element, 'process');
    $element['#process'][] = [get_class($this), 'processManagedFile'];
    // Adds TUS JS.
    $element['#attached']['library'][] = 'webform_strawberryfield/webform_strawberryfield.tus_integration';
    // PASS the CSFR Token
    // @TODO. This element should skip anonymous users at all. We should default to the standard upload
    // Element.
    $url = Url::fromRoute('webform_strawberryfield.tus.upload', ['webform' => $element['#webform'],'key' => $element['#webform_key']],  ['absolute' => TRUE]);
    $token = \Drupal::csrfToken()->get(\Drupal\Core\Access\CsrfRequestHeaderAccessCheck::TOKEN_KEY);
    $valid_extensions = $element['#upload_validators']['FileExtension']['extensions'] ?? '';
    $element['#attached']['drupalSettings']['webform_strawberryfield']['tus'][$element['#webform_key']]['url'] =  $url->toString();
    $element['#attached']['drupalSettings']['webform_strawberryfield']['tus'][$element['#webform_key']]['X-CSRF-Token'] = $token;
    $element['#attached']['drupalSettings']['webform_strawberryfield']['tus'][$element['#webform_key']]['X-TUS-Extensions'] = $valid_extensions;
    $element['#attached']['drupalSettings']['webform_strawberryfield']['tus'][$element['#webform_key']]['chunksize'] = $element['#chunksize'] ?? 0;
    // Not multiple means at least one? Also, unlimited means == true, we are handling that undirectly (and it works) in the JS but might be good
    // to be super explicit.
    $element['#attached']['drupalSettings']['webform_strawberryfield']['tus'][$element['#webform_key']]['file_limit'] = $element['#multiple'] ?? 1;


    // Add managed file upload tracking.
    if ($this->moduleHandler->moduleExists('file')) {
      $element['#attached']['library'][] = 'webform/webform.element.managed_file';
    }
  }

  /**
   * Get max file size for an element.
   *
   * @param array $element
   *   An element.
   *
   * @return int
   *   Max file size.
   */
  protected function getMaxFileSize(array $element) {
    $max_filesize = $this->configFactory->get('webform.settings')->get('file.default_max_filesize') ?: Environment::getUploadMaxSize();
    $max_filesize = Bytes::toNumber($max_filesize);
    if (!empty($element['#max_filesize'])) {
      $max_filesize = min($max_filesize, Bytes::toNumber($element['#max_filesize'] . 'MB'));
    }
    return $max_filesize;
  }

  protected function getMaxFileSizeTus(array $element) {
    $max_filesize = $this->configFactory->get('webform.settings')->get('file.default_max_filesize');
    $max_filesize = Bytes::toNumber($max_filesize);
    if (!empty($element['#max_filesize_tus'] ?? NULL)) {
      if ($max_filesize != 0.0) {
        $max_filesize = min($max_filesize, Bytes::toNumber($element['#max_filesize_tus'] . 'MB'));
      }
      else {
        $max_filesize =  Bytes::toNumber($element['#max_filesize_tus'] . 'MB');
      }
    }
    $max_filesize = Bytes::toNumber($max_filesize);
    return $max_filesize;
  }

  public function form(array $form, FormStateInterface $form_state) {
    $max_filesize = $this->getMaxFileSizeTus([]);
    $form = parent::form($form, $form_state); // TODO: Change the autogenerated stub
    $form['file']['max_filesize_tus'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum file size when the User\'s browser is TUS capable.'),
      '#field_suffix' => $this->t('MB (Max: @filesize MB)', ['@filesize' => $max_filesize]),
      '#placeholder' => $max_filesize,
      '#description' => $this->t('Enter the max file size a user may upload via TUS. Tus has no fileSize limit defined by PHP, given that it uploads using resumable chunks via JS. Still. You need to able to store the file so when not limited  by Webform global config we set it max to 10Gbytes. Keep it discrete. Also please make sure that your Server admin has set `client_max_body_size` on NGINX to the same or larger size'),
      '#min' => 1,
      '#max' => 10000,
      '#step' => 'any',
    ];

    $form['file']['chunksize'] = [
      '#type' => 'number',
      '#title' => $this->t('Chunksize to be used by the TUS Client.'),
      '#field_suffix' => $this->t('Bytes'),
      '#description' => $this->t('A value of 0 (default and recommended) allows TUS decided. Only if you have issues upload files using PATCH, when e.g behind Cloudfare, you could use multiples of 256Kbytes (256000) to work around those issues.'),
      '#min' => 0,
      '#max' => 52428800,
      '#step' => 'any',
    ];


    return $form;

  }

  /**
   * Get file upload location.
   *
   * @param array $element
   *   An element.
   * @param \Drupal\webform\WebformInterface $webform
   *   A webform.
   *
   * @return string
   *   Upload location.
   */
  protected function getUploadLocation(array $element, WebformInterface $webform) {
    $upload_location = $this->getUriScheme($element) . '://webform/' . $webform->id() . '/_sid_/' . $this->currentUser->getAccountName() . '/tus';
    // Make sure the upload location exists and is writable.
    $this->fileSystem->prepareDirectory($upload_location, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    return $upload_location;
  }

}
