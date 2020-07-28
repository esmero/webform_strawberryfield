<?php

namespace Drupal\webform_strawberryfield\Element;

use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Utility\WebformElementHelper;
use Drupal\webform\Element\WebformCompositeFormElementTrait;

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
    return [
      '#input' => TRUE,
      '#size' => 60,
      '#process' => [
        [$class, 'processAjaxForm'],
        [$class, 'processWebformMetadataDate'],
      ],
      '#pre_render' => [
        [$class, 'preRenderWebformCompositeFormElement'],
      ],
      '#required' => FALSE,
      '#initialize' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input === FALSE) {
      if (!isset($element['#default_value'])) {
        $element['#default_value'] = '';
      }
      return [
        'date_from' => $element['#default_value'],
        'date_to' => $element['#default_value'],
        'date_free' => $element['#default_value'],
      ];
    }
    else {
      return $input;
    }
  }

  /**
   * Expand an email confirm field into two HTML5 email elements.
   */
  public static function processWebformMetadataDate(&$element, FormStateInterface $form_state, &$complete_form) {

    if (isset($element['#initialize'])) {
      return $element;
    }
    $element['#initialize'] = TRUE;


    $element['#tree'] = TRUE;
    $name_prefix = $element['#name'];

    // Get shared properties.
    $shared_properties = [
      '#title_display',
      '#description_display',
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

    // The date formatting/options
    $element['date_type'] = [
      '#type' => 'radios',
      '#title' => 'Date Type',
      '#default_value' => 'date_point',
      '#options' => [
        'date_point' => t('Valid ISO 8601 Date'),
        'date_range' => t('Valid ISO 8601 Range'),
        'date_free' => t('Freeform Date (e.g Circa Spring of 1977)'),
      ]
    ];

    $element['date_from'] = $element_shared_properties + array_intersect_key($element, array_combine($date_from_properties, $date_from_properties));
    $element['date_from']['#attributes']['class'][] = 'webform-date-from';
    $element['date_from']['#value'] = empty($element['#value']) ? NULL : $element['#value']['date_from'];
    $element['date_from']['#error_no_message'] = TRUE;

    $element['date_to'] = $element_shared_properties;
    $element['date_to']['#title'] = t('To Date');

    $element['date_to']['#attributes']['class'][] = 'webform-date-to';
    $element['date_to']['#value'] = empty($element['#value']) ? NULL : $element['#value']['date_to'];
    $element['date_to']['#error_no_message'] = TRUE;


    $element['date_to']['#title'] = t('To Date');
    $element['date_from']['#title'] = t('From Date');

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
    $element['date_free']['#value'] = empty($element['#value']) ? NULL : $element['#value']['date_free'];
    $element['date_free']['#error_no_message'] = TRUE;
    $element['date_free']['#type'] = 'textfield';
    $element['date_free']['#webform_element'] = TRUE;
    $element['date_free']['#size'] =  isset($element['#size']) ? $element['#size'] : '60';
    //dpm($element['date_free']);

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

    $element['date_free']['#states'] = [
      'invisible' => [
        [':input[name="' . $name_prefix . '[date_type]"]' => ['value' => 'date_point']],
        'or',
        [':input[name="' . $name_prefix . '[date_type]"]' => ['value' => 'date_range']],
      ]
    ];


    // Don't require the main element.
    $element['#required'] = FALSE;

    // Hide title and description from being display.
    $element['#title_display'] = 'invisible';
    $element['#description_display'] = 'invisible';

    // Remove properties that are being applied to the sub elements.
    unset(
      $element['#maxlength'],
      $element['#attributes'],
      $element['#description'],
      $element['#help'],
      $element['#help_title'],
      $element['#help_display']
    );

    // Add validate callback.
    $element += ['#element_validate' => []];
    array_unshift($element['#element_validate'], [get_called_class(), 'validateMetadataDates']);

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
   * Validates an our weird date element
   */
  public static function validateMetadataDates(&$element, FormStateInterface $form_state, &$complete_form) {
    if (isset($element['flexbox'])) {
      $metadatadate_element =& $element['flexbox'];
    }
    else {
      $metadatadate_element =& $element;
    }

    $date_from = trim($metadatadate_element['date_from']['#value']);
    $date_to = trim($metadatadate_element['date_to']['#value']);
    $date_free = trim($metadatadate_element['date_free']['#value']);
    $choosen_option = $metadatadate_element['date_type']['#value'];

    $value_to_save = $date_from;
    $has_access = (!isset($element['#access']) || $element['#access'] === TRUE);
    if ($has_access) {
      // Depending on the choosen option our validation will vary
      // Start with point data

      if ($choosen_option == 'date_point') {
        ///$form_state->setError($element, t('Date point'));
        // Date field must be converted from a two-element array into a single
        // string regardless of validation results.

        $element['#value'] = $value_to_save;
        $form_state->setValueForElement($element, $value_to_save);
      }

      if ($choosen_option == 'date_range') {
       // $form_state->setError($element, t('To Date needs to be same or higher than from Date'));
        $element['#value'] = $date_from.'/'.$date_to;
        $form_state->setValueForElement($element,  $date_from.'/'.$date_to);

      }

      if ($choosen_option == 'date_free') {
        //$form_state->setError($element, t('Free date basically allows anything'));
        $element['#value'] = $date_free;
        $form_state->setValueForElement($element,  $date_free);
      }

      // Compare email addresses.
      if ((!empty($date_from) || !empty($date_to)) && strcmp($date_from, $date_to)) {
        //$form_state->setError($element, t('To Date needs to be same or higher than from Date'));
      }
      else {
        // NOTE: Only date_from needs to be validated since date_to is the same value.
        // Verify the required value.
        if ($metadatadate_element['date_from']['#required'] && empty($date_from)) {
          $required_error_title = (isset($metadatadate_element['date_from']['#title'])) ? $metadatadate_element['date_from']['#title'] : NULL;
          WebformElementHelper::setRequiredError($element, $form_state, $required_error_title);
        }
        // Verify that the value is not longer than #maxlength.
        if (isset($metadatadate_element['date_from']['#maxlength']) && mb_strlen($date_from) > $metadatadate_element['date_from']['#maxlength']) {
          $t_args = [
            '@name' => $metadatadate_element['date_from']['#title'],
            '%max' => $metadatadate_element['date_from']['#maxlength'],
            '%length' => mb_strlen($date_from),
          ];
          $form_state->setError($element, t('@name cannot be longer than %max characters but is currently %length characters long.', $t_args));
        }
      }

      // Add email validation errors for inline form errors.
      // @see \Drupal\Core\Render\Element\Email::validateEmail
      $inline_errors = empty($complete_form['#disable_inline_form_errors'])
        && \Drupal::moduleHandler()->moduleExists('inline_form_errors');
      $mail_error = $form_state->getError($metadatadate_element['date_from']);
      if ($inline_errors && $mail_error) {
        $form_state->setError($element, $mail_error);
      }
    }

    // Set #title for other validation callbacks.
    // @see \Drupal\webform\Plugin\WebformElementBase::validateUnique
    if (isset($metadatadate_element['date_from']['#title'])) {
      $element['#title'] = $metadatadate_element['date_from']['#title'];
    }

    // Date field must be converted from a two-element array into a single
    // string regardless of validation results.
    /*$form_state->setValueForElement($metadatadate_element['date_from'], NULL);
    $form_state->setValueForElement($metadatadate_element['date_to'], NULL);
    $form_state->setValueForElement($metadatadate_element['date_free'], NULL);
    $form_state->setValueForElement($metadatadate_element['date_type'], NULL);*/

     //$element['#value'] = $value_to_save;
     //$form_state->setValueForElement($element, $value_to_save);
  }



}
