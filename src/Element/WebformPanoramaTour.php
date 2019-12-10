<?php

namespace Drupal\webform_strawberryfield\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Element\WebformCompositeBase;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\webform_strawberryfield\Ajax\AddHotSpotCommand;
use Drupal\webform\Utility\WebformElementHelper;
use Drupal\Core\Entity\Element\EntityAutocomplete;


/**
 * Provides a webform element for a Getty Vocab element.
 *
 * @FormElement("webform_metadata_panoramatour")
 */
class WebformPanoramaTour extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    //@TODO add an extra option to define auth_type.
    //@TODO expose as an select option inside \Drupal\webform_strawberryfield\Plugin\WebformElement\WebformGetty
    $info = parent::getInfo();
    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public static function getCompositeElements(array $element) {
    $elements = [];
    $class = '\Drupal\webform_strawberryfield\Element\WebformPanoramaTour';
    //@TODO all this settings need to be exposed to the Webform element.
    $elements['scene'] = [
      '#type' => 'entity_autocomplete',
      '#title' => t('Select a Scene'),
      '#target_type' => 'node',
      '#selection_handler' => 'solr_views',
      '#selection_settings' => [
        'view' => [
          'view_name' => 'ado_selection_by_type',
          'display_name' => 'entity_reference_solr_2',
          'arguments' => ['Panorama']
        ],
      ],
    ];
    $element['hotspots'] = [
      '#type' => 'value'
    ];
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
    $element_name = $element['#name'];
    // Fetch saved/existing hotspots and transform them into StdClass Objects
    $hotspot_list = [];
    $hotspot_list = $form_state->getValue(['panorama_tour','hotspots']);
    if (!empty($hotspot_list) && empty($form_state->get(
        $element_name . '-hotspots'
      ))) {
      $hotspot_list = array_map(function ($item)  {
        return (object) $item;
      }, $hotspot_list);
      // Now set our internal temp storage for hotspots
      $form_state->set($element_name . '-hotspots', $hotspot_list);
      // We really don't know nor should assume what HTML id the loaded scene has
      // But we do know who is the parent
      // @TODO next iteration, we need this per Scene ID!
    }

    // We need this button to validate. important
    // NEVER add '#limit_validation_errors' => [],
    $element['select_button'] = [
      '#title' => 'Select Scene',
      '#type' => 'submit',
      '#value' => t('Select Scene'),
      '#name' => $element['#name'] . '_select_button',
      '#submit' => [[get_called_class(), 'selectSceneSubmit']],
      '#ajax' => [
        'callback' => [get_called_class(), 'selectSceneCallBack'],
      ],
      '#button_type' => 'default',
      '#visible' => 'true',
    ];
    $element['hotspots_temp'] = [
      '#type' => 'fieldset',
      '#title' => 'Hotspots for this Scene',
      '#attributes' => [
        'data-drupal-selector' => $element['#name'] . '-hotspots',
        'data-webform_strawberryfield-selector' => $element['#name'] . '-hotspots',
      ],
      '#attached' => [
        'library' => [
          'format_strawberryfield/pannellum',
          'webform_strawberryfield/scenebuilder_pannellum_strawberry',
          'core/drupal.dialog.ajax',
        ],
        'drupalSettings' => [
          'webform_strawberryfield' => [
            'WebformPanoramaTour' => [
              $element['#name'] . '-hotspots' =>
                $hotspot_list
            ],
          ]
        ]
      ]
    ];

    // Note. The $element['hotspots_temp'] 'data-drupal-selector' gets
    // Modified during rendering to 'edit-' . $element['#name'] . '-hotspots-temp'


    // @TODO Modal will need to be enabled on the formatter too.

    if ($form_state->getValue(['panorama_tour', 'scene'])) {
      $nodeid = $form_state->getValue(['panorama_tour', 'scene']);

      if ($form_state->getValue(['panorama_tour', 'hotspots_temp', 'ado'])) {

        $othernodeid = EntityAutocomplete::extractEntityIdFromAutocompleteInput($nodeid);
        // We have to do the same when saving the actual Hotspot.

        $element['hotspots_temp']['entities'] = [
          '#type' => 'value',
          '#default_value' => $othernodeid,
        ];
      }
      $vb = \Drupal::entityTypeManager()->getViewBuilder(
        'node'
      ); // Drupal\node\NodeViewBuilder
      //@TODO we need to viewmode this to be configurable!
      //@TODO We could also generate a view mode on the fly.

      $viewmode = 'digital_object_with_pannellum_panorama_';
      $node = \Drupal::entityTypeManager()->getStorage('node')->load(
        $nodeid
      );
      $errors = [];


      if ($node) {
        $nodeview = $vb->view($node, $viewmode);
        $element['hotspots_temp']['yaw'] = [
          '#title' => t('Hotspot Yaw'),
          '#type' => 'textfield',
          '#size' => '6',
          '#attributes' => [
            'data-drupal-loaded-node' => $nodeid,
            'data-drupal-hotspot-property' => 'yaw',
          ],
        ];
        $element['hotspots_temp']['pitch'] = [
          '#title' => t('Hotspot Pitch'),
          '#type' => 'textfield',
          '#size' => '6',
          '#attributes' => [
            'data-drupal-loaded-node' => $nodeid,
            'data-drupal-hotspot-property' => 'pitch',
          ],
        ];

        $element['hotspots_temp']['type'] = [
          '#title' => t('Hotspot Type'),
          '#type' => 'select',
          '#options' => [
            'text' => 'Text',
            'url' => 'An External URL',
            'ado' => 'Another Digital Object',
            'scene' => 'Another Panorama Scene', // Still missing
          ],
          '#attributes' => [
            'data-drupal-loaded-node' => $nodeid,
            'data-drupal-hotspot-property' => 'type',
          ],
        ];
        $element['hotspots_temp']['label'] = [
          '#title' => t('Label'),
          '#type' => 'textfield',
          '#size' => '12',
          '#attributes' => [
            'data-drupal-loaded-node' => $nodeid,
            'data-drupal-hotspot-property' => 'label',
          ],
        ];
        $element['hotspots_temp']['url'] = [
          '#title' => t('url'),
          '#description' => t('Only applies to Hotspots of type "An External URL"'),
          '#type' => 'url',
          '#size' => '12',
          '#attributes' => [
            'data-drupal-loaded-node' => $nodeid,
            'data-drupal-hotspot-property' => 'url',
          ],
         /* '#states' => [
          'visible' => [
            ':input[name="hotspots_temp[type]"]' => array('value' => 'url'),
            ],
          ] */
        ];

        $element['hotspots_temp']['ado'] = [
          '#type' => 'entity_autocomplete',
          '#title' => t('Select a Digital Object'),
          '#description' => t('Only applies to Hotspots of type "Another Digital Object"'),
          '#target_type' => 'node',
          '#selection_handler' => 'solr_views',
          '#selection_settings' => [
            'view' => [
              'view_name' => 'ado_selection_by_type',
              'display_name' => 'entity_reference_solr_2',
              'arguments' => ['Image'],
            ],
          ],
          '#attributes' => [
            'data-drupal-loaded-node' => $nodeid,
            'data-drupal-hotspot-property' => 'ado',
          ],
        ];


        // To make sure menu variables are passed
        // we limit validation errors to those elements
        $limit = array_merge($element['#parents'], ['hotspots_temp']);

        $element['hotspots_temp']['add_hotspot'] = [
          '#type' => 'submit',
          '#value' => t('Add Hotspot'),
          '#name' => $element['#name'] . '_addhotspot_button',
          '#submit' => [[static::class, 'addHotspotSubmit']],
          '#ajax' => [
            'callback' => [static::class, 'addHotSpotCallBack'],
          ],
          '#button_type' => 'default',
          '#visible' => 'true',
          '#limit_validation_errors' => FALSE
          //'#limit_validation_errors' => [$limit]
        ];
        // Make sure the top element gets our validation
        // Since all this subelements are really not triggering that
        // Lets check if hotspots had errors

        /*if (!empty($form_state->get('hotspot_custom_errors'))) {
          $hotspot_errors = $form_state->get('hotspot_custom_errors');

          foreach ($element['hotspots_temp'] as $key => &$field)

            if (array_key_exists($key, $hotspot_errors)) {
            error_log('found errors');
              $field['#attributes']['class'] = ['form-item--error-message', 'alert', 'alert-danger', 'alert-sm'];
              $field['#prefix'] = $hotspot_errors[$key];
            }
        }*/

        $element['hotspots_temp']['node'] = $nodeview;

        $element['hotspots_temp']['node']['#weight'] = 10;
        $element['hotspots_temp']['added_hotspots'] = [
          '#type' => 'details',
          '#attributes' => [
            'data-drupal-loaded-node-hotspot-table' => $nodeid
          ],
          '#weight' => 11,
        ];

        // Get the hotspot submitted data
        if ($form_state->isRebuilding() && !empty(
          $form_state->get(
            $element_name . '-hotspots'
          )
          )) {
          // Json will be UTF-8 correctly encoded/decoded!
          $hotspot_list = $form_state->get($element_name . '-hotspots');
        }


        if (!empty($hotspot_list)) {

          $table_header = [
            'coordinates' => t('Hotspot Coordinates'),
            'type' => t('Type'),
            'label' => t('Label'),
            'operations' => t('Operations'),
          ];

          foreach ($hotspot_list as $key => $hotspot) {
            if (is_array($hotspot)) {
              $hotspot = (object) $hotspot;
            }
            // Key will be in element['#name'].'_'.count($hotspot_list)+1;
            $table_options[$key] = [
              'coordinates' => $hotspot->yaw . "," . $hotspot->pitch,
              'type' => $hotspot->type,
              'label' =>  isset($hotspot->text) ? $hotspot->text : t('no name'),
              'operations' => [
                'data' => [
                  '#type' => 'submit',
                  '#value' => t('Delete'),
                ],
              ]
            ];
          }


          $element['hotspots_temp']['added_hotspots'] = [
            '#prefix'=> '<div>',
            '#suffix'=> '</div>',
            '#title' => t('Hotspots in this scene'),
            '#type' => 'table',
            //'#default_value' => [],
            '#name' => $element['#name'] . '_added_hotspots',
            '#header' => $table_header,
            '#rows' => $table_options,
            '#empty' => t('No Hotspots yet for this Scene'),
            '#weight' => 11,
            '#attributes' => [
              'data-drupal-loaded-node-hotspot-table' => $nodeid
            ],
          ];
        }
      }
    }
    $element['#element_validate'][] = [static::class, 'validateHotSpotItems'];

    return $element;
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public static function selectSceneCallBack(
    array $form,
    FormStateInterface $form_state
  ) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue(
      $form,
      array_slice($button['#array_parents'], 0, -1)
    );

    $response = new AjaxResponse();
    $data_selector = $element['hotspots_temp']['#attributes']['data-drupal-selector'];
    $element['hotspots_temp']['#title'] = 'Hotspots processed via ajax for this Scene';
    $response->addCommand(
      new ReplaceCommand(
        '[data-drupal-selector="' . $data_selector . '"]',
        $element['hotspots_temp']
      )
    );
    return $response;
  }

  /**
   * Submit Handler for the Select Scene Submit call.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function selectSceneSubmit(
    array &$form,
    FormStateInterface $form_state
  ) {
    $button = $form_state->getTriggeringElement();
    error_log('selectSceneSubmit');
    $input = $form_state->getUserInput();
    // Hack. No explanation.

    //error_log(var_export($form_state->getValue(['panorama_tour','scene']),true));
    $main_element_parents = array_slice($button['#array_parents'], 0, -1);

    $top_element = NestedArray::getValue($form, $main_element_parents);
    $my_hotspots = $top_element['#name'] . '-hotspots';


    if (isset($input['_triggering_element_name']) &&
      $input['_triggering_element_name'] == 'panorama_tour_addhotspot_button'
      && empty($form_state->get('hotspot_custom_errors'))
    ) {
      static::addHotspotSubmit(
        $form,
        $form_state
      );
    }

    // Rebuild the form.
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit Handler for adding a Hotspot.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function addHotspotSubmit(
    array &$form,
    FormStateInterface $form_state
  ) {
    error_log('addHotspotSubmit');
    $button = $form_state->getTriggeringElement();
    $main_element_parents = array_slice($button['#array_parents'], 0, -1);

    $top_element = NestedArray::getValue($form, $main_element_parents);

    $my_hotspots_key = $top_element['#name'] . '-hotspots';

    $existing_objects = $form_state->get($my_hotspots_key) ? $form_state->get(
      $my_hotspots_key
    ) : [];

    $hotspot = new \stdClass;

    $hotspot->pitch = $form_state->getValue(
      [$top_element['#name'], 'hotspots_temp', 'pitch']
    );
    $hotspot->yaw = $form_state->getValue(
      [$top_element['#name'], 'hotspots_temp', 'yaw']
    );
    $hotspot->type = $form_state->getValue(
      [$top_element['#name'], 'hotspots_temp', 'type']
    );
    $hotspot->text = $form_state->getValue(
      [$top_element['#name'], 'hotspots_temp', 'label']
    );

    $hotspot->id = $top_element['#name'] . '_' . (count($existing_objects) + 1);
    if ($hotspot->type == 'url') {
      $hotspot->URL = $hotspot->url;
      $hotspot->type = 'info';
    }
    if ($hotspot->type == 'ado') {
      $nodeid =  $form_state->getValue(
        ['panorama_tour', 'hotspots_temp', 'ado']
      );
      // Now the fun part. Since this autocomplete is not part of the process
      // chain we never get the value transformed into id.
      // So. we do it directly
      $nodeid = EntityAutocomplete::extractEntityIdFromAutocompleteInput($nodeid);
      $url = \Drupal\Core\Url::fromRoute('entity.node.canonical', ['node' => $nodeid],[]);
      $url = $url->toString();
      $hotspot->type = 'info';

      $hotspot->URL = $url;
    }
    if ($hotspot->type == 'text') {
      $hotspot->text = $hotspot->text;
      $hotspot->type = 'info';
    }
    error_log(var_export($hotspot,true));

    $existing_objects[$hotspot->id] = $hotspot;
    error_log($my_hotspots_key);
    // @TODO make sure people don't add twice the same coordinates!
    $form_state->set($my_hotspots_key, $existing_objects);

    return;

  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public static function addHotSpotCallBack(
    array $form,
    FormStateInterface $form_state
  ) {
    $button = $form_state->getTriggeringElement();

    $element = NestedArray::getValue(
      $form,
      array_slice($button['#array_parents'], 0, -2)
    );
    error_log('addHotSpotCallBack');

    $response = new AjaxResponse();
    $data_selector = $element['hotspots_temp']['added_hotspots']['#attributes']['data-drupal-loaded-node-hotspot-table'];
    $my_hotspots_key = $element['#name'] . '-hotspots';


    $existing_objects = $form_state->get($my_hotspots_key) ? $form_state->get(
      $my_hotspots_key
    ) : [];
    if (count($existing_objects) > 0) {

      $data_selector2 = $element['hotspots_temp']['#attributes']['data-drupal-selector'];

      $response->addCommand(
        new AddHotSpotCommand(
          '[data-drupal-selector="' . $data_selector2 . '"]',
          end($existing_objects),
          'webform_strawberryfield_pannellum_editor_addHotSpot'
        )
      );
    }
    $response->addCommand(
      new ReplaceCommand(
        '[data-drupal-loaded-node-hotspot-table="' . $data_selector . '"]',
        $element['hotspots_temp']['added_hotspots']
      )
    );
    return $response;
  }

  public static function valueCallback(
    &$element,
    $input,
    FormStateInterface $form_state
  ) {
    error_log('valueCallback');
    /** @var \Drupal\webform\Plugin\WebformElementManagerInterface $element_manager */
    $element_manager = \Drupal::service('plugin.manager.webform.element');
    $composite_elements = static::getCompositeElements($element);
    $composite_elements = WebformElementHelper::getFlattened(
      $composite_elements
    );

    // Get default value for inputs.
    $default_value = [];
    foreach ($composite_elements as $composite_key => $composite_element) {
      $element_plugin = $element_manager->getElementInstance(
        $composite_element
      );

      error_log($composite_key);
      if ($element_plugin->isInput($composite_element)) {
        $default_value[$composite_key] = '';
      }
    }
    // We need to move our internal hotspot form state var into
    // the value field and back.
    $my_hotspots_key = $element['#name'] . '-hotspots';
    $existing_objects = $form_state->get($my_hotspots_key) ? (array) $form_state->get(
      $my_hotspots_key
    ) : [];
    $list_of_hotspots = array_values($existing_objects);
    foreach ($list_of_hotspots as $hotspot) {
      $default_value['hotspots'][] = (array) $hotspot;
    }

    if ($input === FALSE) {
      if (empty($element['#default_value']) || !is_array(
          $element['#default_value']
        )) {
        $element['#default_value'] = [];
      }
      return $element['#default_value'] + $default_value;
    }

    $to_return = (is_array($input)) ? $input + $default_value : $default_value;
    error_log('return of valueCallback');
    error_log(print_r($to_return,true));
    // Remove the hotspots_temp structure from our final value.

    return $to_return;

  }

  public static function validateWebformComposite(
    &$element,
    FormStateInterface $form_state,
    &$complete_form
  ) {
    error_log('validateWebformComposite');
    // What i learned. This god forgotten function
    // Destroys my submission values...
    // Since this is a composite and was never meant to have more than one button
    // triggering element can be wrong
    // Lets use the input and search of the actual element


    $trigger = $form_state->getTriggeringElement();
    // IMPORTANT: Must get values from the $form_states since sub-elements
    // may call $form_state->setValueForElement() via their validation hook.
    // @see \Drupal\webform\Element\WebformEmailConfirm::validateWebformEmailConfirm
    // @see \Drupal\webform\Element\WebformOtherBase::validateWebformOther
    $value = NestedArray::getValue(
      $form_state->getValues(),
      $element['#parents']
    );

    // Only validate composite elements that are visible.
    $has_access = (!isset($element['#access']) || $element['#access'] === TRUE);
    if ($has_access) {
      // Validate required composite elements.
      $composite_elements = static::getCompositeElements($element);
      $composite_elements = WebformElementHelper::getFlattened(
        $composite_elements
      );
      foreach ($composite_elements as $composite_key => $composite_element) {
        $is_required = !empty($element[$composite_key]['#required']);
        $is_empty = (isset($value[$composite_key]) && $value[$composite_key] === '');
        if ($is_required && $is_empty) {
          WebformElementHelper::setRequiredError(
            $element[$composite_key],
            $form_state
          );
        }
      }
    }
    error_log('validateWebformComposite');
    error_log(print_r($value,true));
    // Clear empty composites value.
    if (empty(array_filter($value))) {
      error_log('is empty');
      $element['#value'] = NULL;
      $form_state->setValueForElement($element, NULL);
    }
  }

  public static function validateHotSpotItems(
    &$element,
    FormStateInterface $form_state,
    &$complete_form
  ) {

    $input = $form_state->getUserInput();
    error_log('validateHotSpotItems');
    // Hack. No explanation.
    if (isset($input['_triggering_element_name']) && $input['_triggering_element_name'] == 'panorama_tour_addhotspot_button') {
      if (!is_numeric($form_state->getValue(
        [$element['#name'], 'hotspots_temp', 'yaw']))) {
        // This is needed because we are validating internal subelements during
        // Hotspot adding, but there is really no real form submit.
        // We want the form to keep on rebuild.
        $form_state->set('hotspot_custom_errors', ['yaw'=>t('Yaw needs to be numeric and not empty')]);
      }
      else {
        $form_state->set('hotspot_custom_errors', NULL);
      }
    }
  }

}
