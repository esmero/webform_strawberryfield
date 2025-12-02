<?php

namespace Drupal\webform_strawberryfield\Plugin\WebformElement;
use Drupal\webform\Plugin\WebformElement\TextBase;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Provides an 'EDTF' element.
 *
 * @WebformElement(
 *   id = "webform_edtf_date",
 *   label = @Translation("Simple EDTF Date"),
 *   description = @Translation("Provides a form element for entering a valid EDTF Date."),
 *   category = @Translation("Date/time elements"),
 *   states_wrapper = TRUE,
 * )
 */
class WebformEdtfDate extends TextBase {

  /**
   * {@inheritdoc}
   */
  protected function defineDefaultProperties() {
    return [
        'input_hide' => FALSE,
      ] + parent::defineDefaultProperties()
      + $this->defineDefaultMultipleProperties();
  }

  /* ************************************************************************ */

  /**
   * {@inheritdoc}
   */
  protected function formatHtmlItem(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $value = $this->getValue($element, $webform_submission, $options);

    if (empty($value)) {
      return '';
    }
    return parent::formatHtmlItem($element, $webform_submission, $options);
  }

  /**
   * @inheritDoc
   */
  public function getTestValues(array $element, WebformInterface $webform, array $options = []) {
    return ['1977-07-21'];
  }

}
