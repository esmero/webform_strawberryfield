<?php

namespace Drupal\webform_strawberryfield\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Element\WebformCompositeBase;


/**
 * Provides a webform element for a LoD from Webform options.
 *
 * @FormElement("webform_metadata_options")
 */
class WebformLoDfromOptions extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {

    $info = parent::getInfo();
    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public static function getCompositeElements(array $element) {

    $elements = [];


    $class = '\Drupal\webform_strawberryfield\Element\WebformLoDfromOptions';
    $elements['label'] = [
      '#type' => 'search',
      '#title' => t('Label'),
      '#attributes' => [
        'data-source-strawberry-autocomplete-key' => 'label',
        'data-target-strawberry-autocomplete-key' => 'uri',
        'autocomplete' => 'off'
      ],
      '#autocomplete_route_name' => $element['#webform_composite_elements']['label']['#autocomplete_route_name'] ?? NULL,
      '#autocomplete_route_parameters' => $element['#webform_composite_elements']['label']['#autocomplete_route_parameters'] ?? NULL,
    ];
    $elements['uri'] = [
      '#type' => 'url',
      '#title' => t('Subject URL'),
      '#attributes' => ['data-strawberry-autocomplete-value' => TRUE]
    ];
    $elements['label']['#process'][] =  [$class, 'processAutocomplete'];
    return $elements;
  }



  /**
   * {@inheritdoc}
   */
  public static function processWebformComposite(&$element, FormStateInterface $form_state, &$complete_form) {
    // @Disclaimer: This function is the worst and deceiving. Keeping it here
    // So i never make this error again. Because of
    // \Drupal\webform\Plugin\WebformElement\WebformCompositeBase::prepareMultipleWrapper
    // Basically, in case of having multiple elements :: processWebformComposite
    // is *never* called because it actually converts the 'WebformComposite' element into a
    // \Drupal\webform\Element\WebformMultiple calling ::processWebformMultiple element
    // So basically whatever i do here gets skipped if multiple elements are allowed.
    // Solution is acting here instead:
    // \Drupal\webform_strawberryfield\Plugin\WebformElement\WebformLoC::prepareMultipleWrapper

    $element = parent::processWebformComposite($element, $form_state, $complete_form);
    return $element;
  }

  /**
   * @param array $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param array $complete_form
   *
   * @return array
   */
  public static function processAutocomplete(&$element, FormStateInterface $form_state, &$complete_form) {
    $element = parent::processAutocomplete($element, $form_state, $complete_form);
    $element['#attached']['library'][] = 'webform_strawberryfield/webform_strawberryfield.metadataauth.autocomplete';
    $element['#attached']['drupalSettings'] = [
      'webform_strawberryfield_autocomplete' => [],
    ];
    // Used by the JS as a Selector. just needs a value.
    $element['#attributes']['data-strawberry-autocomplete'] = 'webform_options';
    return $element;
  }


}
