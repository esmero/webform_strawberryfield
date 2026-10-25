<?php

namespace Drupal\webform_strawberryfield\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Element\WebformCompositeBase;


/**
 * Provides a webform element for a wikidata element.
 *
 * @FormElement("webform_metadata_wikidataagent")
 */
class WebformWikiDataAgent extends WebformWikiData {

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
    $class = \Drupal\webform_strawberryfield\Element\WebformWikiData::class;
    $elements['name_label'] = [
      '#type' => 'textfield',
      '#title' => t('Agent/Person Name'),
      //'#title_display' => 'invisible',
      '#autocomplete_route_name' => 'webform_strawberryfield.auth_autocomplete',
      '#autocomplete_route_parameters' => ['auth_type' => 'wikidata', 'count' => 10],
      '#attributes' => [
        'data-source-strawberry-autocomplete-key' => 'name_label',
        'data-target-strawberry-autocomplete-key' => 'name_uri'
      ],

    ];
    $elements['name_uri'] = [
      '#type' => 'url',
      '#title' => t('Agent/Person URL'),
      //'#title_display' => 'invisible',
      '#attributes' => ['data-strawberry-autocomplete-value' => TRUE]
    ];

    $elements['role_label'] = [
      '#type' => 'textfield',
      '#title' => t('Role'),
      //'#title_display' => 'invisible',
      '#autocomplete_route_name' => 'webform_strawberryfield.auth_autocomplete',
      '#autocomplete_route_parameters' => ['auth_type' => 'wikidata', 'count' => 10],
      '#attributes' => [
        'data-source-strawberry-autocomplete-key' => 'role_label',
        'data-target-strawberry-autocomplete-key' => 'role_uri'
      ],

    ];
    $elements['role_uri'] = [
      '#type' => 'url',
      '#title' => t('Role URL'),
      //'#title_display' => 'invisible',
      '#attributes' => ['data-strawberry-autocomplete-value' => TRUE]
    ];
    $elements['name_label']['#process'][] =  [$class, 'processAutocomplete'];
    $elements['role_label']['#process'][] =  [$class, 'processAutocomplete'];
    return $elements;
  }
}
