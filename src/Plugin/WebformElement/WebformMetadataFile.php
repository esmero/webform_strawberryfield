<?php
/**
 * Created by PhpStorm.
 * User: dpino
 * Date: 12/2/18
 * Time: 5:17 PM
 */

namespace Drupal\webform_strawberryfield\Plugin\WebformElement;

use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Plugin\WebformElement\WebformManagedFileBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\file\FileInterface;
use Drupal\strawberryfield\Tools\JsonSimpleXMLElementDecorator;
use Drupal\strawberryfield\Tools\SimpleXMLtoArray;

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

}
