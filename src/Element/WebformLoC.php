<?php

namespace Drupal\webform_strawberryfield\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Element\WebformCompositeBase;


/**
 * Provides a webform element for a LoC element.
 *
 * @FormElement("webform_metadata_loc")
 */
class WebformLoC extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    //@TODO add an extra option to define auth_type.
    //@TODO expose as an select option inside \Drupal\webform_strawberryfield\Plugin\WebformElement\WebformLoC
    $info = parent::getInfo();
    // Always defaults to subjects
    $info = $info + [
      '#vocab' => 'subjects',
      '#rdftype' => 'FullNameElement',
    ];
    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public static function getCompositeElements(array $element) {
    $elements = [];
    $vocab = 'subjects';
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
      '#autocomplete_route_parameters' => array('auth_type' => 'loc', 'vocab' => $vocab, 'rdftype'=> $rdftype ,'count' => 10),
      '#attributes' => [
        'data-source-strawberry-autocomplete-key' => 'label',
        'data-target-strawberry-autocomplete-key' => 'uri'
      ],

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
    $element = parent::processWebformComposite($element, $form_state, $complete_form);

    //dpm('the element');
    //dpm($element);

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

    $element['#attributes']['data-strawberry-autocomplete'] = 'LoC';
    return $element;
  }

}
