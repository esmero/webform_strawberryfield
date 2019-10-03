<?php
/**
 * Created by PhpStorm.
 * User: dpino
 * Date: 12/2/18
 * Time: 5:17 PM
 */

namespace Drupal\webform_strawberryfield\Plugin\WebformElement;

use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Plugin\WebformElement\WebformCompositeBase;

/**
 * Provides an 'Panorama Builder' element.
 *
 * @WebformElement(
 *   id = "webform_metadata_panoramatour",
 *   label = @Translation("A Panorama Builder Element"),
 *   description = @Translation("Provides a form element to build multi scene Panorama Tours."),
 *   category = @Translation("Composite elements"),
 *   multiline = TRUE,
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 * )
 */
class WebformPanoramaTour extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  public function getPluginLabel() {
    return $this->elementManager->isExcluded('webform_metadata_panoramatour') ? $this->t('Panorama Multi Scene Builder') : parent::getPluginLabel();
  }

  /**
   * {@inheritdoc}
   */
  protected function formatHtmlItemValue(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    return $this->formatTextItemValue($element, $webform_submission, $options);
  }

  /**
   * {@inheritdoc}
   */
  protected function formatTextItemValue(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $value = $this->getValue($element, $webform_submission, $options);

    $lines = [];
    if (!empty($value['scene'])) {
      $lines[] = $value['scene'];
    }
    if (!empty($value['hotspots'])) {
      $lines[] = $value['hotspots'];
    }


    return $lines;
  }

}
