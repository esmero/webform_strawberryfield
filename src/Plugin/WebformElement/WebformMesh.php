<?php
/**
 * Created by PhpStorm.
 * User: dpino
 * Date: 12/2/18
 * Time: 5:17 PM
 */

namespace Drupal\webform_strawberryfield\Plugin\WebformElement;

use Drupal\Core\Site\Settings;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Plugin\WebformElement\WebformCompositeBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Provides an 'LoC Heading' element.
 *
 * @WebformElement(
 *   id = "webform_metadata_mesh",
 *   label = @Translation("PubMed MeSH Suggest"),
 *   description = @Translation("Provides a form element to reconciliate against Medical Subject Headings (MeSH) RDF API."),
 *   category = @Translation("Composite elements"),
 *   multiline = TRUE,
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 * )
 */
class WebformMesh extends WebformCompositeBase {


  protected function defineDefaultBaseProperties() {
    return [
        'vocab' => 'descriptor',
        'matchtype' => 'startswith',
      ] + parent::defineDefaultBaseProperties();
  }

  public function getDefaultProperties() {
    $properties = parent::getDefaultProperties() + [
        'vocab' => 'descriptor',
        'matchtype' => 'startswith',
      ];

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
    $matchtype = trim($this->getElementProperty($element, 'matchtype'));
    $matchtype = $matchtype?: $this->getDefaultProperty('matchtype');
    $vocab = $this->getElementProperty($element, 'vocab');
    $vocab = $vocab ?:  $this->getDefaultProperty('vocab');
    if (isset($element['#element']['#webform_composite_elements']['label'])) {
      $element['#element']['#webform_composite_elements']['label']["#autocomplete_route_parameters"] =
        [
          'auth_type' => 'mesh',
          'vocab' => $vocab,
          'rdftype' => $matchtype,
          'count' => 10
        ];
    }
    elseif (isset($element['#webform_multiple']) && $element['#webform_multiple'] == FALSE && isset($element['#webform_composite_elements']['label'])) {
      $element['#webform_composite_elements']['label']["#autocomplete_route_parameters"] =
        [
          'auth_type' => 'mesh',
          'vocab' => $vocab,
          'rdftype' => $matchtype,
          'count' => 10
        ];
    }
    // For some reason i can not understand, when multiples are using
    // Tables, the #webform_composite_elements -> 'label' is not used...
    if (isset($element["#multiple__header"]) && $element["#multiple__header"] == true) {
      $element['#element']['label']["#autocomplete_route_parameters"] =
        [
          'auth_type' => 'mesh',
          'vocab' => $vocab,
          'rdftype' => $matchtype,
          'matchtype' => 'startswith',
          'count' => 10
        ];
    }
  }


  /**
   * {@inheritdoc}
   */
  public function getPluginLabel() {
    return $this->elementManager->isExcluded('webform_metadata_mesh') ? $this->t('Medical Subject Heading MeSH') : parent::getPluginLabel();
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

    $form['composite']['vocab'] = [
      '#type' => 'select',
      '#options' => [
        'descriptor' => 'Medical Subject Headings Descriptor (Subject Headings) API',
        'term' => 'Medical Subject Headings Term API',
      ],
      '#title' => $this->t("What MeSH Autocomplete API Type to use."),
      '#description' =>  $this->t('See <a href="https://id.nlm.nih.gov/mesh/swagger/ui#/">MeSH Subject HeadingsAPI</a>'),

    ];
    $form['composite']['matchtype'] = [
      '#type' => 'select',
      '#options' => [
        'exact' => 'Exact, based on recommended Label. Will give you a single result or none.',
        'startswith' => 'Label Starts with.',
        'contains' => 'Label Contains.',
      ],
      '#title' => $this->t("What type of Match Query to perform"),
      '#description' => $this->t('All match types return the same number of results. Exact matches only against the prefered label of the query.'),
    ];
    return $form;
  }

}
