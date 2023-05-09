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
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'Webform LoD Options' element.
 *
 * @WebformElement(
 *   id = "webform_metadata_options",
 *   label = @Translation("Webform Options LoD suggest"),
 *   description = @Translation("Provides a form element autocomplete labels/urls(values) from Webform Options."),
 *   category = @Translation("Composite elements"),
 *   multiline = TRUE,
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 * )
 */
class WebformLoDfromOptions extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  protected function defineDefaultProperties() {
    $properties = [
        // Autocomplete settings.
        'autocomplete_items' => [],
        'autocomplete_limit' => 10,
        'autocomplete_match' => 3,
        'autocomplete_match_operator' => 'CONTAINS',
      ] + parent::defineDefaultProperties()
      + $this->defineDefaultMultipleProperties();
    // Remove autocomplete property which is not applicable to
    // this autocomplete element.
    unset($properties['autocomplete']);
    return $properties;
  }



  public function prepare(
    array &$element,
    WebformSubmissionInterface $webform_submission = NULL
  ) {
    parent::prepare($element, $webform_submission);

    if (isset($element['#webform_key'])) {
      $element['#autocomplete_route_name'] = 'webform_strawberryfield.element.autocomplete';
      $element['#autocomplete_route_parameters'] = [
        'webform' => $webform_submission->getWebform()->id(),
        'key' => $element['#webform_key'],
      ];
    }
  }

  /**
   * Set multiple element wrapper.
   *
   * @param array $element
   *   An element.
   */
  protected function prepareMultipleWrapper(array &$element) {
    $autocomplete_route =  $element['#autocomplete_route_name'];
    $autocomplete_route_params = $element['#autocomplete_route_parameters'];
    parent::prepareMultipleWrapper($element);

    if (isset($element['#element']['#webform_composite_elements']['label'])) {
      $element['#element']['#webform_composite_elements']['label']['#autocomplete_route_name'] = $autocomplete_route;
      $element['#element']['#webform_composite_elements']['label']['#autocomplete_route_parameters'] = $autocomplete_route_params;
    }

    // For some reason i can not understand, when multiples are using
    // Tables, the #webform_composite_elements -> 'label' is not used...
    if (isset($element["#multiple__header"]) && $element["#multiple__header"] == true) {
      $element['#element']['label']['#autocomplete_route_parameters'] = $autocomplete_route_params;
    }
  }


  /**
   * {@inheritdoc}
   */
  public function getPluginLabel() {
    return $this->elementManager->isExcluded('webform_metadata_options') ? $this->t('LoD Webform Options') : parent::getPluginLabel();
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
    $form['autocomplete'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Autocomplete settings'),
    ];
    $form['autocomplete']['autocomplete_items'] = [
      '#type' => 'webform_element_options',
      '#custom__type' => 'webform_multiple',
      '#title' => $this->t('Autocomplete values'),
    ];
    $form['autocomplete']['autocomplete_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Autocomplete limit'),
      '#description' => $this->t("The maximum number of matches to be displayed."),
      '#min' => 1,
    ];
    $form['autocomplete']['autocomplete_match'] = [
      '#type' => 'number',
      '#title' => $this->t('Autocomplete minimum number of characters'),
      '#description' => $this->t('The minimum number of characters a user must type before a search is performed.'),
      '#min' => 1,
    ];
    $form['autocomplete']['autocomplete_match_operator'] = [
      '#type' => 'radios',
      '#title' => $this->t('Autocomplete matching operator'),
      '#description' => $this->t('Select the method used to collect autocomplete suggestions.'),
      '#options' => [
        'STARTS_WITH' => $this->t('Starts with'),
        'CONTAINS' => $this->t('Contains'),
      ],
    ];
    return $form;
  }

}
