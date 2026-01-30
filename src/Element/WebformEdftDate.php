<?php

namespace Drupal\webform_strawberryfield\Element;

use Drupal\Core\Render\Element\Textfield;
use Drupal\Core\Form\FormStateInterface;
use EDTF\EdtfFactory;

/**
 * Provides a simple webform element for EDTF Dates.
 *
 * Allows a valid EDTF date.
 *
 * @FormElement("webform_edtf_date")
 */
class WebformEdftDate extends Textfield {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();
    $class = get_class($this);
    $info['#element_validate'] = [
    [$class, 'validateMetadataDates']
    ];
    return $info;
  }



  /**
   * Validates EDTF
   */
  public static function validateMetadataDates(&$element, FormStateInterface $form_state, &$complete_form) {
    // Don't validate empties.
    if (empty($element['#value'])) {
      $element['#validated'] = TRUE;
    }
    else {
      $validator = EdtfFactory::newValidator();
      if (!$validator->isValidEdtf($element['#value'])) {
        $form_state->setError($element,
          t('The extended date time format string for the @name field is invalid.',
            [
              '@name' => $element['#title'],
            ]));
      }
      else {
        $element['#validated'] = TRUE;
      }
    }
  }
}
