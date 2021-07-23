<?php

namespace Drupal\webform_strawberryfield\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url as UrlGenerator;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Plugin\WebformElement\WebformLocationBase;

/**
 * Provides an 'location' element using Geocomplete.
 *
 * @WebformElement(
 *   id = "webform_metadata_nominatim",
 *   label = @Translation("Location GEOJSON (Nominatim)"),
 *   description = @Translation("Provides a form element to collect valid location information (address, longitude, latitude, geolocation) using Nominatim/Openstreetmap open API."),
 *   category = @Translation("Composite elements"),
 *   multiline = TRUE,
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 * )
 */
class WebformNominatim extends WebformLocationBase {

  /**
   * {@inheritdoc}
   */
  public function getPluginLabel() {
    return $this->elementManager->isExcluded('webform_location_nominatim') ? $this->t('Nominatim GEOJSON') : parent::getPluginLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultProperties() {
    return parent::getDefaultProperties() + [
        'geolocation' => FALSE,
        'hidden' => FALSE,
        'map' => FALSE,
      ];
  }

  /**
   * {@inheritdoc}
   */
  protected function formatHtmlItem(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $value = $this->getValue($element, $webform_submission, $options);

    // Return empty value.
    if (empty($value) || empty(array_filter($value))) {
      return '';
    }

    $format = $this->getItemFormat($element);
    if ($format == 'map') {

   $location = $value['location'];
   $center = urlencode($value['location']);
      // To build an OpenStreetmap link we need, e.g
      // "osm_type": "way",
      // "osm_id": 270859746,
      // So depending on the type a valid URL would be https://www.openstreetmap.org/way/270859746
      // or /relation/id
      // or /node/id
      // @see https://wiki.openstreetmap.org/wiki/Persistent_Place_Identifier#Element.27s_OSM_ID
     // $image_map_uri = "https://maps.googleapis.com/maps/api/staticmap?zoom=14&size=600x338&markers=color:red%7C$location&key=$key&center=$center";
      //$openstreetmap_url = UrlGenerator::fromUri('https://www.openstreetmap.org/'.);

      return [
        'location' => [
          '#type' => 'link',
          '#title' => $value['value'],
          '#url' => 'https://www.openstreetmap.org',
          '#suffix' => '<br />',
        ],
        'map' => [
          '#type' => 'link',
          '#url' => 'https://www.openstreetmap.org',
        ],
      ];
    }
    else {
      return parent::formatHtmlItem($element, $webform_submission, $options);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getItemFormats() {
    return parent::getItemFormats() + [
        'map' => $this->t('Map'),
      ];
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['composite']['map'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display map'),
      '#description' => $this->t('Display a map for entered location.'),
      '#return_value' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="properties[hidden]"]' => ['checked' => FALSE],
        ],
      ],
    ];


    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preview() {
    return parent::preview() + [
        '#map' => TRUE,
        '#geolocation' => TRUE,
        '#format' => 'map',
      ];
  }

  /**
   * {@inheritdoc}
   */
  public function getTestValues(array $element, WebformInterface $webform, array $options = []) {
    return [
      ['value' => 'The White House, 1600 Pennsylvania Ave NW, Washington, DC 20500, USA'],
      ['value' => 'London SW1A 1AA, United Kingdom'],
      ['value' => 'Moscow, Russia, 10307'],
    ];
  }

}
