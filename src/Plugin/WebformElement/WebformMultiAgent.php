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
 * Provides an 'LoC Heading' element.
 *
 * @WebformElement(
 *   id = "webform_metadata_multiagent",
 *   label = @Translation("Multi LoD Source Agent Items"),
 *   description = @Translation("Provides a form element to reconciliate Agents against Multiple Sources of Agents."),
 *   category = @Translation("Composite elements"),
 *   multiline = TRUE,
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 * )
 */
class WebformMultiAgent extends WebformCompositeBase {


  protected function defineDefaultBaseProperties() {
    return [
      'vocab_personal_name' => '',
      'rdftype_personal_name' => '',
      'vocab_family_name' => '',
      'rdftype_family_name' => '',
      'vocab_corporate_name' => '',
      'rdftype_corporate_name' => '',
      'role_type' => '',
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
    // By unsetting $properties['multiple__header'] we gain controll
    // Over our original Element class again and
    // \Drupal\webform_strawberryfield\Element\WebformMultiAgent::processWebformComposite
    // Is called even in multiple scenario cases
    parent::prepareMultipleWrapper($element);
  }


  /**
   * {@inheritdoc}
   */
  public function getPluginLabel() {
    return $this->elementManager->isExcluded('webform_metadata_multiagent') ? $this->t('Multi LoD Source Agent Items') : parent::getPluginLabel();
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
    $value = $this->getValue($element, $webform_submission, $options);

    $lines = [];
    if (!empty($value['agent_type'])) {
      $lines[] = $value['agent_type'];
    }
    if (!empty($value['role_uri'])) {
      $lines[] = $value['role_uri'];
    }

    if (!empty($value['role_label'])) {
      $lines[] = $value['role_label'];
    }

    if (!empty($value['agent_uri'])) {
      $lines[] = $value['role_uri'];
    }

    if (!empty($value['agent_label'])) {
      $lines[] = $value['role_label'];
    }
    // Agent type can be 'Personal Name or Corporate Name'
    if (!empty($value['agent_type'])) {
      $lines[] = $value['agent_type'];
    }
    return $lines;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

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
    // the  it is actually not saved!
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

    return $form;
  }

}
