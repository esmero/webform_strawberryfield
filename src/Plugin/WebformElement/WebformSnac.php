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
 *   id = "webform_metadata_snac",
 *   label = @Translation("SNAC Constellation Linked Open Data"),
 *   description = @Translation("Provides a form element to reconciliate against Snac Entity Type LoD Sources."),
 *   category = @Translation("Composite elements"),
 *   multiline = TRUE,
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 * )
 */
class WebformSnac extends WebformCompositeBase {


  protected function defineDefaultBaseProperties() {
    return [
      'vocab' => 'Constellation',
      'rdftype' => 'thing',
    ] + parent::defineDefaultBaseProperties();
  }

  public function getDefaultProperties() {
    $properties = parent::getDefaultProperties() + [
        'vocab' => 'Constellation',
        'rdftype' => 'thing',
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
    $vocab = 'Constellation';
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
          'auth_type' => 'snac',
          'vocab' => $vocab,
          'rdftype' => $rdftype,
          'count' => 10
        ];
    }
    elseif (isset($element['#webform_multiple']) && $element['#webform_multiple'] == FALSE && isset($element['#webform_composite_elements']['label'])) {
      $element['#webform_composite_elements']['label']["#autocomplete_route_parameters"] =
        [
          'auth_type' => 'snac',
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
          'auth_type' => 'snac',
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
    return $this->elementManager->isExcluded('webform_metadata_snac') ? $this->t('SNAC Constellation Terms') : parent::getPluginLabel();
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
        'Constellation' => 'All Constellation Entity Types',
        'rdftype' => 'By specific Constellation Entity Types',
      ],
      '#title' => $this->t("What SNAC query type to use."),
      '#description' => $this->t('Specific Entity Types can be: person, corporateBody or family'),
    ];
    // Not sure if this has a sub authority and how that works/if suggest
    $form['composite']['rdftype'] = [
      '#type' => 'select',
      '#options' => [
        'person' => 'person',
        'corporateBody' => 'corporateBody',
        'family' => 'family',
      ],
      '#title' => $this->t("What SNAC Entity type to use as filter"),
      '#description' => $this->t('Can be one of: person, corporateBody or family'),
      '#default_value' => 'person',
      '#states' => [
        'visible' => [
          ':input[name="properties[vocab]"]' => ['value' => 'rdftype'],
        ],
      ],
    ];

    return $form;
  }

}
