<?php
/**
 * Created by PhpStorm.
 * User: dpino
 * Date: 12/2/18
 * Time: 5:17 PM
 */

namespace Drupal\webform_strawberryfield\Plugin\WebformElement;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\NestedArray;
use Drupal\file\Element\ManagedFile;
use Drupal\file\Entity\File;
use Drupal\webform\WebformSubmissionForm;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Plugin\WebformElement\WebformManagedFileBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\file\FileInterface;
use Drupal\strawberryfield\Tools\JsonSimpleXMLElementDecorator;
use Drupal\strawberryfield\Tools\SimpleXMLtoArray;
use Drupal\webform_strawberryfield\Element\WebformStrawberryFieldManagedFile;

/**
 * Provides a 'file element that can import into the submission/ process other formats' element.
 *
 * @WebformElement(
 *   id = "webform_metadata_file",
 *   api = "https://api.drupal.org/api/drupal/core!modules!file!src!Element!ManagedFile.php/class/ManagedFile",
 *   label = @Translation("Import Metadata from a File"),
 *   description = @Translation("Provides a form element for uploading, saving a file and parsing the content as metadata/webform submission data."),
 *   category = @Translation("File upload elements"),
 *   states_wrapper = TRUE,
 * )
 */
class WebformMetadataFile extends WebformManagedFileBase {


  /**
   * {@inheritdoc}
   */
  public function getDefaultProperties() {

    $properties = parent::getDefaultProperties() + [
        'keepfile' => TRUE,
      ] + parent::getDefaultProperties();
    return $properties;

  }

  /**
   * {@inheritdoc}
   */
  public function prepare(
    array &$element,
    WebformSubmissionInterface $webform_submission = NULL
  ) {
    // @TODO explore this method to act on submitted data v/s element behavior
    // This only acts on upload
    // But once uploaded we need a way of doing it again.
    // Kids: this method is used by other subclasses. So always
    // Remember it needs to stay generic handling also existing keys
    parent::prepare($element, $webform_submission);

    $value = $this->getValue($element, $webform_submission, []);
    $file = $this->getFile($element, $value, []);
    $data = $webform_submission->getData();
    $needs_import = FALSE;
    if ($file) {
      $needs_import = TRUE;
      if (isset($data['ap:importeddata'][$this->getKey($element)]['dr:uuid'])) {
        if ($data['ap:importeddata'][$this->getKey($element)]['dr:uuid'] == $file->uuid()) {
          $needs_import = FALSE;
        }
      }
      if ($needs_import) {
        $imported_data['ap:importeddata'][$this->getKey($element)] = $this->processFileContent($file);
        if (isset($data['ap:importeddata']) && is_array($data['ap:importeddata'])) {
          $newimporteddata = array_merge($data['ap:importeddata'],
            $imported_data['ap:importeddata']);
        }
        else {
          $newimporteddata = $imported_data['ap:importeddata'];
        }
        $data['ap:importeddata'] = $newimporteddata;
        $webform_submission->setData($data);
      }
    }
  }

  /*
  * {@inheritdoc}
  */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    //@NOTE    'classification' => 'classification(LCCS)', is not working
    // Not sure if this has a sub authority and how that works/if suggest
    $form['file']['keepfile'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Keep imported XML after persisting?'),
      '#description' => $this->t('If the imported File should be kept as inline data or should be purged on save.'),
      '#default_value' => 'subjects',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(array &$element, WebformSubmissionInterface $webform_submission, $update = TRUE) {
    // Get current value and original value for this element.

    parent::preSave($element, $webform_submission, $update);
    $key = $element['#webform_key'];
    // $data = $webform_submission->getData();
    // $webform_submission->setData($data);
    $value = $this->getValue($element, $webform_submission, []);
    $files = $this->getFiles($element, $value, []);

    /* idea: we could remove the parsed JSON once its not needed anymore */
    // Why? Because we can always reparse it
    // Because we maybe just want to copy values into the purer simpler raw
    // elements.
    // Should be an option
  }

  /**
   * @param \Drupal\file\FileInterface $file
   *
   * @return array
   */
  protected function processFileContent(FileInterface $file) {
    $jsonarray = [];
    $xmljsonarray = [];
    if (!$file) {
      return $jsonarray;
    }
    $uri = $file->getFileUri();
    $mime = $file->getMimeType();
    if ($mime != 'application/xml') {
      return $jsonarray;
    }
    $data = file_get_contents($uri);
    $internalErrors = libxml_use_internal_errors(TRUE);
    libxml_clear_errors();
    libxml_use_internal_errors($internalErrors);

    $simplexml = simplexml_load_string($data);
    if ($simplexml === FALSE) {
      $messages = $this->getXmlErrors($internalErrors);
      if (empty($messages)) {
        $this->messenger()->addError(
          $this->t(
            'Sorry, the provided File @filename does not contain valid XML',
            ['@filename' => $file->getFileName()]
          )
        );
      }
      else {
        $this->messenger()->addError(
          $this->t(
            'Sorry, the provided File @filename XML has following errors @messages',
            [
              '@filename' => $file->getFileName(),
              '@messages' => implode("\n", $messages),
            ]
          )
        );
      }
    }
    else {
      // Root key is
      $rootkey = $simplexml->getName();
      $md5 = md5_file($uri);
      /*
      Not longer using the decorator here since we want to push
      consistently (shape) structured data, plus a few less CPU cycles
      $xmltojson = new JsonSimpleXMLElementDecorator($simplexml, TRUE, TRUE, 50);

      $xmljsonstring = json_encode($xmltojson, JSON_PRETTY_PRINT);
      $xmljsonarray =  json_decode($xmljsonstring, TRUE);
      */
      $SimpleXMLtoArray = new SimpleXMLtoArray($simplexml);
      $xmljsonarray = $SimpleXMLtoArray->xmlToArray();
      // We are casting everything to associative.
      // Do we want that?

      $jsonarray = [
        'dr:uuid' => $file->uuid(),
        'checksum' => $md5,
        'crypHashFunc' => 'md5',
        'standard' => $rootkey,
        'webform_element_type' => $this->pluginDefinition['id'],
        'content' => $xmljsonarray,
        'format' => 'xml',
      ];
    }
    return $jsonarray;
  }

  /**
   * Returns the XML errors of the internal XML parser.
   *
   * @param bool $internalErrors
   *
   * @return array An array of errors
   */
  private function getXmlErrors($internalErrors) {
    $errors = [];
    foreach (libxml_get_errors() as $error) {
      $errors[] = sprintf('[%s %s] %s (in %s - line %d, column %d)',
        LIBXML_ERR_WARNING == $error->level ? 'WARNING' : 'ERROR',
        $error->code,
        trim($error->message),
        $error->file ?: 'n/a',
        $error->line,
        $error->column
      );
    }

    libxml_clear_errors();
    libxml_use_internal_errors($internalErrors);

    return $errors;
  }


  protected function safe_json_encode($value, $options = 0, $depth = 512) {
    $encoded = json_encode($value, $options, $depth);
    if ($encoded === FALSE && $value && json_last_error() == JSON_ERROR_UTF8) {
      $encoded = json_encode($this->utf8ize($value), $options, $depth);
    }
    return $encoded;
  }

  protected function utf8ize($mixed) {
    if (is_array($mixed)) {
      foreach ($mixed as $key => $value) {
        $mixed[$key] = $this->utf8ize($value);
      }
    }
    elseif (is_string($mixed)) {
      return mb_convert_encoding($mixed, "UTF-8", "UTF-8");
    }
    return $mixed;
  }

  /**
   * Form API callback. Validates managed file input before processing uploads.
   *
   * Overrides \Drupal\webform\Plugin\WebformElement\WebformManagedFileBase::valueCallback
   * and used via a hook_webform_element_alter for every derived Element type
   *
   * @param array $element
   *   A managed file element.
   * @param mixed $input
   *   The submitted input.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The processed managed file value.
   *
   * @see \Drupal\file\Element\ManagedFile::valueCallback()
   * @see \Drupal\webform_strawberryfield\Element\WebformStrawberryFieldManagedFile::valueCallback()
   * @see \Drupal\webform\Plugin\WebformElement\WebformManagedFileBase::valueCallback
   */
  public static function valueCallback(array &$element, $input, FormStateInterface $form_state) {
    $form_object = $form_state->getFormObject();
    $fids = [];
    $valid_fids  = [];
    if ($input !== FALSE
      && !empty($input['fids'])
      && $form_object instanceof WebformSubmissionForm
      && $form_object->getOperation() === 'add') {
      $fids = array_map('intval', array_filter(explode(' ', $input['fids'])));
      $valid_fids = $fids;
      // Important Note. Default values won't be sent via INPUT
      // except during multi page next/prev
      // Which means on a fresh load ADO attached files won't
      // be detecte by this as being SBF, but
      // WebformStrawberryFieldManagedFile::valueCallback will keep them safe.
      foreach ($fids as $key => $fid) {
        $file = File::load($fid);
        $is_sbf = $file ? WebformStrawberryFieldManagedFile::hasAccessViaADO($file) : FALSE;

        if (!$is_sbf) {
          $is_invalid = (!$file || !$file->isTemporary() || !$file->access('download'));
          // This was preserved from Webform 3.2.1 I do not like the logic
          // But that is their security fix.
          if (!$is_invalid && $file->getOwnerId() != \Drupal::currentUser()
              ->id()) {
            $is_invalid = TRUE;
          }
          if (!$is_invalid && \Drupal::currentUser()->isAnonymous()) {
            // Use core's HMAC check for anonymous temporary file reuse.
            // @see \Drupal\file\Element\ManagedFile::valueCallback()
            $parents = array_merge($element['#parents'], [
              'file_' . $file->id(),
              'fid_token'
            ]);
            $token = NestedArray::getValue($form_state->getUserInput(), $parents);
            $file_hmac = Crypt::hmacBase64('file-' . $file->id(), \Drupal::service('private_key')
                ->get() . Settings::getHashSalt());
            $is_invalid = ($token === NULL || !hash_equals($file_hmac, $token));
          }
          if ($is_invalid) {
            // Do not include the file name because doing so confirms that a
            // tampered file id maps to an existing managed file.
            $form_state->setError($element, t('An uploaded file is invalid and was removed from the list.'));
            // We can't unset, we need to NULL-i-fy to keep the original INDEX
            $valid_fids[$key] = NULL;
            // This is different than original logic,
            //  here we restore the input as a string only with the valid values
            // removing anything that did not match.
            $input['fids'] = implode(' ', array_filter($valid_fids));
            break;
          }
        }
      }
    }

    $result = WebformStrawberryFieldManagedFile::valueCallback($element, $input, $form_state);

    // NOTE: the following comment does not apply to ADO managed files.
    // There is no submission, but we kept if for complenetness.

    // Drupal 11.4.5 filters default file IDs using file download access.
    // Webform authorizes private files through their associated submission, so
    // restore trusted default IDs to allow their file names to be displayed.
    // Submitted IDs are validated above, and private file downloads continue to
    // be protected by Webform's submission-aware access checks.
    // @see \Drupal\webform\Hook\WebformHooks::fileAccess()
    // @see ::accessFileDownload()
    // @see \Drupal\Tests\webform\Functional\Element\WebformElementManagedFilePreviewTest
    // @see https://www.drupal.org/project/drupal/issues/3593472
    // This is different than the base logic. Because we have a less destructive FID removal, $result['fids']
    // could be just less than the original one, but not empty.
    if (empty($result['fids'])
      && $input === FALSE
      && !empty($element['#default_value'])
      && !empty($element['#webform_key'])
      && $form_object instanceof WebformSubmissionForm
    ) {
      /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
      $webform_submission = $form_object->getEntity();
      $element_data = $webform_submission->getElementData($element['#webform_key']);
      if ($element_data) {
        $element_fids = (array) $element_data;
        $default_fids = $element['#default_value'];
        $result['fids'] = array_values(array_intersect($default_fids, $element_fids));
      }
    }

    return $result;
  }

}
