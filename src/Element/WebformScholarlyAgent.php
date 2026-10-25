<?php

namespace Drupal\webform_strawberryfield\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Element\WebformCompositeBase;
use Drupal\webform\Element\WebformMultiple;
use Drupal\Component\Utility\Html;
use Drupal\webform\Utility\WebformElementHelper;

/**
 * Provides a webform element for a LoC based Agents with Roles.
 *
 * @FormElement("webform_metadata_scholarly_agent")
 */
class WebformScholarlyAgent extends WebformCompositeBase {


  public const LOCAL_ROLE_OPTIONS = [
    'ContactPerson' =>  'Contact Person',
    'DataCollector' => 'Data Collector',
    'DataCurator' => 'Data Curator',
    'DataManager' => 'Data Manager',
    'Scholar' => 'Scholar',
    'Student' => 'Student',
    'Editor' => 'Editor',
    'Producer' => 'Producer',
    'Author' => 'Author',
    'PrimaryAuthor' => 'Primary Author',
    'CoAuthor' => 'Co-author',
    'ProjectLeader' => 'Project Leader',
    'ProjectManager' => 'Project Manager',
    'ProjectMember' =>  'Project Member',
    'RelatedPerson' => 'Relate Person',
    'Researcher' => 'Researcher',
    'RightsHolder' => 'Rights Holder',
    'Sponsor' => 'Sponsor',
    'Supervisor' => 'Supervisor',
    'WorkPackageLeader' => 'Work Package Leader',
    'Other' => 'Other',
  ];

  public const AGENT_TYPES = [
        'corporate' => 'Corporate',
        'personal' => 'Personal',
        'family' => 'Family'
      ];
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
        '#name_label' => 'name_label',
        '#name_uri' => 'name_uri',
        '#agent_type' => 'agent_type',
        '#role_label' => 'role_label',
        '#role_uri' => 'role_uri',
        '#identifier_orcid' => 'identifier_orcid',
        '#affiliation' => 'affiliation',
        '#affiliation_url' => 'affiliation_url',
        '#graduation_year' => 'graduation_year',
        '#local_role' => 'local_role',
        '#vocab_affiliation' => 'names',
        '#rdftype_affiliation' => 'CorporateName',
        '#agent_type_options' =>  ['personal'],
      ];
    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public static function getCompositeElements(array $element) {
    $elements = [];

    // Roles from https://datacite-metadata-schema.readthedocs.io/en/4.5/appendices/appendix-1/contributorType/
    // With extras
    $local_role_options = static::LOCAL_ROLE_OPTIONS;

    // This god forsaken function gets called many many times
    // and it gets more and more data everytime!
    // Why? We may never know
    $class = \Drupal\webform_strawberryfield\Element\WebformScholarlyAgent::class;

    $vocab_personal_name = 'names';
    $rdftype_personal_name = 'thing';
    $role_type = 'loc';

    $vocab_affiliation = 'names';
    $rdftype_affiliation = 'thing';

    $name_label_key = $element['#name_label'] ?? 'name_label';
    $name_uri_key = $element['#name_uri'] ?? 'name_uri';
    $name_label_key = trim($name_label_key);
    $name_uri_key = trim($name_uri_key);
    $agent_type_key = $element['#agent_type'] ?? 'agent_type';
    $agent_type_key = trim($agent_type_key);
    $role_label_key = $element['#role_label'] ?? 'role_label';
    $role_uri_key = $element['#role_uri'] ?? 'role_uri';
    $identifier_orcid_key = $element['#identifier_orcid'] ?? 'identifier_orcid';
    $affiliation_key = $element['#affiliation'] ?? 'affiliation';
    $affiliation_uri_key = $element['#affiliation_uri'] ?? 'affiliation_uri';
    $graduation_year_key = $element['#graduation_year'] ?? 'graduation_year';
    $local_role_key = $element['#local_role'] ?? 'local_role';

    if (isset($element['#vocab_personal_name'])) {
      $vocab_personal_name = $element['#vocab_personal_name'];
    }
    if (($vocab_personal_name == 'rdftype') && isset($element['#rdftype_personal_name'])) {
      $rdftype_personal_name = trim($element['#rdftype_personal_name']);
    }

    if (isset($element['#vocab_affiliation'])) {
      $vocab_affiliation = $element['#vocab_affiliation'];
    }
    if (($vocab_affiliation == 'rdftype') && isset($element['#rdftype_affiliation'])) {
      $rdftype_affiliation = trim($element['#rdftype_affiliation']);
    }

    if (isset($element['#role_type'])) {
      $role_type = $element['#role_type'];
    }

    $agent_type_options = static::AGENT_TYPES;
    $selectedagent_type_options = $element['#agent_type_options'] ?? ['personal'];
    foreach ($agent_type_options as $key => $option) {
      if (!in_array($key, $selectedagent_type_options)) {
        unset($agent_type_options[$key]);
      }
    }


    $elements[$agent_type_key] = [
      '#type' => 'select',
      '#title' => $element['#agent_type__title'] ?? t('Agent Type'),
      '#title_display' => $element['#agent_type__title_display'] ?? 'before',
      '#description' => $element['#agent_type__description'] ?? '',
      '#help' => $element['#agent_type__help'] ?? '',
      '#required' => $element['#agent_type__required'] ?? FALSE,
      '#empty_option' => t('- Please select one -'),
      '#attributes' => [
        'data-source-strawberry-autocomplete-key' => $name_label_key,
      ],
      '#options' => $agent_type_options,
      '#default_value' => reset($agent_type_options),
    ];

    $elements[$name_label_key] = [
      '#type' => 'textfield',
      '#title' => $element['#name_label__title'] ?? t('Agent Name'),
      '#title_display' => $element['#name_label__title_display'] ?? 'before',
      '#other__placeholder' => $element['#name_label__placeholder'] ?? '',
      '#description' => $element['#name_label__description'] ?? '',
      '#help' => $element['#name_label__help'] ?? '',
      '#required' => $element['#name_label__required'] ?? FALSE,
      '#autocomplete_route_name' => 'webform_strawberryfield.auth_autocomplete',
      '#autocomplete_route_parameters' => [
        'auth_type' => 'loc',
        'vocab' => $vocab_personal_name,
        'rdftype' => $rdftype_personal_name,
        'count' => 10
      ],
      '#attributes' => [
        'data-source-strawberry-autocomplete-key' => $name_label_key,
        'data-target-strawberry-autocomplete-key' => $name_uri_key
      ],

    ];
    $elements[$name_uri_key] = [
      '#type' => 'url',
      '#title' => $element['#name_uri__title'] ?? t('Agent URL'),
      '#title_display' => $element['#name_uri__title_display'] ?? 'before',
      '#other__placeholder' => $element['#name_uri__placeholder'] ?? '',
      '#description' => $element['#name_uri__description'] ?? '',
      '#help' => $element['#name_uri__help'] ?? '',
      '#required' => $element['#name_uri__required'] ?? FALSE,
      '#attributes' => ['data-strawberry-autocomplete-value' => TRUE]

    ];

    $elements[$role_label_key] = [
      '#type' => 'textfield',
      '#title' => $element['#role_label__title'] ?? t('Role'),
      '#title_display' => $element['#role_label__title_display'] ?? 'before',
      '#description' => $element['#role_label__description'] ?? '',
      '#help' => $element['#role_label__help'] ?? '',
      '#required' => $element['#role_label__required'] ?? FALSE,
      '#other__placeholder' => $element['#role_label__placeholder'] ?? '',
      '#autocomplete_route_name' => 'webform_strawberryfield.auth_autocomplete',
      '#autocomplete_route_parameters' => [
        'auth_type' => $role_type,
        'vocab' => 'relators',
        'rdftype' => 'thing',
        'count' => 10
      ],
      '#attributes' => [
        'data-source-strawberry-autocomplete-key' => $role_label_key,
        'data-target-strawberry-autocomplete-key' => $role_uri_key
      ],

    ];
    $elements[$role_uri_key] = [
      '#type' => 'url',
      '#title' => $element['#role_uri__title'] ?? t('Role URL'),
      '#title_display' => $element['#role_uri__title_display'] ?? 'before',
      '#description' => $element['#role_uridescription'] ?? '',
      '#help' => $element['#role_uri__help'] ?? '',
      '#required' => $element['#role_uri__required'] ?? FALSE,
      '#other__placeholder' => $element['#role_uri__placeholder'] ?? '',
      '#attributes' => ['data-strawberry-autocomplete-value' => TRUE]
    ];

    $elements[$identifier_orcid_key] = [
      '#type' => 'textfield',
      '#title' => $element['#identifier_orcid__title'] ?? t('ORCID'),
      '#title_display' => $element['#identifier_orcid__title_display'] ?? 'before',
      '#description' => $element['#identifier_orcid__description'] ?? '',
      '#help' => $element['#identifier_orcid__help'] ?? '',
      '#required' => $element['#identifier_orcid__required'] ?? FALSE,
      '#other__placeholder' => $element['#identifier_orcid__placeholder'] ?? '',
      '#autocomplete_route_name' => 'webform_strawberryfield.auth_autocomplete',
      '#autocomplete_route_parameters' => [
        'auth_type' => 'orcid',
        'vocab' => 'orcid',
        'rdftype' => 'thing',
        'match' => 'exact',
        'count' => 10
      ],
      '#attributes' => [
        'data-source-strawberry-autocomplete-key' => $identifier_orcid_key,
        'data-target-strawberry-autocomplete-key' => $identifier_orcid_key,
        'data-strawberry-autocomplete-value' => TRUE
      ],
    ];

    $elements[$affiliation_key] = [
      '#type' => 'textfield',
      '#title' => $element['#affiliation__title'] ?? t('Affiliation'),
      '#title_display' => $element['#affiliation__title_display'] ?? 'before',
      '#description' => $element['#affiliation__description'] ?? '',
      '#help' => $element['#affiliation__help'] ?? '',
      '#required' => $element['#affiliation__required'] ?? FALSE,
      '#other__placeholder' => $element['#affiliation__placeholder'] ?? '',
      '#autocomplete_route_name' => 'webform_strawberryfield.auth_autocomplete',
      '#autocomplete_route_parameters' => [
        'auth_type' => 'loc',
        'vocab' => $vocab_affiliation,
        'rdftype' => $rdftype_affiliation,
        'count' => 10
      ],
      '#attributes' => [
        'data-source-strawberry-autocomplete-key' => $affiliation_key,
        'data-target-strawberry-autocomplete-key' => $affiliation_uri_key
      ],
    ];
    $elements[$affiliation_uri_key] = [
      '#type' => 'url',
      '#title' => $element['#affiliation_uri__title'] ?? t('Affiliation URL'),
      '#title_display' => $element['#affiliation_uri__title_display'] ?? 'before',
      '#description' => $element['#affiliation_uri__description'] ?? '',
      '#help' => $element['#affiliation_uri__help'] ?? '',
      '#required' => $element['#affiliation_uri__required'] ?? FALSE,
      '#other__placeholder' => $element['#affiliation_uri__placeholder'] ?? '',
      '#attributes' => ['data-strawberry-autocomplete-value' => TRUE]
    ];
    $elements[$graduation_year_key] = [
      '#type' => 'number',
      '#min' => 1900,
      '#max' => 2046, // Eastern egg/movie :)
      '#step' => 1,
      '#title' => $element['#graduation_year__title'] ?? t('Graduation Year'),
      '#title_display' => $element['#graduation_year__title_display'] ?? 'before',
      '#description' => $element['#graduation_year__description'] ?? '',
      '#help' => $element['#graduation_year__help'] ?? '',
      '#required' => $element['#graduation_year__required'] ?? FALSE,
      '#other__placeholder' => $element['#graduation_year__placeholder'] ?? '',
    ];
    $elements[$local_role_key] = [
      '#type' => 'select',
      '#options' => $local_role_options,
      '#title' => $element['#local_role__title'] ?? t('Local Role'),
      '#title_display' => $element['#local_role__title_display'] ?? 'before',
      '#description' => $element['#local_role__description'] ?? '',
      '#help' => $element['#local_role__help'] ?? '',
      '#required' => $element['#local_role__required'] ?? FALSE,
      '#other__placeholder' => $element['#local_role__placeholder'] ?? '',
    ];

    $elements[$name_label_key]['#process'][] = [$class, 'processAutocomplete'];
    $elements[$role_label_key]['#process'][] = [$class, 'processAutocomplete'];
    // Future Note. Processing break the self/autocomplete for ORCID
    //$elements[$identifier_orcid_key]['#process'][] = [$class, 'processAutocomplete'];
    $elements[$affiliation_key]['#process'][] = [$class, 'processAutocomplete'];
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
    $select = $form_state->getTriggeringElement();
    // Interesting... i can actually pass arguments via the original #ajax callback
    // Any argument passed will become a new thing!
    // Example frm $form_state->getTriggeringElement()['#ajax'] by adding extra
    // Argments to the array on the caller element.

    $to_return_parents = array_slice($select['#array_parents'], 0, -1);
    $to_return_parents[] = $select['#attributes']['data-source-strawberry-autocomplete-key'] ?? 'name_label';
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
    $vocab_family_name = 'names';
    $vocab_corporate_name = 'names';
    $rdftype_corporate_name = 'CorporateName';
    $rdftype_family_name = 'FamilyName';
    $rdftype_personal_name = 'thing'; // In case we need a default for WIKIDATA
    $role_type = 'loc';

    $element = parent::processWebformComposite($element, $form_state, $complete_form);

    $class = \Drupal\webform_strawberryfield\Element\WebformScholarlyAgent::class;

    $unique_id = $element['#webform_id'] . implode('-', $element['#parents']);

    $ajax = [
      'callback' => [$class, 'ajaxCallbackChangeType'],
      'wrapper' => $unique_id,
      'event' => 'change',
    ];
    $name_label_key = $element['#name_label'] ?? 'name_label';
    $name_uri_key = $element['#name_uri'] ?? 'name_uri';
    $name_label_key = trim($name_label_key);
    $name_uri_key = trim($name_uri_key);
    $agent_type_key = $element['#agent_type'] ?? 'agent_type';
    $agent_type_key = trim($agent_type_key);
    $role_label_key = $element['#role_label'] ?? 'role_label';
    $role_uri_key = $element['#role_uri'] ?? 'role_uri';
    $identifier_orcid_key = $element['#$identifier_orcid'] ?? '$identifier_orcid';

    $element[$agent_type_key]['#ajax'] = $ajax;

    if (isset($element['#role_type'])) {
      $role_type = $element['#role_type'];
    }

    if (isset($element['#vocab_personal_name'])) {
      $vocab_personal_name = $element['#vocab_personal_name'];
    }
    if (isset($element['#vocab_family_name'])) {
      $vocab_family_name = $element['#vocab_family_name'];
    }
    if (isset($element['#vocab_corporate_name'])) {
      $vocab_corporate_name = $element['#vocab_corporate_name'];
    }

    if (($vocab_personal_name == 'rdftype') && isset($element['#rdftype_personal_name'])) {
      $rdftype_personal_name = !empty(trim($element['#rdftype_personal_name'])) ? trim($element['#rdftype_personal_name']) : $rdftype_personal_name;
    }
    else {
      // Defaults to full name LoC endpoint
      $vocab_personal_name = 'names';
      $rdftype_personal_name = 'thing';
    }

    if (($vocab_family_name == 'rdftype') && isset($element['#rdftype_family_name'])) {
      $rdftype_family_name = !empty(trim($element['#rdftype_family_name'])) ? trim($element['#rdftype_family_name']) : $rdftype_family_name;
    }
    else {
      // Defaults to full name LoC endpoint
      $vocab_family_name = 'names';
      $rdftype_family_name = 'thing';
    }

    if (($vocab_corporate_name == 'rdftype') && isset($element['#rdftype_corporate_name'])) {
      $rdftype_corporate_name = !empty(trim($element['#rdftype_corporate_name'])) ? trim($element['#rdftype_corporate_name']) : $rdftype_corporate_name;
    }
    else {
      // Defaults to full name LoC endpoint
      $vocab_corporate_name = 'names';
      $rdftype_corporate_name = 'thing';
    }

    $autocomplete_label_default = [
      'auth_type' => 'loc',
      'vocab' => $vocab_personal_name,
      'rdftype' => $rdftype_personal_name,
      'count' => 10
    ];

    $form_state_key = $element['#parents'];
    // In case of multiple elements the actual value (form state) will be buried deep
    // into some extra item wrappers. So we use the #parent of the element to match
    // and it should be enough!
    $form_state_key[] = $agent_type_key;

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

    $element[$name_label_key]['#autocomplete_route_parameters'] = $autocomplete_label_default;

    $element[$name_label_key]['#prefix'] = '<div id="' . $unique_id . '">';
    $element[$name_label_key]['#suffix'] = '</div>';

    $element['role_label']['#autocomplete_route_parameters'] =
      [
        'auth_type' => $role_type,
        'vocab' => 'relators',
        'rdftype' => 'thing',
        'count' => 10
      ];

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

  protected static function initializeCompositeElementsRecursive(array &$element, array &$composite_elements) {
    /** @var \Drupal\webform\Plugin\WebformElementManagerInterface $element_manager */
    $element_manager = \Drupal::service('plugin.manager.webform.element');
    // Very similar to its parent. But, because we allow the user to override the default keys, we are going to take that in account here
    //Mappins are new keys => original ones
    // Because this is called recursively, chances are the key mappings won't exist
    // at deeper levels, so we check with an isset().
    $key_mappings = [
        trim($element['#name_label'] ?? '') ?? 'name_label '=> 'name_label',
        trim($element['#name_uri'] ?? '') ?? 'name_uri' => 'name_uri',
        trim($element['#agent_type'] ?? '') ?? 'agent_type' => 'agent_type',
        trim($element['#role_label'] ?? '') ?? 'role_label' =>  'role_label',
        trim($element['#role_uri'] ?? '') ?? 'role_uri' => 'role_uri',
        trim($element['#identifier_orcid'] ?? '') ?? 'identifier_orcid' => 'identifier_orcid',
        trim($element['#affiliation'] ?? '') ?? 'affiliation' => 'affiliation',
        trim($element['#affiliation_url'] ?? '') ?? 'affiliation_url' => 'affiliation_url',
        trim($element['#graduation_year'] ?? '') ?? 'graduation_year' => 'graduation_year',
        trim($element['#local_role'] ?? '') ?? 'local_role' => 'local_role',
    ];
    // $composite_elements already holds the new keys
    foreach ($composite_elements as $composite_key => &$composite_element) {
      if (WebformElementHelper::property($composite_key)) {
        continue;
      }
      // Transfer '#{composite_key}_{property}' from main element to composite
      // element but taking in account the new keys provided by the user
      foreach ($element as $property_key => $property_value) {
        if ((str_starts_with($property_key, '#' . $composite_key . '__'))){
          $composite_property_key = str_replace('#' . $composite_key . '__', '#', $property_key);
          $composite_element[$composite_property_key] = $property_value;
        }
        elseif (isset($key_mappings[$composite_key]) && (str_starts_with($property_key, '#' . $key_mappings[$composite_key] . '__'))) {
          $composite_property_key = str_replace('#' . $key_mappings[$composite_key]  . '__', '#', $property_key);
          $composite_element[$composite_property_key] = $property_value;
        }
      }

      // Initialize composite sub-element.
      $element_plugin = $element_manager->getElementInstance($composite_element);

      // Make sure to remove any #options references from unsupported elements.
      // This prevents "An illegal choice has been detected." error.
      // @see FormValidator::performRequiredValidation()
      if (isset($composite_element['#options']) && !$element_plugin->hasProperty('options')) {
        unset($composite_element['#options']);
      }

      // Convert #placeholder to #empty_option for select elements.
      if (isset($composite_element['#placeholder']) && $element_plugin->hasProperty('empty_option')) {
        $composite_element['#empty_option'] = $composite_element['#placeholder'];
      }

      // Apply #select2, #choices, and #chosen to select elements.
      if (isset($composite_element['#type']) && str_contains($composite_element['#type'], 'select')) {
        $select_properties = [
          '#select2' => '#select2',
          '#choices' => '#choices',
          '#chosen' => '#chosen',
        ];
        $composite_element += array_intersect_key($element, $select_properties);
      }

      if ($element_plugin->hasMultipleValues($composite_element)) {
        throw new \Exception('Multiple elements are not supported within composite elements.');
      }
      if ($element_plugin->isComposite()) {
        throw new \Exception('Nested composite elements are not supported within composite elements.');
      }

      $element_plugin->initialize($composite_element);

      static::initializeCompositeElementsRecursive($element, $composite_element);
    }
  }

}
