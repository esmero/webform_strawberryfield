<?php

namespace Drupal\webform_strawberryfield\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Element\WebformCompositeBase;
use Drupal\webform\Element\WebformMultiple;
use Drupal\Component\Utility\Html;


/**
 * Provides a webform element for a wikidata element.
 *
 * @FormElement("webform_metadata_multiagent")
 */
class WebformMultiAgent extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo() + [
        '#vocab_personal_name' => 'names',
        '#rdftype_personal_name' => 'FullName',
        '#vocab_family_name' => 'names',
        '#rdftype_family_name' => 'FamilyName',
        '#vocab_corporate_name' => 'names',
        '#rdftype_corporate_name' => 'CorporateName',
        '#role_type' => 'loc',
      ];
    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public static function getCompositeElements(array $element) {
    $elements = [];
    // This god forsaken function gets called many many times
    // and it gets more and more data everytime!
    // Why? We may never know
    $class = '\Drupal\webform_strawberryfield\Element\WebformMultiAgent';

    $vocab_personal_name = 'names';
    $rdftype_personal_name = 'thing'; // In case we need a default for WIKIDATA
    $vocab_corporate_name = 'names';
    $rdftype_corporate_name = 'CorporateName';
    $role_type = 'loc';

    if (isset($element['#vocab_personal_name'])) {
      $vocab_personal_name = $element['#vocab_personal_name'];
    }
    if (($vocab_personal_name == 'rdftype') && isset($element['#rdftype_personal_name'])) {
      $rdftype_personal_name = trim($element['#rdftype_personal_name']);
    }

    if (isset($element['#role_type'])) {
      $role_type = $element['#role_type'];
    }

    $elements['agent_type'] = [
      '#type' => 'select',
      '#title' => t('Agent Type'),
      '#title_display' => 'invisible',
      '#options' => [
        'corporate' => 'Corporate',
        'personal' => 'Personal',
        'family' => 'Family'
      ],
      '#default_value' => 'Personal'
    ];

    $elements['name_label'] = [
      '#type' => 'textfield',
      '#title' => t('Agent Name'),
      //'#title_display' => 'invisible',
      '#autocomplete_route_name' => 'webform_strawberryfield.auth_autocomplete',
      '#autocomplete_route_parameters' => ['auth_type' => 'loc', 'vocab' => $vocab_personal_name, 'rdftype'=> $rdftype_personal_name ,'count' => 10],
      '#attributes' => [
        'data-source-strawberry-autocomplete-key' => 'name_label',
        'data-target-strawberry-autocomplete-key' => 'name_uri'
      ],

    ];
    $elements['name_uri'] = [
      '#type' => 'url',
      '#title' => t('Agent URL'),
      //'#title_display' => 'invisible',
      '#attributes' => ['data-strawberry-autocomplete-value' => TRUE]
    ];

    $elements['role_label'] = [
      '#type' => 'textfield',
      '#title' => t('Role'),
      //'#title_display' => 'invisible',
      '#autocomplete_route_name' => 'webform_strawberryfield.auth_autocomplete',
      '#autocomplete_route_parameters' => [
        'auth_type' => $role_type,
        'vocab' => 'relators',
        'rdftype' => 'thing',
        'count' => 10
      ],
      '#attributes' => [
        'data-source-strawberry-autocomplete-key' => 'role_label',
        'data-target-strawberry-autocomplete-key' => 'role_uri'
      ],

    ];
    $elements['role_uri'] = [
      '#type' => 'url',
      '#title' => t('Role URL'),
      '#attributes' => ['data-strawberry-autocomplete-value' => TRUE]
    ];
    $elements['name_label']['#process'][] =  [$class, 'processAutocomplete'];
    $elements['role_label']['#process'][] =  [$class, 'processAutocomplete'];
    return $elements;
  }

  /**
   * Ajax callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   An associative array containing entity reference details element.
   */
  public static function ajaxCallbackChangeType(array $form, FormStateInterface $form_state) {
    $trigger_name = $form_state->getTriggeringElement()['#name'];
    $select = $form_state->getTriggeringElement();
    // Interesting... i can actually pass arguments via the original #ajax callback
    // Any argument passed will become a new thing!
    // Example frm $form_state->getTriggeringElement()['#ajax'] by adding extra
    // Argments to the array on the caller element.

    $to_return_parents = array_slice($select['#array_parents'], 0, -1);
    $to_return_parents[] = 'name_label';
    $to_return = NestedArray::getValue($form, $to_return_parents);
    $to_return['#autocomplete_route_parameters'] =
      [
        'auth_type' => 'wikidata',
        'vocab' => 'rdftype',
        'rdftype' => 'thing',
        'count' => 10,
      ];

    return $to_return;
  }

  /**
   * {@inheritdoc}
   */
  public static function processWebformComposite(&$element, FormStateInterface $form_state, &$complete_form) {
    // Called everytime now since we unset
    // at \Drupal\webform_strawberryfield\Plugin\WebformElement\WebformMultiAgent::getDefaultProperties
    $vocab_personal_name = 'names';
    $rdftype_personal_name = 'thing'; // In case we need a default for WIKIDATA
    $vocab_corporate_name = 'names';
    $rdftype_corporate_name = 'CorporateName';
    $role_type = 'loc';

    $element = parent::processWebformComposite($element, $form_state, $complete_form);

    $class = '\Drupal\webform_strawberryfield\Element\WebformMultiAgent';

    $unique_id = $element['#webform_id'] . implode('-', $element['#parents']);

    $ajax = [
      'callback' => [$class, 'ajaxCallbackChangeType'],
      'wrapper' => $unique_id,
      'event' => 'change',
    ];
    $element['agent_type']['#ajax'] = $ajax;

    if (isset($element['#role_type'])) {
      $role_type = $element['#role_type'];
    }

    if (isset($element['#vocab_personal_name'])) {
      $vocab_personal_name = $element['#vocab_personal_name'];
    }
    if (($vocab_personal_name == 'rdftype') && isset($element['#rdftype_personal_name'])) {
      $rdftype_personal_name = trim($element['#rdftype_personal_name']);
    }

    if (isset($element['#vocab_family_name'])) {
      $vocab_family_name = $element['#vocab_family_name'];
    }
    if (($vocab_family_name == 'rdftype') && isset($element['#rdftype_family_name'])) {
      $rdftype_family_name = trim($element['#rdftype_family_name']);
    }

    if (isset($element['#vocab_corporate_name'])) {
      $vocab_corporate_name = $element['#vocab_corporate_name'];
    }
    if (($vocab_corporate_name == 'rdftype') && isset($element['#rdftype_corporate_name'])) {
      $rdftype_corporate_name = trim($element['#rdftype_corporate_name']);
    }

    $autocomplete_label_default =  ['auth_type' => 'loc', 'vocab' => $vocab_personal_name, 'rdftype'=> $rdftype_personal_name ,'count' => 10];

    $form_state_key = $element['#parents'];
    // In case of multiple elements the actual value (form state) will be buried deep
    // into some extra item wrappers. So we use the #parent of the element to match
    // and it should be enough!
    $form_state_key[] = 'agent_type';

    if ($form_state->getValue($form_state_key)) {
      $agent_type = $form_state->getValue($form_state_key);
      if ($agent_type == 'personal') {
        $autocomplete_label_default = [
          'auth_type' => 'loc',
          'vocab' => $vocab_personal_name,
          'rdftype' => $rdftype_personal_name,
          'count' => 10
        ];
      }
      elseif ($agent_type == 'corporate') {
        $autocomplete_label_default = [
          'auth_type' => 'loc',
          'vocab' => $vocab_corporate_name,
          'rdftype' => $rdftype_corporate_name,
          'count' => 10
        ];
      }
      elseif ($agent_type == 'family') {
        $autocomplete_label_default = [
          'auth_type' => 'loc',
          'vocab' => $vocab_family_name,
          'rdftype' => $rdftype_family_name,
          'count' => 10
        ];
      }
    }

    $element['name_label']['#autocomplete_route_parameters'] = $autocomplete_label_default;

    $element['name_label']['#prefix'] = '<div id="'.$unique_id.'">';
    $element['name_label']['#suffix'] = '</div>';

    $element['role_label']['#autocomplete_route_parameters'] =
      ['auth_type' => $role_type, 'vocab' => 'relators','rdftype' => 'thing',  'count' => 10];

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
    // Does process get called everytime i do an ajax callback?
    $element = parent::processAutocomplete($element, $form_state, $complete_form);
    $element['#attached']['library'][] = 'webform_strawberryfield/webform_strawberryfield.metadataauth.autocomplete';
    $element['#attached']['drupalSettings'] = [
      'webform_strawberryfield_autocomplete' => [],
    ];

    $element['#attributes']['data-strawberry-autocomplete'] = 'Multi';
    return $element;
  }
}
