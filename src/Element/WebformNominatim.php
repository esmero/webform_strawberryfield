<?php

namespace Drupal\webform_strawberryfield\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Element\WebformLocationBase;
use Drupal\webform_strawberryfield\Controller\NominatimController;
use Drupal\Component\Utility\NestedArray;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;

/**
 * Provides a webform element for a Nominatim element.
 *
 * @FormElement("webform_metadata_nominatim")
 */
class WebformNominatim extends WebformLocationBase {


  /**
   * @var string
   */
  protected static $name = 'nominatim';

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();
    // Add our theme.
    $info['#theme'] = 'webform_metadata_nominatim';
    return $info;
  }

  /**
   * Helper function to get elements we don't want to persist.
   *
   * @return array
   */
  public static function getExcludedAttributes() {
    return [
      'feature',
      'nominatim',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getLocationAttributes() {
    return [
      'lat' => t('Latitude'),
      'lng' => t('Longitude'),
      'category' => t('Place Category'),
      'display_name' => t('display_name'),
      'osm_id' => t('Open Street Map ID'),
      'osm_type' => t('Open Street Map ID'),
      'neighbourhood' => t('Neighbourhood'),
      'locality' => t('Locality'),
      'city' => t('City'),
      'county' => t('County'),
      'state_district' => t('State District'),
      'state' => t('State'),
      'postcode' => t('Postal Code/ZIP'),
      'country' => t('Country'),
      'country_code' => t('Country Code'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getCompositeElements(array $element) {

    $elements = [];

    $elements['value'] = [
      '#type' => 'textfield',
      '#title' => t('Location, Place or Address'),
      '#attributes' => [
        'class' => ['webform-location-' . static::$name],
      ],
    ];

    $attributes = static::getLocationAttributes();
    foreach ($attributes as $name => $title) {
      $elements[$name] = [
        '#title' => $title,
        '#type' => 'textfield',
        '#error_no_message' => TRUE,
        '#attributes' => [
          'data-webform-location-' . static::$name . '-attribute' => $name,
        ],
      ];
    }

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function processWebformComposite(
    &$element,
    FormStateInterface $form_state,
    &$complete_form
  ) {

    $element = parent::processWebformComposite(
      $element,
      $form_state,
      $complete_form
    );

    // This key contains the nominati reponse for this $element
    $my_geosjonkey = $element['#name'] . '-geojson';

    $element['nominatim'] = [
      '#title' => 'Reconciliate against Openstreetmaps Nominatim Service',
      '#type' => 'submit',
      '#value' => t('Search OpenStreet Maps'),
      '#name' => $element['#name'] . '_reconciliate_button',
      '#submit' => [[get_called_class(), 'nominatimFetchSubmit']],
      '#ajax' => [
        'callback' => [get_called_class(), 'nominatimCallBack'],
      ],
      '#button_type' => 'default',
      '#visible' => 'true',
      '#limit_validation_errors' => [],
    ];

    $element['feature'] = [
      '#type' => 'container',
      '#title' => '',
      '#attributes' => [
        'data-webform-location-feature-attribute' => 'feature',
      ],
      '#description' => 'https://operations.osmfoundation.org/policies/nominatim/',
    ];
    $triggering_element = $form_state->getTriggeringElement();
    $trigger_name = isset($triggering_element) && isset($triggering_element['#name'])? $triggering_element['#name'] : NULL;
    
    // Only act on our own submits
    if ($trigger_name == $element['#name'] . '_select_button' ||
     $trigger_name == $element['#name'] . '_reconciliate_button') {

      if ($form_state->isRebuilding() && !empty(
        $form_state->get(
          $my_geosjonkey
        )
        )) {
        // Json will be UTF-8 correctly encoded/decoded!
        $nominatim_features = json_decode(
          $form_state->get($my_geosjonkey),
          FALSE
        );

        $table_header = [
          'display_name' => t('Display Name'),
          'category' => t('Category'),
        ];
        $table_options = [];
        foreach($nominatim_features as $key => $feature) {

          $table_options[$key+1] = [
            'display_name' => $feature->label,
            'category' => $feature->value->properties->category,
          ];
        }
        $element['feature']['table'] = [
          '#title' => t('OpenStreet Map Nominatim Best Matches'),
          '#type' => 'tableselect',
          '#default_value' => $form_state->get($my_geosjonkey.'-table-option'),
          '#header' => $table_header,
          '#options' => $table_options,
          '#empty' => t('Sorry, no OpenStreet Map Match'),
          '#js_select' => FALSE,
          '#multiple' => FALSE,
          '#ajax' => [
            'trigger_as' => ['name' => $element['#name'] . '_select_button'],
            'callback' => [get_called_class(), 'nominatimSelectCallBack'],
          ],

        ];
      }
   }
    // If we don't render this one of creation, Ajax never works.
      $element['feature']['select'] = [
        '#title' => 'Select the best match',
        '#type' => 'submit',
        '#value' => t('Select Best Match'),
        '#name' => $element['#name'] . '_select_button',
        '#submit' => [[get_called_class(), 'nominatimTableSubmit']],
        '#ajax' => [
          'callback' => [get_called_class(), 'nominatimSelectCallBack'],
        ],
        '#button_type' => 'default',
        '#limit_validation_errors' => [],
        '#visible' => 'true',
        '#attributes' => ['class' => ['js-hide']],
      ];

    return $element;
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public static function nominatimCallBack(
    array $form,
    FormStateInterface $form_state
  ) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue(
      $form,
      array_slice($button['#array_parents'], 0, -1)
    );

    $response = new AjaxResponse();
    $data_selector = $element['feature']['#attributes']['data-drupal-selector'];
    $response->addCommand(
      new ReplaceCommand(
        '[data-drupal-selector="' . $data_selector . '"]',
        $element['feature']
      )
    );
    return $response;

  }


  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public static function nominatimSelectCallBack(
    array $form,
    FormStateInterface $form_state
  ) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue(
      $form,
      array_slice($button['#array_parents'], 0, -2)
    );
    $response = new AjaxResponse();
    $data_selector = $element['#attributes']['data-drupal-selector'];
    $response->addCommand(
      new ReplaceCommand(
        '[data-drupal-selector="' . $data_selector . '"]',
        $element
      )
    );
    return $response;
  }

  /**
   * Submit Hanlder for the Nominatim reconciliation call.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function nominatimFetchSubmit(
    array &$form,
    FormStateInterface $form_state
  ) {

    $button = $form_state->getTriggeringElement();
    $main_element_parents = array_slice($button['#array_parents'], 0, -1);

    // Fetch main element so we can use that webform_id key to store the full output of nominatim
    $top_element = NestedArray::getValue($form, $main_element_parents);
    $my_geosjonkey = $top_element['#name'] . '-geojson';

    $element2 = &NestedArray::getValue(
      $form,
      array_slice($button['#array_parents'], 0, -1)
    );

    $user_input = trim($element2['#value']['value']);
    // Only call nominatim if we have more than 3 characters
    // @TODO force the field to have webform limit count.
    if (strlen($user_input) >= 3) {
      // Lets get a Nominatim Response from the Controller
      $controller_nominatim = new NominatimController(\Drupal::httpClient());
      $current_laguage = \Drupal::languageManager()
        ->getCurrentLanguage()
        ->getId();
      // tricky? Our request has the arguments + the method takes the same
      // Just for compatibility since route collection manager
      // Does that automatically on a real public request.
      $controller_url = Url::fromRoute(
        'webform_strawberryfield.nominatim',
        ['api_type' => 'search', 'count' => 5, 'lang' => $current_laguage],
        ['query' => ['q' => $user_input]]
      );

      $json_response = $controller_nominatim->handleRequest(
        Request::create($controller_url->toString(), 'GET'),
        'search',
        5,
        $current_laguage

      );
      $nomitanim_response_encoded = $json_response->isSuccessful()
        ? $json_response->getContent() : [];
      if (!$json_response->isEmpty()) {
        $form_state->set($my_geosjonkey, $nomitanim_response_encoded);
        $nomitanim_response_decoded = json_decode(
          $nomitanim_response_encoded,
          TRUE
        );
      }
    }
    // Reset current selection, if any. That way we can deselect wrongly made options
    // By researching.
    $form_state->set($my_geosjonkey.'-table-option', NULL);
    $userinput = $form_state->getUserInput();
    // Only way to get that tableselect form to rebuild completely
    unset($userinput[$top_element['#name']]['feature']['table']);
    $form_state->setUserInput($userinput);

    // Rebuild the form.
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for the Table select
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function nominatimTableSubmit(
    array &$form,
    FormStateInterface $form_state
  ) {
    $button = $form_state->getTriggeringElement();
    // This button is two levels up
    $main_element_parents = array_slice($button['#array_parents'], 0, -2);
    // Fetch main element so we can use that webform_id key to store the full output of nominatim
    $top_element = NestedArray::getValue($form, $main_element_parents);

    $my_geosjonkey = $top_element['#name'] . '-geojson';
    // @TODO move this long address processing to its own method
      if (!empty($form_state->get($my_geosjonkey))) {
        $nominatim_features = json_decode(
          $form_state->get($my_geosjonkey),
          FALSE
        );
        // Address can have many aliases, so we need to clean this up and match
        // to our more limited fields

        // For some reason i can not explain other than i'm hacking this
        // When passing the the submit to the _select button
        // The table value is only in the user input, not on the state!
        $table_key = array_slice($button['#parents'], 0, -1);
        $table_key[] = 'table';
        $selected_option =  NestedArray::getValue($form_state->getUserInput(), $table_key);
        // Lets learn from this. Value i added vas an integer, value i get is a string
        // Love you drupal...
        $selected_option = (int) $selected_option;
        // Means no selection but also its the first element!
        $selected_option = $selected_option > 0 ? $selected_option - 1 : 0;

        $address = $nominatim_features[$selected_option]->value->properties->address;

        foreach ($address as $properties => $value)
          switch ($properties) {
            case 'hamlet':
            case 'village':
            case 'locality':
            case 'croft' :
              $normalized_address['locality'] = $value;
              break;
            case 'city':
            case 'town':
            case 'municipality':
              $normalized_address['city'] = $value;
              break;
            case 'neighbourhood':
            case 'suburb':
            case 'city_district':
            case 'district':
            case 'quarter':
            case 'houses':
            case 'subdivision':
              $normalized_address['neighbourhood'] = $value;
              break;
            case 'county' :
            case 'local_administrative_area' :
            case 'county_code':
              $normalized_address['county'] = $value;
              break;
            case 'state_district':
              $normalized_address['state_district'] = $value;
              break;
            case 'state':
            case 'province':
            case 'state_code':
              $normalized_address['state'] = $value;
              break;
            case 'country':
              $normalized_address['country'] = $value;
              break;
            case 'country_code':
              $normalized_address['country_code'] = $value;
              break;
            case 'postcode':
              $normalized_address['postcode'] = $value;
              break;
          }

        // Take what we got from nominatim and put into our location keys.
        // Lat and Long are in http://en.wikipedia.org/wiki/en:WGS-84
        $values = [
          'value' => $nominatim_features[$selected_option]->label,
          'lat' => $nominatim_features[$selected_option]->value->geometry->coordinates[1],
          'lng' => $nominatim_features[$selected_option]->value->geometry->coordinates[0],
          'category' => $nominatim_features[$selected_option]->value->properties->category,
          'display_name' => $nominatim_features[$selected_option]->label,
          'osm_id' => $nominatim_features[$selected_option]->value->properties->osm_id,
          'osm_type' => $nominatim_features[$selected_option]->value->properties->osm_type,
          'neighbourhood' => isset($normalized_address['neighbourhood']) ? $normalized_address['neighbourhood'] : '',
          'locality' => isset($normalized_address['locality']) ? $normalized_address['locality'] : '',
          'city' => isset($normalized_address['city']) ? $normalized_address['city'] : '',
          'county' => isset($normalized_address['county']) ? $normalized_address['county'] : '',
          'state_district' => isset($normalized_address['state_district']) ? $normalized_address['state_district'] : '',
          'state' => isset($normalized_address['state']) ? $normalized_address['state'] : '',
          'postcode' => isset($normalized_address['postcode']) ? $normalized_address['postcode'] : '',
          'country' => isset($normalized_address['country']) ? $normalized_address['country'] : '',
          'country_code' => isset($normalized_address['country_code']) ? $normalized_address['country_code'] : '',
        ];
        $geojson_field = array_slice($button['#parents'], 0, -2);
        NestedArray::setValue(
          $form_state->getUserInput(),
          $geojson_field,
          $values
        );
        $form_state->setValueForElement($top_element, $values);
        // Table can be or not be when we finally deliver and no 'key' means
        // NestedArray:: will fail badly. No fear!
        // We will use a stub to mark which option was selected using the same
        // Naming convention as the $my_geosjonkey
        $form_state->set($my_geosjonkey.'-table-option', $selected_option +1);

      }
    // Rebuild the form.
    $form_state->setRebuild(TRUE);
  }




  /**
   * {@inheritdoc}
   */
  public static function valueCallback(
    &$element,
    $input,
    FormStateInterface $form_state
  ) {
    $to_return = parent::valueCallback($element, $input, $form_state);
    foreach (self::getExcludedAttributes() as $to_exclude_composite_key) {
      unset($to_return[$to_exclude_composite_key]);
      unset($element['#default_value'][$to_exclude_composite_key]);
    }
    return $to_return;
  }


  /**
   * {@inheritdoc}
  */
  public static function validateWebformLocation(
    &$element,
    FormStateInterface $form_state,
    &$complete_form
  ) {
    $value = $element['#value'];
    foreach (self::getExcludedAttributes() as $to_exclude_composite_key) {
      unset($element['#value'][$to_exclude_composite_key]);
      unset($element['#default_value'][$to_exclude_composite_key]);
    }

    $has_access = (!isset($element['#access']) || $element['#access'] === TRUE);
    if ($has_access && !empty($element['#required']) && empty($value['lat']) && empty($value['long'])) {
      $t_args = [
        '@title' => !empty($element['#title']) ? $element['#title'] : t(
          'Location'
        ),
      ];
      $form_state->setError(
        $element['value'],
        t('The @title is not valid.', $t_args)
      );
    }
    // Needed to remove our own Ajax generated and support Elements
    $form_state->setValueForElement($element, $element['#value']);
  }
}
