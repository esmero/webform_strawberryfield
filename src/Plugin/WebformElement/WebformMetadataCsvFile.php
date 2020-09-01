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
 *   id = "webform_metadata_csv_file",
 *   api = "https://api.drupal.org/api/drupal/core!modules!file!src!Element!ManagedFile.php/class/ManagedFile",
 *   label = @Translation("Import Metadata in CSV format from a File"),
 *   description = @Translation("Provides a form element for uploading, saving a file and parsing the content as metadata/webform submission data."),
 *   category = @Translation("File upload elements"),
 *   states_wrapper = TRUE,
 * )
 */

class WebformMetadataCsvFile extends WebformMetadataFile {

  /*
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    //@NOTE    'classification' => 'classification(LCCS)', is not working
    // Not sure if this has a sub authority and how that works/if suggest
    $form['jsonkey']['#title'] = $this->t("jsonkey to use to store XML in JSON format");
    $form['keepfile']['#title'] = $this->t("Keep imported CSV File after persisting?");
    return $form;
  }


  /**
   * @param \Drupal\file\FileInterface $file
   *
   * @return array
   */
  protected function processFileContent(FileInterface $file) {
    $jsonarray = [];
    $data = [];
    $xmljsonarray = [];
    if (!$file) {
      return $jsonarray;
    }
    $uri = $file->getFileUri();
    //read csv headers
    ini_set('auto_detect_line_endings',TRUE);
    setlocale(LC_CTYPE, 'en_US.UTF-8');

    $index = 0;
    $headers = [];
    //@TODO. Can we require a list of headers? Check against a list?

    if (($handle = fopen($uri, "r")) !== FALSE) {
      $keyIndex = [];
      while (($csvdata = fgetcsv($handle, "2048", ",")) !== FALSE) {
        $index++;
        if ($index < 2) {
          foreach($csvdata as $values) {
            // Because for each is always faster than map
            $headers[] = trim($values);
          }
         if (!count($headers)) { break 1;}
        } else {
          // Actual data
          foreach($headers as $columnindex => $header) {
            if (isset($csvdata))
            $data[$index][$header] = isset($csvdata[$columnindex]) ? $csvdata[$columnindex] : "";
          }
        }
      }
    }
    else {
     //empty!
    }

    if (!empty($data) && ($index >=2)) {
      $md5 = md5_file($uri);
      $jsonarray['ap:importeddatacsv'] = [
        'dr:uuid' => $file->uuid(),
        'checksum' => $md5,
        'crypHashFunc' =>  'md5',
        'standard' => $this->pluginDefinition['id'],
        'content' => $data,
      ];
    }
    return $jsonarray;
  }
}
