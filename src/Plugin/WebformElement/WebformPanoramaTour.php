<?php
/**
 * Created by PhpStorm.
 * User: dpino
 * Date: 12/2/18
 * Time: 5:17 PM
 */

namespace Drupal\webform_strawberryfield\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Plugin\WebformElement\WebformElement;
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
 *   default_key = "panorama_tour",
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
    // No preview for now
    // Just too complex
    // @TODO next iteration can force a Formatter to be used.
    $lines = [];
    return $lines;
  }

  public function supportsMultipleValues() {
    // Make sure people can not change this value.
    return FALSE;
  }

  public function getDefaultProperties() {
    $defaults = parent::getDefaultProperties();
    unset($defaults['multiple']);
    return $defaults;
  }

  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['element']['multiple']['#disabled'] = TRUE;
    $form['element']['multiple']['#description'] = '<em>' . $this->t('You can only build one Tour with this Webform Element. But it supports multiple Scenes') . '</em>';
    // Disable Multiple Elements option
    return $form;
  }


}
