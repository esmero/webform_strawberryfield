<?php

namespace Drupal\webform_strawberryfield\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Element\WebformCompositeBase;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\SettingsCommand;
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
    $info['#theme'] = 'webform_metadata_panoramatour';
    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public static function getCompositeElements(array $element) {
    $elements = [];
    $class = '\Drupal\webform_strawberryfield\Element\WebformPanoramaTour';
    //@TODO all this settings need to be exposed to the Webform element.

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

    // Just in case someone wants to trick us
    // This is already disabled on the Config Form for the Element
    unset($element['#multiple']);

    $element = parent::processWebformComposite(
      $element,
      $form_state,
      $complete_form
    );


    $element_name = $element['#name'];

    $all_scenes_key = $element['#name'] . '-allscenes';
    $all_scenes = $form_state->get($all_scenes_key);

    // If no initial 'scene' value, use first key of the allmighty
    // Full array.
    $sceneid = NULL;

    if (!$form_state->getValue([$element['#name'], 'scene']) && !empty($all_scenes)) {
      $scene = reset($all_scenes);
      $sceneid = $scene['scene'];

    } else {

      $sceneid = $form_state->getValue([$element['#name'], 'scene']);
      $form_state->setValue([$element_name, 'scene'],$sceneid);
    }

    // Fetch saved/existing hotspots and transform them into StdClass Objects
    $hotspot_list = [];


    foreach ($all_scenes as $scene) {
      // Fetching from full array
      error_log('fetching from full array');
      if ($scene['scene'] == $sceneid) {
        $hotspot_list = $scene['hotspots'];
      }
    }

    // We need this button to validate. important
    // NEVER add '#limit_validation_errors' => [],
    $element['scene'] = [
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


    $element['select_button'] = [
      '#title' => 'Select Scene',
      '#type' => 'submit',
      '#value' => t('Select Scene'),
      '#name' => $element['#name'] . '_select_button',
      '#submit' => [[static::class, 'selectSceneSubmit']],
      '#ajax' => [
        'callback' => [static::class, 'selectSceneCallBack'],
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
    // Some changes here. If we have multiple values we need a new flag
    // For the current selected index in the Scene list.
    //dpm($form_state->getValues());




    if ($sceneid) {
      $nodeid = $sceneid;

      if ($form_state->getValue([$element['#name'], 'hotspots_temp', 'ado'])) {

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
      //@TODO we need viewmode to be configurable!
      //@TODO We could also generate a view mode on the fly.

      $viewmode = 'digital_object_with_pannellum_panorama_';
      $node = \Drupal::entityTypeManager()->getStorage('node')->load(
        $nodeid
      );
      $errors = [];


      if ($node) {
        // If we have multiple Scenes,deal with it.
        if (!empty($all_scenes) && is_array($all_scenes)) {
          //dpm($all_scenes);
          foreach($all_scenes as $key => $scene) {
            $all_scenes_nodeids[$key] = $scene['scene'];
          }
          $all_scene_nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($all_scenes_nodeids);
          foreach ($all_scene_nodes as $entity) {
            $options[$entity->id()] = $entity->label();
          }
          // If we have loaded values, replace autocomplete with this
          $element['scene'] =  [
          '#title' => t('Editing Scene'),
          '#type' => 'select',
          '#options' => $options,
          '#default_value' => $node->id(),
          '#ajax' => [
            'callback' => [static::class, 'changeSceneCallBack'],
            'event' => 'change',
          ],
          '#submit' => [[static::class, 'changeSceneSubmit']],
        ];
        }
        $element['hotspots_temp']['label'] = [
          '#title' => t('The label to display on mouse over'),
          '#type' => 'textfield',
          '#size' => '12',
          '#attributes' => [
            'data-drupal-loaded-node' => $nodeid,
            'data-drupal-hotspot-property' => 'label',
          ],
        ];
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
        $element['hotspots_temp']['hfov'] = [
          '#title' => t('hfov'),
          '#type' => 'textfield',
          '#size' => '6',
          '#attributes' => [
            'data-drupal-loaded-node' => $nodeid,
            'data-drupal-hotspot-property' => 'hfov',
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
        $element['hotspots_temp']['url'] = [
          '#title' => t('URL to open on Hotspot Click'),
          '#type' => 'url',
          '#size' => '12',
          '#attributes' => [
            'data-drupal-loaded-node' => $nodeid,
            'data-drupal-hotspot-property' => 'url',
          ],
         '#states' => [
          'visible' => [
            ':input[name="'.$element['#name'].'[hotspots_temp][type]"]' => array('value' => 'url'),
            ],
          ]
        ];
        //@TODO expose arguments to the Webform element config UI.
        $element['hotspots_temp']['ado'] = [
          '#type' => 'entity_autocomplete',
          '#title' => t('Select a Digital Object that will open on Hotspot Click'),
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
          '#states' => [
            'visible' => [
              ':input[name="'.$element['#name'].'[hotspots_temp][type]"]' => array('value' => 'ado'),
            ],
          ]
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
        ];


        $element['hotspots_temp']['set_sceneorientation'] = [
          '#type' => 'submit',
          '#value' => t('Set Initial Scene Orientation'),
          '#name' => $element['#name'] . '_setsceneorientation_button',
          '#submit' => [[static::class, 'setSceneOrientation']],
          '#ajax' => [
            'callback' => [static::class, 'setSceneOrientationCallBack'],
          ],
          '#button_type' => 'default',
          '#visible' => 'true',
          '#limit_validation_errors' => FALSE
        ];


        $element['hotspots_temp']['node'] = $nodeview;

        $element['hotspots_temp']['node']['#weight'] = 10;
        $element['hotspots_temp']['added_hotspots'] = [
          '#type' => 'details',
          '#attributes' => [
            'data-drupal-loaded-node-hotspot-table' => $nodeid
          ],
          '#weight' => 11,
        ];

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
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public static function changeSceneCallBack(
    array $form,
    FormStateInterface $form_state
  ) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue(
      $form,
      array_slice($button['#array_parents'], 0, -1)
    );
    error_log('changeSceneCallBack');
    $response = new AjaxResponse();
    $element_name = $element['#name'];
    $data_selector = $element['hotspots_temp']['#attributes']['data-webform_strawberryfield-selector'];
    $element['hotspots_temp']['#title'] = 'Hotspots processed via ajax for this Scene';


    // Now update the JS settings
    if ($form_state->getValue([$element_name, 'scene'])) {
      $all_scenes_key = $element_name . '-allscenes';
      $allscenes = $form_state->get($all_scenes_key);
      $current_scene = $form_state->getValue([$element_name, 'scene']);
      $existing_objects = [];
      foreach ($allscenes as $key => &$scene) {
        if ($scene['scene'] == $current_scene) {
          $existing_objects = $scene['hotspots'];
          break;
        }
      }

      $settingsclear = [
        'webform_strawberryfield' => [
          'WebformPanoramaTour' => [
            $element_name . '-hotspots' =>
              NULL
          ],
        ]
      ];
      $settings = [
        'webform_strawberryfield' => [
          'WebformPanoramaTour' => [
            $element_name . '-hotspots' =>
              $existing_objects
          ],
        ]
      ];
      error_log('attaching replacement Drupal settings for the viewer');
      error_log(print_r($settings,true));
      // Why twice? well because merge is deep merge. Gosh JS!
      // And merge = FALSE clears even my brain settings...
      $response->addCommand(new SettingsCommand($settingsclear, TRUE));
      $response->addCommand(new SettingsCommand($settings, TRUE));

    }
    // And now replace the container
    $response->addCommand(
      new ReplaceCommand(
        '[data-webform_strawberryfield-selector="' . $data_selector . '"]',
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
  public static function changeSceneSubmit(
    array &$form,
    FormStateInterface $form_state
  ) {
    $button = $form_state->getTriggeringElement();
    error_log('changeSceneSubmit');
    $input = $form_state->getUserInput();
    // Hack. No explanation.
    $main_element_parents = array_slice($button['#array_parents'], 0, -1);

    $top_element = NestedArray::getValue($form, $main_element_parents);
    error_log('triggering element name'.  $input['_triggering_element_name']);

    // Rebuild the form.
    $form_state->setRebuild(TRUE);
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
    $main_element_parents = array_slice($button['#array_parents'], 0, -1);

    $top_element = NestedArray::getValue($form, $main_element_parents);

    error_log('triggering element name'.  $input['_triggering_element_name']);

    if (isset($input['_triggering_element_name']) &&
      $input['_triggering_element_name'] == $top_element['#name'].'_addhotspot_button'
      && empty($form_state->get('hotspot_custom_errors'))
    ) {
      static::addHotspotSubmit(
        $form,
        $form_state
      );
    }
    elseif (isset($input['_triggering_element_name']) &&
      $input['_triggering_element_name'] == $top_element['#name'].'_setsceneorientation_button'
      && empty($form_state->get('hotspot_custom_errors'))
    ) {
      static::setSceneOrientation(
        $form,
        $form_state
      );
    }
    else {
      $current_scene = $form_state->getValue([$top_element['#name'], 'scene']);
      $alreadythere = FALSE;
      if ($current_scene) {
        $all_scenes_key = $top_element['#name'] . '-allscenes';
        $all_scenes = $form_state->get($all_scenes_key);
        foreach ($all_scenes as $scene) {
          if ($scene['scene'] == $current_scene) {
            $alreadythere = TRUE;
            break;
          }
        }
        if (!$alreadythere) {
          $all_scenes[] = [
            'scene' => $current_scene,
            'hotspots' => [],
          ];
        $form_state->set($all_scenes_key,$all_scenes);
        }
      }
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
    $hot_spot_values_parents = array_slice($button['#parents'], 0, -1);

    $element_name = $hot_spot_values_parents[0];
    if ($form_state->getValue([$element_name, 'scene'])) {
      $all_scenes_key = $element_name . '-allscenes';
      $allscenes = $form_state->get($all_scenes_key);
      $current_scene = $form_state->getValue([$element_name, 'scene']);
      $scene_key = 0;
      $existing_objects = [];
      error_log(print_r($form_state->getValues(), TRUE));
      foreach ($allscenes as $key => &$scene) {
        if ($scene['scene'] == $form_state->getValue(
            [$element_name, 'scene']
          )) {
          $scene_key = $key;
          $existing_objects = $scene['hotspots'];
          break;
        }
      }

      $hotspot = new \stdClass;

      $hotspot->pitch = $form_state->getValue(
        [$element_name, 'hotspots_temp', 'pitch']
      );
      $hotspot->yaw = $form_state->getValue(
        [$element_name, 'hotspots_temp', 'yaw']
      );
      $hotspot->type = $form_state->getValue(
        [$element_name, 'hotspots_temp', 'type']
      );
      $hotspot->text = $form_state->getValue(
        [$element_name, 'hotspots_temp', 'label']
      );

      $hotspot->id = $element_name . '_' . $current_scene . '_' .(count($existing_objects) + 1);
      if ($hotspot->type == 'url') {
        $hotspot->URL = $hotspot->url;
        $hotspot->type = 'info';
      }
      if ($hotspot->type == 'ado') {
        $nodeid = $form_state->getValue(
          [$element_name, 'hotspots_temp', 'ado']
        );
        // Now the fun part. Since this autocomplete is not part of the process
        // chain we never get the value transformed into id.
        // So. we do it directly
        $nodeid = EntityAutocomplete::extractEntityIdFromAutocompleteInput(
          $nodeid
        );
        $url = \Drupal\Core\Url::fromRoute(
          'entity.node.canonical',
          ['node' => $nodeid],
          []
        );
        $url = $url->toString();
        $hotspot->type = 'info';

        $hotspot->URL = $url;
      }
      if ($hotspot->type == 'text') {
        $hotspot->text = $hotspot->text;
        $hotspot->type = 'info';
      }


      $existing_objects[] = $hotspot;

      // @TODO make sure people don't add twice the same coordinates!

      // Push hotspots there

      $allscenes[$scene_key]['hotspots'] = $existing_objects;
      $form_state->set($all_scenes_key, $allscenes);
      error_log('done updating original array');

    }



    // This is strange but needed.
    // If we are creating a new  panorama, addhotspot submit button
    // uses the scene submit (because this is nested) and hidden on initialize
    // but if we start with data, its called directly.
    // So we have the setRebuild on the parent caller
    // \Drupal\webform_strawberryfield\Element\WebformPanoramaTour::selectSceneSubmit
    // and also here. all good
    $form_state->setRebuild(TRUE);
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
    $element_name = $element['#name'];
    $response = new AjaxResponse();
    $data_selector = $element['hotspots_temp']['added_hotspots']['#attributes']['data-drupal-loaded-node-hotspot-table'];
    $existing_object = [];
    if ($form_state->getValue([$element_name, 'scene'])) {
      $all_scenes_key = $element_name . '-allscenes';
      $allscenes = $form_state->get($all_scenes_key);
      $current_scene = $form_state->getValue([$element_name, 'scene']);
      $scene_key = 0;
      $existing_objects = [];
       foreach ($allscenes as $key => &$scene) {
        if ($scene['scene'] == $form_state->getValue(
            [$element_name, 'scene']
          )) {
          $scene_key = $key;
          $existing_objects = $scene['hotspots'];
        }
      }
    }
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

  /**
   * Submit Handler for adding a Hotspot.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function setSceneOrientation(
    array &$form,
    FormStateInterface $form_state
  ) {

    error_log('setSceneOrientation');
    $button = $form_state->getTriggeringElement();
    $hot_spot_values_parents = array_slice($button['#parents'], 0, -1);

    $element_name = $hot_spot_values_parents[0];
    if ($form_state->getValue([$element_name, 'scene'])) {
      $all_scenes_key = $element_name . '-allscenes';
      $allscenes = $form_state->get($all_scenes_key);
      $current_scene = $form_state->getValue([$element_name, 'scene']);
      $scene_key = 0;
      $existing_objects = [];
      error_log(print_r($form_state->getValues(), TRUE));
      foreach ($allscenes as $key => &$scene) {
        if ($scene['scene'] == $form_state->getValue(
            [$element_name, 'scene']
          )) {
          $scene_key = $key;
          break;
        }
      }

      $allscenes[$scene_key]['hfov'] =$form_state->getValue(
        [$element_name, 'hotspots_temp', 'hfov']);
      $allscenes[$scene_key]['pitch'] =$form_state->getValue(
        [$element_name, 'hotspots_temp', 'pitch']);
      $allscenes[$scene_key]['yaw'] =$form_state->getValue(
        [$element_name, 'hotspots_temp', 'yaw']);

      $form_state->set($all_scenes_key, $allscenes);
      error_log('done updating original array');

    }



    // This is strange but needed.
    // If we are creating a new  panorama, addhotspot submit button
    // uses the scene submit (because this is nested) and hidden on initialize
    // but if we start with data, its called directly.
    // So we have the setRebuild on the parent caller
    // \Drupal\webform_strawberryfield\Element\WebformPanoramaTour::selectSceneSubmit
    // and also here. all good
    $form_state->setRebuild(TRUE);
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public static function setSceneOrientationCallBack(
    array $form,
    FormStateInterface $form_state
  ) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue(
      $form,
      array_slice($button['#array_parents'], 0, -1)
    );
    error_log('setSceneOrientationCallBack');
    $response = new AjaxResponse();
    $element_name = $element['#name'];
    $data_selector = $element['hotspots_temp']['#attributes']['data-webform_strawberryfield-selector'];
    $element['hotspots_temp']['#title'] = 'Hotspots processed via ajax for this Scene';

    // And now replace the container
    $response->addCommand(
      new ReplaceCommand(
        '[data-webform_strawberryfield-selector="' . $data_selector . '"]',
        $element['hotspots_temp']
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

      if ($element_plugin->isInput($composite_element)) {
        $default_value[$composite_key] = '';
      }
    }

    // Used for multiscenes
    $all_scenes_key = $element['#name'] . '-allscenes';
    $current_scene_key = $element['#name'] . '-currentscene';

    if ($form_state->get($all_scenes_key)) {
      // Merging with saved $all_scenes
      error_log('Merge default values with whatever is that we got before');
      $default_value = array_merge($form_state->get($all_scenes_key), $default_value);
      $default_value['scene'] = $form_state->get($current_scene_key);
    }

    if ($input !== FALSE && $input !== NULL) {
      error_log('we have input');
      error_log(print_r($input,true));
    }



    if (($input === FALSE) && !$form_state->get($all_scenes_key)) {
      // OK, first load or webform auto processing, no input.

      error_log('valueCallback input empty and no all scenes yet');
      if (empty($element['#default_value']) || !is_array(
          $element['#default_value']
        )) {
        $element['#default_value'] = [];
      } else {

      // Check if Default value is an array of scenes or just a single scene
        if (isset($element['#default_value']['scene'])) {
          error_log('Got a single scene');
          // cast into an array
          $element['#default_value'] = [$element['#default_value']];
          // This will be our current scene
          $element['#default_value']['scene'] = $element['#default_value'][0]['scene'];
          // Means single scene.
          $form_state->set($all_scenes_key, $element['#default_value']);
          $form_state->set($current_scene_key, $element['#default_value']['scene']);

        } elseif (isset($element['#default_value'][0]['scene'])) {
          error_log('multi scene');
          $form_state->set($all_scenes_key, $element['#default_value']);
          $element['#default_value']['scene'] = $element['#default_value'][0]['scene'];
          $form_state->set($current_scene_key, $element['#default_value']['scene']);
          // Multi scene!
        }

        foreach ($element['#default_value'] as $scene) {
          // $scene could be a god damn button which is a translateable
          if (is_array($scene) && $scene['scene'] == $element['#default_value']['scene']) {
            $element['#default_value']['hotspots'] = $scene['hotspots'];
            break;
          }
        }
      }

      return $element['#default_value'] + $default_value;
    }

    $to_return = (is_array($input)) ? $input + $default_value : $default_value;
    error_log('what is in the element default_value before valuecallback return');
    error_log(print_r(array_keys($element['#default_value']),true));
    error_log('return of valueCallback');
    error_log(print_r($to_return,true));

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
    error_log('validateHotSpotItems');

    if ($triggering = $form_state->getTriggeringElement()) {
      if (reset($triggering['#parents']) == $element['#name']) {
        error_log('triggered by our Tour builder');
        // Means it was something inside the button
      }
      else {
        error_log('Clear our redundant values');
        $form_state->unsetValue([$element['#name'], 'scene']);
        $form_state->unsetValue([$element['#name'], 'hotspots']);
        $form_state->unsetValue([$element['#name'], 'hotspots_temp']);
      }
    }
  }

}
