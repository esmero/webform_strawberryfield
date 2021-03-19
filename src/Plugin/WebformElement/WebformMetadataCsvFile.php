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
 * Provides a 'file element' that can import CSV into a flat JSON.
 *
 * @WebformElement(
 *   id = "webform_metadata_csv_file",
 *   api = "https://api.drupal.org/api/drupal/core!modules!file!src!Element!ManagedFile.php/class/ManagedFile",
 *   label = @Translation("Import Metadata in CSV format from a File"),
 *   description = @Translation("Provides a form element for uploading, saving a CSV file and parsing the content as metadata/webform submission data."),
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
    $form['jsonkey']['#title'] = $this->t("jsonkey to use to store CSV in JSON format");
    $form['keepfile']['#title'] = $this->t("Keep imported CSV File after persisting?");
    return $form;
  }


  /**
   * Process CSV content.
   *
   * @param \Drupal\file\FileInterface $file
   *
   * @return array
   */
  protected function processFileContent(FileInterface $file) {
    $jsonarray = [];
    $data = [];
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
      while (($csvdata = fgetcsv($handle, "2048", ",")) !== FALSE) {
        $index++;
        if ($index < 2) {
          foreach($csvdata as $values) {
            // Because for each is always faster than map
            // In case the cleaning up means we end with empty, we still add it
            // Because we need to maintain the column index to fetch the right
            // row/cell data.
            $headers[] = trim(preg_replace('/[^[:print:]]/', '', $values));
          }
         if (!count($headers)) { break 1;}
        } else {
          // Actual data
          foreach($headers as $columnindex => $header) {
            // Totally skip empty headers(includes empty ones after cleanup)
            if (strlen($header) > 0) {
              $data[$index][$header] = isset($csvdata[$columnindex])
                ? $csvdata[$columnindex] : "";
            }
          }
        }
      }
    }
    else {
      return $jsonarray;
    }

    if (!empty($data) && ($index >=2)) {
      $md5 = md5_file($uri);
      $jsonarray = [
        'dr:uuid' => $file->uuid(),
        'checksum' => $md5,
        'crypHashFunc' =>  'md5',
        'webform_element_type' => $this->pluginDefinition['id'],
        'standard' => NULL,
        'content' => $data,
        'format' => 'csv',
      ];
    }
    return $jsonarray;
  }
}
