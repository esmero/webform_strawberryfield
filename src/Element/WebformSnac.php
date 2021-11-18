<?php

namespace Drupal\webform_strawberryfield\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Element\WebformCompositeBase;


/**
 * Provides a webform element for a Snac element.
 *
 * @FormElement("webform_metadata_snac")
 */
class WebformSnac extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    // #rdftype one of person, corporateBody, family, thing will kick in any
    $info =  parent::getInfo() + [
      '#vocab' => 'Constellation',
      '#rdftype' => 'thing'
    ];
    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public static function getCompositeElements(array $element) {

    $elements = [];
    $vocab = 'Constellation';
    $rdftype = 'thing';
    if (isset($element['#vocab'])) {
      $vocab = $element['#vocab'];
    }
    if (($vocab == 'rdftype') && isset($element['#rdftype'])) {
      $rdftype = trim($element['#rdftype']);
    }

    $class = '\Drupal\webform_strawberryfield\Element\WebformLoC';
    $elements['label'] = [
      '#type' => 'textfield',
      '#title' => t('Label'),
      '#autocomplete_route_name' => 'webform_strawberryfield.auth_autocomplete',
      '#autocomplete_route_parameters' => ['auth_type' => 'snac', 'vocab' => $vocab, 'rdftype'=> $rdftype ,'count' => 10],
      '#attributes' => [
        'data-source-strawberry-autocomplete-key' => 'label',
        'data-target-strawberry-autocomplete-key' => 'uri'
      ],

    ];
    $elements['uri'] = [
      '#type' => 'url',
      '#title' => t('Snac URL'),
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
    $vocab = 'Constellation';
    $rdftype = 'thing';

    $element = parent::processWebformComposite($element, $form_state, $complete_form);
    if (isset($element['#vocab'])) {
      $vocab = $element['#vocab'];
    }
    if (($vocab == 'rdftype') && isset($element['#rdftype'])) {
      $rdftype = trim($element['#rdftype']);
    }
    $element['label']["#autocomplete_route_parameters"] =
      ['auth_type' => 'snac', 'vocab' => $vocab, 'rdftype'=> $rdftype ,'count' => 10];

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

    $element['#attributes']['data-strawberry-autocomplete'] = 'Snac';
    return $element;
  }


}
