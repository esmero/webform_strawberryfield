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
   * @return array
   */
  public function getDefaultProperties() {

    $properties = parent::getDefaultProperties() + [
        'jsonkey' => 'imported_metadata',
        'keepxml' => TRUE,
      ] + parent::getDefaultProperties();
    return $properties;

  }

  public function prepare(
    array &$element,
    WebformSubmissionInterface $webform_submission = NULL
  ) {

    // @TODO explore this method to act on submitted data v/s element behavior

    parent::prepare($element,$webform_submission );
    $value = $this->getValue($element, $webform_submission, []);
    $file = $this->getFile($element, $value, []);
    $data = $webform_submission->getData();
    if ($file) {
      if (!isset($data['ap:importeddata']['dr:uuid']) ||
       $data['ap:importeddata']['dr:uuid'] != $file->uuid()) {
        $imported_xml = $this->processXML($file);
        $data = array_merge($data, $imported_xml);
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
    $form['jsonkey'] = [
      '#type' => 'textfield',
      '#title' => $this->t("jsonkey to use to store XML in JSON format"),
      '#description' => $this->t('JSON key to be used <em>names</em>'),
      '#default_value' => 'subjects',
    ];
    $form['keepxml'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Keep imported XML after persisting?'),
      '#description' => $this->t('If the imported XML should be kept as inline data or should be purged on save.'),
      '#default_value' => 'subjects',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(array &$element, WebformSubmissionInterface $webform_submission, $update = TRUE) {
    // Get current value and original value for this element.

    parent::postSave($element, $webform_submission, $update);
    $key = $element['#webform_key'];
    $data = $webform_submission->getData();
    $data['imported_xml_data'] = ['holi' => 'buh'];
    $webform_submission->setData($data);
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
  protected function processXML(FileInterface $file) {
    $jsonarray = [];
    if (!$file) {
      return $jsonarray;
    }
    $uri = $file->getFileUri();
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
              '@messages' => implode("\n", $messages)
            ]
          )
        );
      }
    }
    else {
      // Root key is
      $rootkey = $simplexml->getName();
      $md5 = md5_file($uri);
      $xmltojson = new JsonSimpleXMLElementDecorator($simplexml, TRUE, TRUE, 5);
      // Destination key
      $xmljsonstring = json_encode($xmltojson, JSON_PRETTY_PRINT);
      $xmljsonarray =  json_decode($xmljsonstring, TRUE);
      $jsonarray['ap:importeddata'] = [
        'dr:uuid' => $file->uuid(),
        'checksum' => $md5,
        'crypHashFunc' =>  'md5',
        'standard' => $rootkey,
        'content' => $xmljsonarray,
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
  protected function getXmlErrors($internalErrors)
  {
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
}
