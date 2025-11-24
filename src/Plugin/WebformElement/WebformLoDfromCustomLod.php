<?php

namespace Drupal\webform_strawberryfield\Plugin\WebformElement;

use Drupal\Core\Site\Settings;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Plugin\WebformElement\WebformCompositeBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'Webform LoD from Custom LoD Endpoint' element.
 *
 * @WebformElement(
 *   id = "webform_metadata_customlod",
 *   label = @Translation("Webform Custom LoD Endpoint suggest"),
 *   description = @Translation("Provides a form element autocomplete labels/urls(values) from Custom LoD Endpoint Entity/Plugins."),
 *   category = @Translation("Composite elements"),
 *   multiline = TRUE,
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 * )
 */
class WebformLoDfromCustomLod extends WebformSBFLoD {

  /**
   * {@inheritdoc}
   */
  protected function defineDefaultProperties() {
    $properties = [
        // Autocomplete settings.
        'custom_lod' => ''
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
      $element['#autocomplete_route_name'] = 'webform_strawberryfield.custom_lod';
      $element['#autocomplete_route_parameters'] = [
        'custom_lod_entity_id' => $element['#custom_lod'],
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
    elseif (isset($element['#webform_multiple']) && $element['#webform_multiple'] == FALSE && isset($element['#webform_composite_elements']['label'])) {
      // Not a multiple one. So assign the Autocomplete route directly to the composite children.
      $element['#webform_composite_elements']['label']['#autocomplete_route_name'] = $autocomplete_route;
      $element['#webform_composite_elements']['label']['#autocomplete_route_parameters'] = $autocomplete_route_params;
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
    $custom_lod_endpoints = $this->getCustomLoDEndpoints(TRUE);
    $form['composite']['custom_lod'] = [
      '#title' => $this->t("Which Custom LoD Endpoint to use"),
      '#type' => 'select',
      '#options' => $custom_lod_endpoints,
      '#empty_option' => 'Select an Active Custom LoD endpoint',
      '#description' => $this->t('What Custom LoD Endpoint is to be used'),
      '#required' => TRUE
    ];

    return $form;
  }

}
