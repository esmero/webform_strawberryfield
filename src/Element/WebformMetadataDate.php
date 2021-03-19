<?php

namespace Drupal\webform_strawberryfield\Element;

use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Utility\WebformElementHelper;
use Drupal\webform\Element\WebformCompositeFormElementTrait;
use Drupal\Component\Utility\NestedArray;
use EDTF\EdtfFactory;

/**
 * Provides a webform element requiring users to double-element and confirm an email address.
 *
 * Formats as a pair of email addresses fields, which do not validate unless
 * the two entered email addresses match.
 *
 * @FormElement("webform_metadata_date")
 */
class WebformMetadataDate extends FormElement {

  use WebformCompositeFormElementTrait;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {

    $class = get_class($this);
    $info['#input'] = TRUE;
    $info['#size'] = 60;
    $info['#pre_render'] = [
        [$class, 'preRenderWebformCompositeFormElement'],
      ];
     $info['#required'] = FALSE;
      // Add validate callback.
    $info['#element_validate'] = [[$class, 'validateMetadataDates']];
    $info['#process'] = [[$class, 'processWebformMetadataDate']];
    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE) {
      if (!isset($input) && !empty($element['#default_value'])) {
        $element['#needs_validation'] = TRUE;
      }
      return $input;
    }
    else {
     $value = isset($element['#default_value']) ? $element['#default_value'] : NULL;
      if (!isset($value)) {
        $element['#has_garbage_value'] = TRUE;
      }
      return $value;
    }
  }

  /**
   * Expand date element into multiple inputs. .
   */
  public static function processWebformMetadataDate(&$element, FormStateInterface $form_state, &$complete_form) {

    $element['#tree'] = TRUE;
    $name_prefix = $element['#name'];

    // Get shared properties.
    $shared_properties = [
      '#size',
      '#maxlength',
      '#pattern',
      '#pattern_error',
      '#required',
      '#required_error',
      '#placeholder',
      '#attributes',
    ];
    // Also distribute general Attributes to each input
    $element_shared_properties = [
        '#type' => 'date',
        '#webform_element' => TRUE,
      ] + array_intersect_key($element, array_combine($shared_properties, $shared_properties));
    // Copy wrapper attributes to shared element attributes.
    if (isset($element['#wrapper_attributes'])
      && isset($element['#wrapper_attributes']['class'])) {
      foreach ($element['#wrapper_attributes']['class'] as $index => $class) {
        if (in_array($class, ['js-webform-tooltip-element', 'webform-tooltip-element'])) {
          $element_shared_properties['#wrapper_attributes']['class'][] = $class;
          unset($element['#wrapper_attributes']['class'][$index]);
        }
      }
    }

    $date_from_properties = [
      '#title',
      '#description',
      '#help_title',
      '#help',
      '#help_display',
    ];

    $date_from_value = NULL;
    $date_to_value = NULL;
    $date_free_value = NULL;

    // Let's parse the value passed and let's see which date type it conforms
    $type = 'date_free';
    if (isset($element['#default_value']) && !empty($element['#default_value'])) {
      if (is_array($element['#default_value'])) {
        // This is the default. When stored value is present
        $type = isset($element['#default_value']['date_type']) ? $element['#default_value']['date_type'] : $type;
        $date_from_value = isset($element['#default_value']['date_from']) ? $element['#default_value']['date_from'] : $date_from_value;
        $date_to_value = isset($element['#default_value']['date_to']) ? $element['#default_value']['date_to'] : $date_to_value;
        $date_free_value = isset($element['#default_value']['date_free']) ? $element['#default_value']['date_free'] : $date_free_value;
      }
      else {
        // Means we are reading a string and we are parsing it depending on the format into
        // Components
        // Start with range
        $range_split = explode('/', $element['#default_value']);
        if (count($range_split) == 2) {
          $type = 'date_range';
          foreach ($range_split as $key => $possible_date_string) {
            if ($key == 0) {
              $date_from_value = $possible_date_string;
            }
            else {
              $date_to_value = $possible_date_string;
            }
            if (strtotime($possible_date_string) === FALSE) {
              $type = 'date_free';
            }
          }
        }
        if ($type == 'date_free') {
          if (strtotime($element['#default_value']) !== FALSE) {
            $type = 'date_point';
            $date_from_value = $element['#default_value'];
            $date_to_value = NULL;
          }
          else {
            $date_free_value = $element['#default_value'];
          }
        }
      }
    }

    if(!empty($element['#edtf_validateme'])) {
      $date_free_msg = t('EDTF formatted date');
    }
    else {
      $date_free_msg = t('Free form Date (e.g   Circa Spring of 1977)');
    }
    // The date formatting/options
    $element['date_type'] = [
      '#type' => 'radios',
      '#title' => '',
      '#default_value' => $type,
      '#options' => [
        'date_point' => t('Date'),
        'date_range' => t('Date Range'),
        'date_free' => $date_free_msg,
      ]
    ];
    /* Just and idea? Since we need modal labels.
    // We may make the labels simply HTML and then deal with them via JS?
    $element['date_from_label'] = [
      '#type' => 'html_tag',
      '#tag' => 'label',
      '#attributes' => [
        'for' => $element['#id'].'date-from'
      ],
      '#value' => 'Start Date',
      '#states' => [
        'invisible' => [
          [':input[name="' . $name_prefix . '[date_type]"]' => ['value' => 'date_point']],
          'or',
          [':input[name="' . $name_prefix . '[date_type]"]' => ['value' => 'date_free']],
        ]
      ]
    ];
    $element['date_point_label'] = [
      '#type' => 'html_tag',
      '#tag' => 'label',
      '#attributes' => [
        'for' => $element['#id'].'date-from'
      ],
      '#value' => 'Point Date',
      '#states' => [
        'invisible' => [
          [':input[name="' . $name_prefix . '[date_type]"]' => ['value' => 'date_range']],
          'or',
          [':input[name="' . $name_prefix . '[date_type]"]' => ['value' => 'date_free']],
        ]
      ]
    ];
    $element['date_to_label'] = [
      '#type' => 'html_tag',
      '#tag' => 'label',
      '#attributes' => [
        'for' => $element['#id'].'date-to'
      ],
      '#value' => 'End Date',
      '#states' => [
        'invisible' => [
          [':input[name="' . $name_prefix . '[date_type]"]' => ['value' => 'date_point']],
          'or',
          [':input[name="' . $name_prefix . '[date_type]"]' => ['value' => 'date_free']],
        ]
      ]
    ];
    */

    $element['date_from'] = $element_shared_properties + array_intersect_key($element, array_combine($date_from_properties, $date_from_properties));
    $element['date_from']['#attributes']['class'][] = 'webform-date-from';
    $element['date_from']['#default_value'] = $date_from_value;
    $element['date_from']['#error_no_message'] = TRUE;

    $element['date_to'] = $element_shared_properties;

    $element['date_to']['#attributes']['class'][] = 'webform-date-to';
    $element['date_to']['#default_value'] = $date_to_value;
    $element['date_to']['#error_no_message'] = TRUE;


    $element['date_to']['#title'] = t('End Date (ISO 8601)');
    $element['date_from']['#title'] = t('Start or Point Date (ISO 8601)');

    // pass #date attributes to sub elements before calling ::buildElement

    $element['date_to']['#date_date_format'] = $element['#date_date_format'];
    $element['date_from']['#date_date_format'] = $element['#date_date_format'];

    if (isset($element['#date_date_min'])) {
      $element['date_to']['#date_date_min'] = $element['#date_date_min'];
      $element['date_from']['#date_date_min'] = $element['#date_date_min'];
    }
    if (isset($element['#date_date_max'])) {
      $element['date_to']['#date_date_max'] = $element['#date_date_max'];
      $element['date_from']['#date_date_max'] = $element['#date_date_max'];
    }

    if (isset($element['#attributes']['min'])) {
      $element['date_to']['#attributes']['min'] = $element['#attributes']['min'];
      $element['date_from']['#attributes']['min'] = $element['#attributes']['min'];
    }
    if (isset($element['#attributes']['max'])) {
      $element['date_to']['#attributes']['max'] = $element['#attributes']['max'];
      $element['date_from']['#attributes']['max'] = $element['#attributes']['max'];
    }

    if (isset($element['#attributes']['data-min-year'])) {
      $element['date_to']['#attributes']['data-min-year'] = $element['#attributes']['data-min-year'];
      $element['date_from']['#attributes']['data-min-year'] = $element['#attributes']['data-min-year'];
    }

    if (isset($element['#attributes']['data-max-year'])) {
      $element['date_to']['#attributes']['data-max-year'] = $element['#attributes']['data-max-year'];
      $element['date_from']['#attributes']['data-max-year'] = $element['#attributes']['data-max-year'];
    }
    $element['date_to']['#attributes']['data-drupal-date-format'] =  $element['#attributes']['data-drupal-date-format'];
    $element['date_from']['#attributes']['data-drupal-date-format'] = $element['#attributes']['data-drupal-date-format'];


    $element['date_from']['#datepicker'] = TRUE;
    $element['date_to']['#datepicker'] = TRUE;

    $element['date_free']['#title'] = t('Free form Date');
    $element['date_free']['#attributes']['class'][] = 'webform-date-free';
    $element['date_free']['#default_value'] = $date_free_value;
    $element['date_free']['#error_no_message'] = TRUE;
    $element['date_free']['#type'] = 'textfield';
    $element['date_free']['#webform_element'] = TRUE;
    $element['date_free']['#size'] =  isset($element['#size']) ? $element['#size'] : '60';

    $element['date_to']['#states'] = [
      'invisible' => [
        [':input[name="' . $name_prefix . '[date_type]"]' => ['value' => 'date_point']],
        'or',
        [':input[name="' . $name_prefix . '[date_type]"]' => ['value' => 'date_free']],
      ]
    ];

    $element['date_from']['#states'] = [
      'invisible' => [
        ':input[name="' . $name_prefix . '[date_type]"]' => ['value' => 'date_free'],
      ],
    ];



    if (!isset($element['#showfreeformalways']) || (isset($element['#showfreeformalways']) && $element['#showfreeformalways'] == FALSE )) {
      $element['date_free']['#states'] = [
        'invisible' => [
          [':input[name="' . $name_prefix . '[date_type]"]' => ['value' => 'date_point']],
          'or',
          [':input[name="' . $name_prefix . '[date_type]"]' => ['value' => 'date_range']],
        ]
      ];
    }


    // Don't require the main element.
    $element['#required'] = FALSE;

    // Hide title and description from being display.
    //$element['#title_display'] = 'invisible';
    //$element['#description_display'] = 'invisible';

    // Remove properties that are being applied to the sub elements.
    unset(
      $element['#maxlength'],
      $element['#attributes'],
      $element['#description'],
      $element['#help'],
      $element['#help_title'],
      $element['#help_display']
    );


    // Add flexbox support.
    if (!empty($element['#flexbox'])) {
      $flex_wrapper = [
        '#prefix' => '<div class="webform-flex webform-flex--1"><div class="webform-flex--container">',
        '#suffix' => '</div></div>',
      ];
      $element['flexbox'] = [
        '#type' => 'webform_flexbox',
        'date_type' => $element['date_type'] + $flex_wrapper + [
          '#parents' => array_merge($element['#parents'], ['date_type']),
          ],
        'date_from' => $element['date_from'] + $flex_wrapper + [
            '#parents' => array_merge($element['#parents'], ['date_from']),
          ],
        'date_to' => $element['date_to'] + $flex_wrapper + [
            '#parents' => array_merge($element['#parents'], ['date_to']),
          ],
        'date_free' => $element['date_free'] + $flex_wrapper + [
            '#parents' => array_merge($element['#parents'], ['date_free']),
          ],
      ];
      unset($element['date_from'], $element['date_to'],  $element['date_free'], $element['date_type']);
    }
    return $element;
  }

  /**
   * Validates our weird date element
   */
  public static function validateMetadataDates(&$element, FormStateInterface $form_state, &$complete_form) {
    //@TODO remake this whole method. Current implementation is just too complex.
    if (isset($element['flexbox'])) {
      $metadatadate_element =& $element['flexbox'];
    }
    else {
      $metadatadate_element =& $element;
    }
    if (empty($element['#value']) && isset($element['#webform_multiple'])) {
      $value = NestedArray::getValue($form_state->getValues(), $element['#parents']);
      $filtered_value = array_filter($value);
      // Empty elements here will carry at least the date_type making them
      // not empty. So deal with that by unsetting the value completely in
      // that case.
      if (count($filtered_value) == 1 && isset($filtered_value['date_type'])) {
        $multi_item_parents = array_slice($element['#parents'],-2);
        $makeshift_element['#parents'] = $multi_item_parents;
        $form_state->setValueForElement($makeshift_element, NULL);
      }
      /*$valuetoset = [
        'date_free' => $metadatadate_element['date_free']['#value'],
        'date_from' => $metadatadate_element['date_from']['#value'],
        'date_to' => $metadatadate_element['date_to']['#value'],
        'date_type' => $metadatadate_element['date_type']['#value'],
        ]; */
    } else {
      if (!empty($element['#value'])) {
        $value = $form_state->getValue($element['#parents'], []);
        $filtered_value = array_filter($value);
        // Empty elements here will carry at least the date_type making them
        // not empty. So deal with that by unsetting the value completely in
        // that case.
        if (count($filtered_value) == 1 && isset($filtered_value['date_type'])) {
          $element['#value'] = [];
        } else {
          $element['#value'] = $value;
        }
        $form_state->setValueForElement($element, $element['#value']);
      }
    }

    // Perform edtf validation on freeform date if so configured.
    if(!empty($metadatadate_element['#edtf_validateme']) && !empty($element['#value']['date_free'])) {
      $validator = EdtfFactory::newValidator();
      if (!$validator->isValidEdtf($element['#value']['date_free'])) {
        // @TODO: Figure out how to get the error message to display for $element['date_free'] only.
        // @TODO: If $form_state->setError($element['date_free'],...) is used, no error message appears.
        $form_state->setError($element,
          t('The extended date time format string for the @name field is invalid.',
            [
              '@name' => $element['#title'],
            ]));
      }
    }
  }
}
