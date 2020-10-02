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
 *   id = "webform_metadata_loc",
 *   label = @Translation("LoC Linked Open Data"),
 *   description = @Translation("Provides a form element to reconciliate against LoC Headings and similar LoD Sources."),
 *   category = @Translation("Composite elements"),
 *   multiline = TRUE,
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 * )
 */
class WebformLoC extends WebformCompositeBase {


  protected function defineDefaultBaseProperties() {
    return [
      'vocab' => 'subjects',
      'rdftype' => 'FullName',
    ];
  }

  public function getDefaultProperties() {
    $properties = parent::getDefaultProperties() + [
        'vocab' => 'subjects',
        'rdftype' => 'FullName',
      ] + parent::defineDefaultProperties()
      + $this->defineDefaultBaseProperties();

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

    parent::prepareMultipleWrapper($element);

    // Finally!
    // This is the last chance we have to affect the render array
    // This is where the original element type is also
    // swapped by webform_multiple
    // breaking all our #process callbacks.
    $vocab = 'subjects';
    $rdftype = 'thing';
    $vocab = $this->getElementProperty($element, 'vocab');
    $vocab = $vocab ?:  $this->getDefaultProperty($vocab);
    if ($vocab == 'rdftype') {
      $rdftype = trim($this->getElementProperty($element, 'rdftype'));
    }

    $rdftype = $rdftype ?: $this->getDefaultProperty($rdftype);
    // This seems to have been an old Webform module variation
    // Keeping it here until sure its not gone for good
    if (isset($element['#element']['#webform_composite_elements']['label'])) {
      $element['#element']['#webform_composite_elements']['label']["#autocomplete_route_parameters"] =
        [
          'auth_type' => 'loc',
          'vocab' => $vocab,
          'rdftype' => $rdftype,
          'count' => 10
        ];
    }
    // For some reason i can not understand, when multiples are using
    // Tables, the #webform_composite_elements -> 'label' is not used...
    if (isset($element["#multiple__header"]) && $element["#multiple__header"] == true) {
      $element['#element']['label']["#autocomplete_route_parameters"] =
        [
          'auth_type' => 'loc',
          'vocab' => $vocab,
          'rdftype' => $rdftype,
          'count' => 10
        ];
    }
  }


  /**
   * {@inheritdoc}
   */
  public function getPluginLabel() {
    return $this->elementManager->isExcluded('webform_metadata_loc') ? $this->t('Subject Headings') : parent::getPluginLabel();
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
    if (!empty($value['uri'])) {
      $lines[] = $value['uri'];
    }

    if (!empty($value['label'])) {
      $lines[] = $value['label'];
    }
    return $lines;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    //@NOTE    'classification' => 'classification(LCCS)', is not working
    // Not sure if this has a sub authority and how that works/if suggest
    $form['composite']['vocab'] = [
      '#type' => 'select',
      '#options' => [
        'subjects' => 'subjects(LCSH)',
        'names' => 'LC Name Authority File (LCNAF)',
        'genreForms' => 'LC Genre/Form Terms (LCGFT',
        'graphicMaterials' => 'Thesaurus of Graphic Materials (TGN)',
        'geographicAreas' => 'MARC List for Geographic Areas',
        'rdftype' => 'Filter Suggest results by RDF Type',
      ],
      '#title' => $this->t("What LoC Autocomplete Source Provider to use."),
      '#description' => $this->t('See <a href="http://id.loc.gov">Linked Data Service</a>. If the link is to an Authority is http://id.loc.gov/authorities/names then the value to use there is <em>names</em>'),
    ];
    // Not sure if this has a sub authority and how that works/if suggest
    $form['composite']['rdftype'] = [
      '#type' => 'textfield',
      '#title' => $this->t("What RDF type to use as filter"),
      '#description' => $this->t('See <a href="http://id.loc.gov/ontologies/madsrdf/v1.html">Use one of the Classes listed here</a>. Defaults to <em>FullName</em>'),
      '#default_value' => 'FullName',
      '#states' => [
        'visible' => [
          ':input[name="properties[vocab]"]' => ['value' => 'rdftype'],
        ],
      ],
    ];

    return $form;
  }

}
