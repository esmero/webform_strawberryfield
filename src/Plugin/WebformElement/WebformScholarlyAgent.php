<?php
/**
 * Created by PhpStorm.
 * User: dpino
 * Date: 12/2/18
 * Time: 5:17 PM
 */

namespace Drupal\webform_strawberryfield\Plugin\WebformElement;

use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Plugin\WebformElement\WebformCompositeBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Provides an Composite Metadata element to describe a scholarly person.
 *
 * @WebformElement(
 *   id = "webform_metadata_scholarly_agent",
 *   label = @Translation("Multi LoD Source Scholarly Agent Items"),
 *   description = @Translation("Provides a form element to reconciliate Agents against Multiple Sources of Scholarly Agents."),
 *   category = @Translation("Composite elements"),
 *   multiline = TRUE,
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 * )
 */
class WebformScholarlyAgent extends WebformCompositeBase {


  protected function defineDefaultBaseProperties() {
    return [
        'vocab_personal_name' => '',
        'rdftype_personal_name' => '',
        'vocab_family_name' => '',
        'rdftype_family_name' => '',
        'vocab_corporate_name' => '',
        'rdftype_corporate_name' => '',
        'agent_type' => '',
        'role_type' => '',
        'name_label' => '',
        'name_uri' => '',
        'role_label' => '',
        'role_uri' => '',
        'identifier_orcid' => '',
        'affiliation' => '',
        'affiliation_url' => '',
        'graduation_year' => '',
        'local_role' => '',
        'local_affiliation' => '',
        'vocab_affiliation' =>  '',
        'rdftype_affiliation' => '',
        'agent_type_options' =>  [],
      ] + parent::defineDefaultBaseProperties();
  }

  public function getDefaultProperties() {
    $properties = parent::getDefaultProperties() +
      [
        'vocab_personal_name' => '',
        'rdftype_personal_name' => '',
        'vocab_family_name' => '',
        'rdftype_family_name' => '',
        'vocab_corporate_name' => '',
        'rdftype_corporate_name' => '',
        'role_type' => '',
        'name_label' => 'name_label',
        'name_uri' => 'name_uri',
        'agent_type' => 'agent_type',
        'role_label' => 'role_label',
        'role_uri' => 'role_uri',
        'identifier_orcid' => 'identifier_orcid',
        'affiliation' => 'affiliation',
        'affiliation_url' => 'affiliation_url',
        'graduation_year' => 'graduation_year',
        'local_role' => 'local_role',
        'local_affiliation' => 'local_affiliation',
        'vocab_affiliation' =>  '',
        'rdftype_affiliation' => '',
        'agent_type_options' =>  ['personal'],
      ];

    unset($properties['multiple__header']);
    return $properties;
  }



  public function prepare(
    array &$element,
    WebformSubmissionInterface $webform_submission = NULL
  ) {

    // @TODO explore this method to act on submitted data v/s element behavior
  }

  /**
   * Set multiple element wrapper.
   *
   * @param array $element
   *   An element.
   */
  protected function prepareMultipleWrapper(array &$element) {
    // We do not need a Multi Wrapper custom call because
    // By unsetting $properties['multiple__header'] we gain control
    // Over our original Element class again and
    // \Drupal\webform_strawberryfield\Element\WebformMultiAgent::processWebformComposite
    // Is called even in multiple scenario cases
    parent::prepareMultipleWrapper($element);
  }


  /**
   * {@inheritdoc}
   */
  public function getPluginLabel() {
    return $this->elementManager->isExcluded('webform_metadata_scholarly_agent') ? $this->t('Multi LoD Source Scholarly Agent Items') : parent::getPluginLabel();
  }

  /**
   * {@inheritdoc}
   */
  protected function formatHtmlItemValue(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    return $this->formatTextItemValue($element, $webform_submission, $options);
  }

  /**
   * {@inheritdoc}
   */
  protected function formatTextItemValue(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $values = $this->getValue($element, $webform_submission, $options);
    $lines = [];
    foreach( $values as $value) {
      if (!empty($value)) {
        $lines[] = $value;
      }
    }
    return $lines;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['composite']['agent_type_options'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t("Allowed Agent Types."),
      '#options' => [
        'corporate' => 'Corporate',
        'personal' => 'Personal',
        'family' => 'Family'
      ],
      '#default_value' => 'personal',
      '#required' => TRUE,
    ];


    $form['composite']['agent_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Sub element Key name (field) that will hold the Agent Type, defaults to 'agent_type'"),
    ];
    $form['composite']['name_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Sub element Key name (field) that will hold the Name label, defaults to 'name_label'"),
    ];

    $form['composite']['name_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Sub element Key name (field) that will hold the URI of the autocompleted Name, defaults to 'name_uri'"),
    ];

    $form['composite']['role_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Sub element Key name (field) that will hold the Role label, defaults to 'role_label'"),
    ];

    $form['composite']['role_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Sub element Key name (field) that will hold the URI of the autocompleted Role, defaults to 'role_uri'"),
    ];

    $form['composite']['identifier_orcid'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Sub element Key name (field) that will hold the an ORCID, defaults to 'identifier_orcid'"),
    ];

    $form['composite']['affiliation'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Sub element Key name (field) that will hold the the affiliation, defaults to 'affiliation'"),
    ];

    $form['composite']['affiliation_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Sub element Key name (field) that will hold the the affiliation URL if using LoD, defaults to 'affiliation_url'"),
    ];

    $form['composite']['graduation_year'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Sub element Key name (field) that will hold the the Graduation Year, defaults to 'graduation_year'"),
    ];

    $form['composite']['local_role'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Sub element Key name (field) that will hold a Local Role, defaults to 'local_role'"),
      '#description' => $this->t('this subelement provides a default set of options based on <a href="https://datacite-metadata-schema.readthedocs.io/en/4.5/appendices/appendix-1/contributorType">Data Cite Contributors</a>. To customize and/or allow other settings please create a Webform Options Configuration with a machine name that starts with <em>local_role</em>. Multiple are allowed. This only applies if the element type is set to <em>Select</em>(default). Doing so will effectively disable the internal defaults.')
    ];


    $form['composite']['vocab_personal_name'] = [
      '#type' => 'select',
      '#options' => [
        'names' => 'LC Name Authority File (LCNAF)',
        'rdftype' => 'Filter Suggest results by RDF Type',
      ],
      '#title' => $this->t("What LoC Autocomplete Source Provider to use for Personal Names."),
      '#description' => $this->t('See <a href="http://id.loc.gov">Linked Data Service</a>. If the link is to an Authority at http://id.loc.gov/authorities/names then the value to use there is <em>names</em>'),
    ];
    $form['composite']['rdftype_personal_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t("What RDF type to use as filter for Personal Names"),
      '#description' => $this->t('See <a href="http://id.loc.gov/ontologies/madsrdf/v1.html">Use one of the Classes listed here</a>. Defaults to <em>FullName</em>'),
      '#default_value' => 'FullName',
      '#states' => [
        'visible' => [
          ':input[name="properties[vocab_personal_name]"]' => ['value' => 'rdftype'],
        ],
      ],
    ];
    // PLEASE NEVER FORGET!!!
    // If the value saved is the default
    // as in  \Drupal\webform_strawberryfield\Plugin\WebformElement\WebformMultiAgent::getDefaultProperties
    // then it is actually not saved!
    // Which really is so silly...
    // So we set defaults to empty
    // Or getting them on the actual element implies reinitializing the webform
    // Element Not good.
    $form['composite']['vocab_corporate_name'] = [
      '#type' => 'select',
      '#options' => [
        'names' => 'LC Name Authority File (LCNAF)',
        'rdftype' => 'Filter Suggest results by RDF Type',
      ],
      '#title' => $this->t("What LoC Autocomplete Source Provider to use for Corporate Names."),
      '#description' => $this->t('See <a href="http://id.loc.gov">Linked Data Service</a>. If the link is to an Authority at http://id.loc.gov/authorities/names then the value to use there is <em>names</em>'),
    ];
    $form['composite']['rdftype_corporate_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t("What RDF type to use as filter for Corporate Names."),
      '#description' => $this->t('See <a href="http://id.loc.gov/ontologies/madsrdf/v1.html">Use one of the Classes listed here</a>. Defaults to <em>CorporateName</em>'),
      '#default_value' => 'CorporateName',
      '#states' => [
        'visible' => [
          ':input[name="properties[vocab_corporate_name]"]' => ['value' => 'rdftype'],
        ],
      ],
    ];

    $form['composite']['vocab_family_name'] = [
      '#type' => 'select',
      '#options' => [
        'names' => 'LC Name Authority File (LCNAF)',
        'rdftype' => 'Filter Suggest results by RDF Type',
      ],
      '#title' => $this->t("What LoC Autocomplete Source Provider to use for Family Names."),
      '#description' => $this->t('See <a href="http://id.loc.gov">Linked Data Service</a>. If the link is to an Authority at http://id.loc.gov/authorities/names then the value to use there is <em>names</em>'),
    ];
    $form['composite']['rdftype_family_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t("What RDF type to use as filter for Family Names."),
      '#description' => $this->t('See <a href="http://id.loc.gov/ontologies/madsrdf/v1.html">Use one of the Classes listed here</a>. Defaults to <em>FamilyName</em>'),
      '#default_value' => 'FamilyName',
      '#states' => [
        'visible' => [
          ':input[name="properties[vocab_family_name]"]' => ['value' => 'rdftype'],
        ],
      ],
    ];
    $form['composite']['role_type'] = [
      '#title' => $this->t("What Source to use for Role Definition"),
      '#type' => 'select',
      '#options' => [
        'loc' => 'LC Relators Vocabulary',
        'wikidata' => 'Unfiltered Wikidata',
      ],
      '#description' => $this->t('What source is to be used for Role assignment to Agents'),
      '#default_value' => 'loc',
    ];

    $form['composite']['vocab_affiliation'] = [
      '#type' => 'select',
      '#options' => [
        'names' => 'LC Name Authority File (LCNAF)',
        'rdftype' => 'Filter Suggest results by RDF Type',
      ],
      '#title' => $this->t("What LoC Autocomplete Source Provider to use for the affiliation."),
      '#description' => $this->t('See <a href="http://id.loc.gov">Linked Data Service</a>. If the link is to an Authority at http://id.loc.gov/authorities/names then the value to use there is <em>names</em>'),
    ];
    $form['composite']['rdftype_affiliation'] = [
      '#type' => 'textfield',
      '#title' => $this->t("What RDF type to use as filter for Affilition names"),
      '#description' => $this->t('See <a href="http://id.loc.gov/ontologies/madsrdf/v1.html">Use one of the Classes listed here</a>. Defaults to <em>CorporateName</em>'),
      '#default_value' => 'CorporateName',
      '#states' => [
        'visible' => [
          ':input[name="properties[vocab_affiliation]"]' => ['value' => 'rdftype'],
        ],
      ],
    ];

    //$form['composite']['#local_role__options'] => static::LOCAL_ROLE_OPTIONS

    return $form;
  }

}
