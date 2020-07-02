<?php

namespace Drupal\webform_strawberryfield\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Element\WebformCompositeBase;


/**
 * Provides a webform element for a wikidata element.
 *
 * @FormElement("webform_metadata_wikidata")
 */
class WebformWikiData extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    //@TODO add an extra option to define auth_type.
    //@TODO expose as an select option inside \Drupal\webform_strawberryfield\Plugin\WebformElement\WebformLoC
    $info = parent::getInfo();
    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public static function getCompositeElements(array $element) {
    $elements = [];
    $class = '\Drupal\webform_strawberryfield\Element\WebformWikiData';
    $elements['label'] = [
      '#type' => 'textfield',
      '#title' => t('Label'),
      '#autocomplete_route_name' => 'webform_strawberryfield.auth_autocomplete',
      '#autocomplete_route_parameters' => array('auth_type' => 'wikidata', 'count' => 10),
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
    $composite_elements = static::getCompositeElements($element);
    // URL should always end in the HTML to Ajax can autofill the URI coming from
    // LoD provider.
    // #access = false is really just converting the elements to hidden elements.
    // @warning Sadly \Drupal\webform_strawberryfield\Element\WebformWikiData::processWebformComposite is never called
    // if the multiple__header is enabled.
    // To get around that we call \Drupal\webform_strawberryfield\Plugin\WebformElement\WebformWikiData::hiddenElementAfterBuild
    foreach ($composite_elements as $composite_key => $composite_element) {
      if ($composite_key != 'value') {
        if (isset($element[$composite_key]['#access']) && $element[$composite_key]['#access'] === FALSE) {
          unset($element[$composite_key]['#access']);
          unset($element[$composite_key]['#pre_render']);
          $element[$composite_key]['#type'] = 'hidden';
        }
      }
    }
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

    $element['#attributes']['data-strawberry-autocomplete'] = 'wikidata';
    return $element;
  }



}
