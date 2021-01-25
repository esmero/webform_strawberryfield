<?php

namespace Drupal\webform_strawberryfield\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Utility\WebformArrayHelper;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Plugin\WebformElement\Select;

/**
 * Provides a 'select' element.
 *
 * @WebformElement(
 *   id = "webform_select_withlabel",
 *   api = "https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Render!Element!Select.php/class/Select",
 *   label = @Translation("Select with Label and Value Storage"),
 *   description = @Translation("Provides a form element for a drop-down menu or scrolling selection box that stores label and value."),
 *   category = @Translation("Options elements"),
 * )
 */
class WebformSelectWithLabel extends Select {

  /**
   * {@inheritdoc}
   */
  public function prepare(array &$element, WebformSubmissionInterface $webform_submission = NULL) {
    parent::prepare($element, $webform_submission);
    // dpm($element);
  }

}
