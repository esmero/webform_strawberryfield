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
 *   id = "webform_metadata_europeana",
 *   label = @Translation("Europeana Entity Suggest"),
 *   description = @Translation("Provides a form element to reconciliate against Europeana's Entity Suggest API."),
 *   category = @Translation("Composite elements"),
 *   multiline = TRUE,
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 * )
 */
class WebformEuropeana extends WebformCompositeBase {


  protected function defineDefaultBaseProperties() {
    return [
      'vocab' => 'agent',
      'rdftype' => 'thing',
    ] + parent::defineDefaultBaseProperties();
  }

  public function getDefaultProperties() {
    $properties = parent::getDefaultProperties() + [
        'vocab' => 'agent',
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
    $vocab = 'agent';
    $rdftype = 'thing';
    $vocab = $this->getElementProperty($element, 'vocab');
    $vocab = $vocab ?:  $this->getDefaultProperty($vocab);
   if (isset($element['#element']['#webform_composite_elements']['label'])) {
      $element['#element']['#webform_composite_elements']['label']["#autocomplete_route_parameters"] =
        [
          'auth_type' => 'europeana',
          'vocab' => $vocab,
          'rdftype' => $rdftype,
          'count' => 10
        ];
    }
   elseif (isset($element['#webform_multiple']) && $element['#webform_multiple'] == FALSE && isset($element['#webform_composite_elements']['label'])) {
     $element['#webform_composite_elements']['label']["#autocomplete_route_parameters"] =
       [
         'auth_type' => 'europeana',
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
          'auth_type' => 'europeana',
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
    return $this->elementManager->isExcluded('webform_metadata_europeana') ? $this->t('Europeana Entity') : parent::getPluginLabel();
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
    $apikey = Settings::get('webform_strawberryfield.europeana_entity_apikey');
    if ($apikey) {
      $description = $this->t('See <a href="https://pro.europeana.eu/page/entity#suggest">Europeana Entity API</a>. Good! We found your Europeana Entity API key.');
    } else {
      $description = $this->t('See <a href="https://pro.europeana.eu/page/entity#suggest">Europeana Entity API</a>. Warning: This API requires an apikey. Please ask your Drupal admin to set it for you in <em>settings.php</em>');
    }

    $form['composite']['vocab'] = [
      '#type' => 'select',
      '#options' => [
        'agent' => 'Europeana Agents',
        'concept' => 'Europeana Concepts',
        'place' => 'Europeana Places',
      ],
      '#title' => $this->t("What Europeana Autocomplete Entity Type to use."),
      '#description' => $description,
    ];
    return $form;
  }

}
