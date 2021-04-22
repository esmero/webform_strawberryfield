<?php

namespace Drupal\webform_strawberryfield\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\Core\Render\Element\Select;
use Drupal\Component\Utility\NestedArray;
use Drupal\webform\Utility\WebformElementHelper;


/**
 * Provides a webform element for a wikidata element.
 *
 * @FormElement("webform_select_withlabel")
 */
class WebformSelectWithLabel extends Select {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $properties = parent::getInfo();
    $class = get_class($this);
    $properties['#process'][] = [$class, 'processSelectWithLabel'];
    return $properties;
  }

  public static function processSelectWithLabel(&$element, FormStateInterface $form_state, &$complete_form) {
    if (isset($element['#initialize'])) {
      return $element;
    }
    $element['#initialize'] = TRUE;
    // Add validate callback.
    $element += ['#element_validate' => []];
    array_unshift($element['#element_validate'], [get_called_class(), 'validateWebformSelectWithLabel']);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE) {
      error_log('$input not empty');
      if (isset($element['#multiple']) && $element['#multiple']) {
        // If an enabled multi-select submits NULL, it means all items are
        // unselected. A disabled multi-select always submits NULL, and the
        // default value should be used.
        if (empty($element['#disabled'])) {
          return (is_array($input)) ? array_combine($input, $input) : [];
        }
        else {
          error_log(print_r($element['#default_value'],true));
          return (isset($element['#default_value']) && is_array($element['#default_value'])) ? $element['#default_value'] : [];
        }
      }
      // Non-multiple select elements may have an empty option prepended to them
      // (see \Drupal\Core\Render\Element\Select::processSelect()). When this
      // occurs, usually #empty_value is an empty string, but some forms set
      // #empty_value to integer 0 or some other non-string constant. PHP
      // receives all submitted form input as strings, but if the empty option
      // is selected, set the value to match the empty value exactly.
      elseif (isset($element['#empty_value']) && $input === (string) $element['#empty_value']) {
        return $element['#empty_value'];
      }
      else {
        return $input;
      }
    } else {
      if (isset($element['#default_value'])) {
        error_log('$input empty');
        if (is_array($element['#default_value']) && $element['#multiple']) {
          $to_return = [];
          foreach($element['#default_value'] as $entry) {
            if (isset($entry['value'])) {
              $to_return[$entry['value']] = $entry['value'];
            }
          }
          return $to_return;
        } else {
          if (isset($element['#default_value']['value'])) {
            return $element['#default_value']['value'];
          }
        }
      }
    }
  }

  /**
   * Validates a composite element.
   *
   * @param $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param $complete_form
   */
  public static function validateWebformSelectWithLabel(&$element, FormStateInterface $form_state, &$complete_form) {
    error_log('calling validateWebformSelectWithLabel');
    error_log(print_r($element['#value'],true));
    error_log(print_r($element['#default_value'],true));
    $value = NestedArray::getValue($form_state->getValues(), $element['#parents']);
    error_log(print_r($value,true));
    $options = OptGroup::flattenOptions($element['#options']);
    error_log(print_r($options, true));
    // Only validate composite elements that are visible.
    $has_access = (!isset($element['#access']) || $element['#access'] === TRUE);
    error_log('has access '. $has_access);
    error_log('is processing input ' . $form_state->isProcessingInput());
    error_log('is rebuilding ' . $form_state->isRebuilding());

    if (!empty($value)) {
      if (isset($element['#multiple']) && $element['#multiple']) {
        $to_set = [];
        foreach ($value as $item) {
          $to_set[] = [
            'value' => $item,
            'label' => $options[$item]
          ];
        }

      } else {
        $to_set = [
          'value' => $value,
          'label' => $options[$value]
        ];
      }
      error_log('setting value');
      error_log(print_r($to_set, true));
      $form_state->setValueForElement($element, $to_set);
    }
  }


}
