<?php

namespace Drupal\webform_strawberryfield\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\webform\Element\WebformCompositeBase;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\webform_strawberryfield\Ajax\AddHotSpotCommand;
use Drupal\webform_strawberryfield\Ajax\RemoveHotSpotCommand;
use Drupal\webform\Utility\WebformElementHelper;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Ramsey\Uuid\Uuid;


/**
 * Provides a webform element for a Panorama Tour Builder with hotspots.
 *
 * @FormElement("webform_metadata_panoramatour")
 */
class WebformPanoramaTour extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    // @TODO allow user to set which View is used to populate Panoramas
    // Allow user to select which view mode is used to render inline panoramas
    $class = get_class($this);
    $info = [
      '#input' => TRUE,
      '#access' => TRUE,
      '#process' => [
        [$class, 'processWebformComposite'],
        [$class, 'processAjaxForm'],
      ],
      '#pre_render' => [
        [$class, 'preRenderWebformPanoramaTourElement'],
      ],
      '#title_display' => 'invisible',
      '#required' => FALSE,
      '#flexbox' => TRUE,
      '#theme' => 'webform_metadata_panoramatour',
    ];

    return $info;
  }


  /**
   * Render API callback: Hides display of the upload or remove controls.
   *
   * Upload controls are hidden when a file is already uploaded. Remove controls
   * are hidden when there is no file attached. Controls are hidden here instead
   * of in \Drupal\file\Element\ManagedFile::processManagedFile(), because
   * #access for these buttons depends on the managed_file element's #value. See
   * the documentation of \Drupal\Core\Form\FormBuilderInterface::doBuildForm()
   * for more detailed information about the relationship between #process,
   * #value, and #access.
   *
   * Because #access is set here, it affects display only and does not prevent
   * JavaScript or other untrusted code from submitting the form as though
   * access were enabled. The form processing functions for these elements
   * should not assume that the buttons can't be "clicked" just because they are
   * not displayed.
   *
   * @param array $element
   *
   * @return array
   * @see \Drupal\file\Element\ManagedFile::processManagedFile()
   * @see \Drupal\Core\Form\FormBuilderInterface::doBuildForm()
   */
  public static function preRenderWebformPanoramaTourElement(array $element) {
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function getCompositeElements(array $element) {

    $elements = [];
    $elements['allscenes'] = [
      '#type' => 'hidden',
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

    $element = parent::processWebformComposite($element, $form_state, $complete_form);

    // Just in case someone wants to trick us
    // This is already disabled on the Config Form for the Element
    unset($element['#multiple']);

    $element_name = $element['#name'];

    $all_scenes = [];

    $all_scenes_key = $element_name . '-allscenes';
    $all_scenes_from_form_state = !empty($form_state->getValue([$element['#name'],'allscenes'])) ? json_decode($form_state->getValue([$element['#name'],'allscenes']),TRUE) : [];

    $all_scenes_from_form_value = !empty($form_state->get($all_scenes_key)) ? $form_state->get($all_scenes_key) : [];


    // Basically. First try to get it from the defaults set by the ::valuecallback().
    // This will populate Form state value.
    // But once that happens, the #value elements does not survive.
    // So we store it in a form state variable. And keep setting it.
    $all_scenes = !empty($all_scenes_from_form_value) ? $all_scenes_from_form_value : $all_scenes_from_form_state;

    if (!empty($all_scenes)) {
      $form_state->set($all_scenes_key,$all_scenes );
    }

    // Double set it. One for the input, one for the form state.
    // Reason is we have a complex ::valuecallback() here
    // and when used in the widget a form inside a form
    // This reasonably deals with both needs.
    $element['allscenes']['#default_value'] = json_encode($all_scenes,true);
    $form_state->setValue([$element['#name'],'allscenes'],json_encode($all_scenes,TRUE));

    $currentscene = $form_state->getValue([$element_name, 'currentscene']);
    $sceneid = NULL;

    if (!$currentscene && !empty($all_scenes)) {
      $scene = reset($all_scenes);
      $sceneid = $scene['scene'];

    } else {
      $sceneid = $currentscene;
      $form_state->setValue([$element_name, 'scene'], $sceneid);
    }
    $hotspot_list = [];


    foreach ($all_scenes as $scene) {
      // Fetching from full array
      if (isset($scene['scene']) && $scene['scene'] == $sceneid) {
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
      '#limit_validation_errors' => [$element['#parents']],
    ];

    $element['hotspots'] = [
      '#type' => 'value'
    ];

    $element['select_button'] = [
      '#title' => 'Select Scene',
      '#type' => 'submit',
      '#value' => t('Add Scene'),
      '#name' => $element['#name'] . '_select_button',
      '#submit' => [[get_called_class(), 'selectSceneSubmit']],
      '#ajax' => [
        'callback' => [get_called_class(), 'selectSceneCallBack'],
      ],
      '#button_type' => 'default',
      '#visible' => 'true',
      '#limit_validation_errors' => [$element['#parents']],
    ];

    $element['hotspots_temp'] = [
      '#weight' => 4,
      '#type' => 'fieldset',
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
      ],
    ];

    // If we have a currently selected scene
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
      //$viewmode = 'digital_object_with_pannellum_panorama_';

      $node = \Drupal::entityTypeManager()->getStorage('node')->load(
        $nodeid
      );

      $all_scenes_nodeids = [];
      if ($node) {
        // Will contain all currently loaded scenes as an Select option array.
        $options = [];
        // If we have multiple Scenes,deal with it.
        if (!empty($all_scenes) && is_array($all_scenes)) {
          foreach($all_scenes as $key => $scene) {
            if (isset($scene['scene'])) {
              $all_scenes_nodeids[$key] = $scene['scene'];
            }
          }
          $all_scene_nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($all_scenes_nodeids);

          foreach ($all_scene_nodes as $entity) {
            $options[$entity->id()] = $entity->label();
          }

          $element['currentscene']['#weight'] = 2;
          $element['currentscene'] =  [
            '#title' => t('Editing Scene'),
            '#type' => 'select',
            '#options' => $options,
            '#default_value' => (string) $node->id(),
            '#ajax' => [
              'callback' => [get_called_class(), 'changeSceneCallBack'],
              'event' => 'change',
            ],
            '#submit' => [[get_called_class(), 'changeSceneSubmit']],
          ];
        }

        $nodeview = $vb->view($node);
        $element['hotspots_temp']['node'] = $nodeview;

        $element['hotspots_temp']['node']['#weight'] = -10;
        $element['hotspots_temp']['node']['#prefix'] = '<div class="row">';
        $element['hotspots_temp']['node']['#attributes']['class'] = ['col-8'];
        $element['hotspots_temp']['added_hotspots'] = [
          '#type' => 'details',
          '#attributes' => [
            'data-drupal-loaded-node-hotspot-table' => $nodeid,
            'class' => ['row'],
          ],
          '#weight' => 11,
        ];

        $element['hotspots_temp']['label'] = [
          '#prefix' => '<div class="col-4">',
          '#title' => t('The label to display on mouse over'),
          '#type' => 'textfield',
          '#size' => '12',
          '#attributes' => [
            'data-drupal-loaded-node' => $nodeid,
            'data-drupal-hotspot-property' => 'label',
          ],
        ];

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
            'scene' => 'Another Panorama Scene',
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
        $optionscenes = is_array($options) ? $options : [];
        // Remove from linkable scenes the current loaded one

        $element['hotspots_temp']['adoscene'] = [
          '#title' => t('Linkable Scenes'),
          '#type' => 'radios',
          "#empty_option" => t('- Select a loaded Scene -'),
          '#options' => $optionscenes,
          '#states' => [
            'visible' => [
              ':input[name="'.$element['#name'].'[hotspots_temp][type]"]' => array('value' => 'scene'),
            ],
          ],
          '#attributes' => [
            'data-drupal-loaded-node' => $nodeid,
            'data-drupal-hotspot-property' => 'type',
          ],
        ];

        $element['hotspots_temp']['adoscene'][$nodeid] = ['#disabled' => TRUE];

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

        $element['hotspots_temp']['add_hotspot'] = [
          '#prefix' => '<div class="btn-group-vertical" role="group" aria-label="Tour Builder actions">',
          '#type' => 'submit',
          '#value' => t('Add Hotspot'),
          '#name' => $element['#name'] . '_addhotspot_button',
          '#submit' => [[get_called_class(), 'addHotspotSubmit']],
          '#ajax' => [
            'callback' => [get_called_class(), 'addHotSpotCallBack'],
          ],
          '#button_type' => 'default',
          '#visible' => 'true',
          '#limit_validation_errors' => [$element['#parents']],
        ];


        $element['hotspots_temp']['set_sceneorientation'] = [
          '#type' => 'submit',
          '#value' => t('Set Initial Scene Orientation'),
          '#name' => $element['#name'] . '_setsceneorientation_button',
          '#submit' => [[get_called_class(), 'setSceneOrientation']],
          '#ajax' => [
            'callback' => [get_called_class(), 'setSceneOrientationCallBack'],
          ],
          '#button_type' => 'default',
          '#visible' => 'true',
          '#limit_validation_errors' => [$element['#parents']],
        ];

        $element['hotspots_temp']['delete_scene'] = [
          '#type' => 'submit',
          '#value' => t('Delete this Scene'),
          '#name' => $element['#name'] . '_deletescene_button',
          '#submit' => [[get_called_class(), 'deleteScene']],
          '#ajax' => [
            'callback' => [get_called_class(), 'deleteSceneCallBack'],
          ],
          '#button_type' => 'default',
          '#visible' => 'true',
          '#limit_validation_errors' => [$element['#parents']],
          '#attributes' => [
            'class' => ['btn-warning']
          ],
          '#suffix' => '</div></div></div>' // Closes the btn group, row and the col
        ];

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
          $table_options = [];
          $row = 0;
          foreach ($hotspot_list as $key => $hotspot) {
            $row++;
            if (is_array($hotspot)) {
              $hotspot = (object) $hotspot;
            }

            $delete_hot_spot_button = [
              '#hotspottodelete' => $hotspot->id,
              '#type' => 'submit',
              '#limit_validation_errors' => [
                array_merge($element['#parents'],['hotspots_temp','added_hotspots']),
                array_merge($element['#parents'],['currentscene']),
                array_merge($element['#parents'],['allscenes']),

              ],
              '#value' => t('Remove'),
              '#id' => $element['#name'].'-remove-hotspot-'.$hotspot->id,
              '#name' => $element['#name'].'-remove-hotspot-'.$hotspot->id,
              '#submit' => [[get_called_class(),'deleteHotSpot']],
              '#ajax' => [
                'callback' => [ get_called_class(), 'deleteHotSpotCallback'],
                'wrapper' => $element['#name'].'-hotspot-list-wrapper',
              ],
            ];
            $table_options[$row] = [
              'coordinates' => [
                '#type' => 'html_tag',
                '#tag' => 'p',
                '#value' => $hotspot->yaw . "," . $hotspot->pitch,
              ],
              'type' => [
                '#type' => 'html_tag',
                '#tag' => 'p',
                '#value' => $hotspot->type,
              ],
              'label' =>  [
                '#type' => 'html_tag',
                '#tag' => 'p',
                '#value' => isset($hotspot->text) ? $hotspot->text ." (ID: ". $hotspot->id.")" : t('no name'),
              ],
              'operations' => $delete_hot_spot_button
            ];
          }

          $element['hotspots_temp']['added_hotspots'] = [
            '#prefix' => '<div id="' . $element['#name'] . '-hotspot-list-wrapper">',
            '#suffix'=> '</div>',
            '#title' => t('Hotspots in this scene'),
            '#type' => 'table',
            '#name' => $element['#name'] . '_added_hotspots',
            '#header' => $table_header,
            '#empty' => t('No Hotspots yet for this Scene'),
            '#weight' => 11,
            '#attributes' => [
              'data-drupal-loaded-node-hotspot-table' => $nodeid
            ],
          ];
          if (count($table_options)) {
            // Don't add rows if no Hotspots.
            $element['hotspots_temp']['added_hotspots'] = array_merge(
              $element['hotspots_temp']['added_hotspots'],
              $table_options
            );
          }

        }
      }
    }
    // TODO.
    $element['#element_validate'][] = [get_called_class(), 'validatePanoramaTourElement'];

    return $element;
  }


  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    /** @var \Drupal\webform\Plugin\WebformElementManagerInterface $element_manager */
    $element_manager = \Drupal::service('plugin.manager.webform.element');
    $composite_elements = static::getCompositeElements($element);
    $composite_elements = WebformElementHelper::getFlattened($composite_elements);

    // Get default value for inputs.
    $default_value = [];
    foreach ($composite_elements as $composite_key => $composite_element) {
      $element_plugin = $element_manager->getElementInstance($composite_element);
      if ($element_plugin->isInput($composite_element)) {
        $default_value[$composite_key] = '';
      }
    }
    if ($input === FALSE) {
      if (empty($element['#default_value']) || !is_array($element['#default_value'])) {
        $to_return = $element['#default_value'] = [];
      }
      else {
        $to_return = $element['#default_value'];
        $to_return['allscenes'] = array_filter($to_return, function($k) {
          return is_integer($k);
        }, ARRAY_FILTER_USE_KEY);
        if (is_array($to_return['allscenes'])) {
          $to_return['allscenes'] = json_encode($to_return['allscenes']);
        }
      }

      return $to_return + $default_value;
    }

    return (is_array($input)) ? $input + $default_value : $default_value;
  }


  /**
   * Main Submit Handler for the Select Scene Submit call.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function selectSceneSubmit(
    array &$form,
    FormStateInterface $form_state
  ) {
    $button = $form_state->getTriggeringElement();

    $input = $form_state->getUserInput();
    // Hack. No explanation.
    $main_element_parents = array_slice($button['#array_parents'], 0, -1);

    $top_element = NestedArray::getValue($form, $main_element_parents);
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
    elseif (isset($input['_triggering_element_name']) &&
      $input['_triggering_element_name'] == $top_element['#name'].'_deletescene_button'
      && empty($form_state->get('hotspot_custom_errors'))
    ) {
      static::deleteScene(
        $form,
        $form_state
      );
    }
    else {
      $current_scene = $form_state->getValue([$top_element['#name'], 'scene']);
      $current_scene = $current_scene ? $current_scene : $form_state->getValue(
        [$top_element['#name'], 'currentscene']
      );
      $all_scenes_key = $top_element['#name'] . '-allscenes';
      $alreadythere = FALSE;
      if ($current_scene) {
        $all_scenes = $form_state->get($all_scenes_key);
        if (is_array($all_scenes)) {
          foreach ($all_scenes as $scene) {
            if (isset($scene['scene']) && $scene['scene'] == $current_scene) {
              $alreadythere = TRUE;
              $form_state->setValue([$top_element['#name'], 'currentscene'], $current_scene);
              break;
            }
          }
        }
        if (!$alreadythere) {
          $all_scenes[] = [
            'scene' => $current_scene,
            'hotspots' => [],
          ];

          $form_state->setValue([$top_element['#name'], 'allscenes'],json_encode($all_scenes));
          $form_state->set($all_scenes_key, $all_scenes);
          // WE need to set the input also, or Drupal 8 won't allow setting a different
          // value for the currentscene select box.
          $user_input = $form_state->getUserInput();
          if (isset($user_input[$top_element['#name']]['currentscene'])) {
            $user_input[$top_element['#name']]['currentscene'] = $current_scene;
          }
          $form_state->setUserInput($user_input);
          $form_state->setValue([$top_element['#name'], 'currentscene'], $current_scene);
        }
      }
    }

    // Rebuild the form.
    $form_state->setRebuild(TRUE);
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
    $element_name = $element['#name'];
    $response = new AjaxResponse();
    $data_selector = $element['#attributes']['data-drupal-selector'];
    // We also need to clear any visible Hotspots in case
    // There is a loaded Panorama already in place
    if ($form_state->getValue([$element_name, 'scene'])) {
      $current_scene = $form_state->getValue([$element_name, 'scene']);
      static::updateJsSettings($form_state, $current_scene, $element_name, $response);
    }
    $response->addCommand(
      new ReplaceCommand(
        '[data-drupal-selector="' . $data_selector . '"]',
        $element
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
    // Rebuild the form.
    $form_state->setRebuild(TRUE);
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
    error_log('changeSceneCallBack called');
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue(
      $form,
      array_slice($button['#array_parents'], 0, -1)
    );
    $response = new AjaxResponse();
    $element_name = $element['#name'];
    $data_selector = $element['hotspots_temp']['#attributes']['data-webform_strawberryfield-selector'];


    // Now update the JS settings
    if ($form_state->getValue([$element_name, 'scene'])) {
      $current_scene = $form_state->getValue([$element_name, 'scene']);
      static::updateJsSettings($form_state, $current_scene, $element_name, $response);
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

  /** This function updates via JS/Settings the Visible Hotspots
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param string $current_scene
   * @param string $element_name
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   */
  public static function updateJsSettings(FormStateInterface $form_state, string $current_scene, string $element_name, AjaxResponse $response) {
    // Now update the JS settings
    $all_scenes_key = $element_name . '-allscenes';
    $allscenes = $form_state->get($all_scenes_key);
    // Only here scene applies, since it is passed via the autocomplete
    $existing_objects = [];
    foreach ($allscenes as $key => &$scene) {
      if (isset($scene['scene']) && $scene['scene'] == $current_scene) {
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
    // Why twice? well because merge is deep merge. Gosh JS!
    // And merge = FALSE clears even my brain settings...
    $response->addCommand(new SettingsCommand($settingsclear, TRUE));
    $response->addCommand(new SettingsCommand($settings, TRUE));
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

    $button = $form_state->getTriggeringElement();
    $hot_spot_values_parents = array_slice($button['#parents'], 0, -1);
    $element_name = $hot_spot_values_parents[0];
    $all_scenes_key = $element_name . '-allscenes';

    $allscenes = !empty($form_state->getValue([$element_name,'allscenes'])) ? json_decode($form_state->getValue([$element_name,'allscenes']),TRUE) : [];

    if ($form_state->getValue([$element_name, 'currentscene'])
      && $allscenes) {
      $current_scene = $form_state->getValue([$element_name, 'currentscene']);
      $scene_key = 0;
      $existing_objects = [];
      foreach ($allscenes as $key => &$scene) {
        if (isset($scene['scene']) && $scene['scene'] == $current_scene
        ) {
          $scene_key = (int) $key;
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
      // Instead of trying to figure out which Incremental ID is next
      // stop wasting cycles and generate a UUID for it and prefix it. Will
      // also confuse users less after deleting/adding/deleting and ending
      // with non consecutive Ids.
      $newid = Uuid::uuid4();
      $hotspot->id = $element_name . '_' . $current_scene . '_' . $newid->toString();
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
        // So. we do need to check if it needs to be done directly
        if (!is_numeric($nodeid)) {
          $nodeid = EntityAutocomplete::extractEntityIdFromAutocompleteInput(
            $nodeid
          );
        }
        if (is_numeric($nodeid) && (int)$nodeid == $nodeid) {
          $url = \Drupal\Core\Url::fromRoute(
            'entity.node.canonical',
            ['node' => $nodeid],
            []
          );
          $url = $url->toString();
          $hotspot->type = 'info';
          $hotspot->URL = $url;
        } else {
          $form_state->setRebuild(TRUE);
          return;
        }
      }

      if ($hotspot->type == 'scene') {
        $sceneid = $form_state->getValue(
          [$element_name, 'hotspots_temp', 'adoscene']
        );

        $hotspot->type = 'scene';
        $hotspot->sceneId = "{$sceneid}";
      }

      if ($hotspot->type == 'text') {
        $hotspot->text = $hotspot->text;
        $hotspot->type = 'info';
      }

      $existing_objects[] = (array) $hotspot;

      // @TODO make sure people don't add twice the same coordinates!

      // Push hotspots there

      $allscenes[$scene_key]['hotspots'] = $existing_objects;
      $form_state->set($all_scenes_key, $allscenes);
      $form_state->setValue([$element_name, 'allscenes'], json_encode($allscenes));


    } else {
      // Do we alert the user? Form needs to be restarted
      static::messenger()->addError(t('Something bad happened with the Tour builder, sadly you will have you restart your session.'));
      // We could set a form_state value and render it when the form rebuilds?
    }

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
    $element_name = $element['#name'];
    $response = new AjaxResponse();
    $data_selector = $element['hotspots_temp']['added_hotspots']['#attributes']['data-drupal-loaded-node-hotspot-table'];
    $existing_objects = [];
    $current_scene = $form_state->getValue([$element_name, 'currentscene']);
    if ($current_scene) {
      $allscenes = $form_state->getValue([$element_name, 'allscenes']);
      $allscenes = json_decode($allscenes, TRUE);
      $scene_key = 0;
      $existing_objects = [];
      foreach ($allscenes as $key => &$scene) {
        if ($scene['scene'] == $current_scene
        ) {
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
   * Submit handler for the "deleteHotSpot" button.
   *
   * Adds Key and View Mode to the Table Drag  Table.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function deleteHotSpot(array &$form, FormStateInterface $form_state) {

    $button = $form_state->getTriggeringElement();

    $element = NestedArray::getValue(
      $form,
      array_slice($button['#array_parents'], 0, -4)
    );

    $element_name = $element['#name'];

    $allscenes = !empty($form_state->getValue([$element_name,'allscenes'])) ? json_decode($form_state->getValue([$element_name,'allscenes']),TRUE) : [];

    if ($form_state->getValue([$element_name, 'currentscene'])
      && $allscenes) {
      $all_scenes_key = $element_name . '-allscenes';
      $current_scene = $form_state->getValue([$element_name, 'currentscene']);
      $scene_key = 0;
      $existing_objects = [];
      foreach ($allscenes as $key => &$scene) {
        if (isset($scene['scene']) && $scene['scene'] == $current_scene
        ) {
          $scene_key = (int) $key;
          $existing_objects = $scene['hotspots'];
          break;
        }
      }
      $keytodelete = NULL;
      if ($existing_objects && is_array($existing_objects)) {
        if (isset($button['#hotspottodelete'])) {
          foreach($existing_objects as $key => $hotspot) {
            if (is_array($hotspot)) {
              $hotspot = (object) $hotspot;
            }
            if ($hotspot->id == $button['#hotspottodelete']) {
              $keytodelete = $key;
              break;
            }
          }
          // Because 0 will not pass an isset so ....
          if ($keytodelete !== NULL) {
            unset($existing_objects[$keytodelete]);
            $existing_objects = array_values($existing_objects);
            $allscenes[$scene_key]['hotspots'] = $existing_objects;
          }
        }
      }

      $form_state->set($all_scenes_key, $allscenes);
      $form_state->setValue([$element_name, 'allscenes'],json_encode($allscenes));


    } else {
      // Do we alert the user? Form needs to be restarted
      \Drupal::messenger()->addError(t('Something bad happened with the Tour builder, sadly you will have you restart your session.'));
      // We could set a form_state value and render it when the form rebuilds?
    }

    $form_state->setRebuild(TRUE);
  }

  /**
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public static function deleteHotSpotCallback(
    array $form,
    FormStateInterface $form_state
  ) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue(
      $form,
      array_slice($button['#array_parents'], 0, -4)
    );

    $element_name = $element['#name'];
    $response = new AjaxResponse();
    $data_selector = $element['hotspots_temp']['added_hotspots']['#attributes']['data-drupal-loaded-node-hotspot-table'];
    $existing_objects = [];
    $current_scene = $form_state->getValue([$element_name, 'currentscene']);
    if (isset($button['#hotspottodelete'])) {

      $data_selector2 = $element['hotspots_temp']['#attributes']['data-drupal-selector'];

      $response->addCommand(
        new removeHotSpotCommand(
          '[data-drupal-selector="' . $data_selector2 . '"]',
          $button['#hotspottodelete'],
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
   * Submit Handler for Setting the Scene Orientation.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function setSceneOrientation(
    array &$form,
    FormStateInterface $form_state
  ) {
    $button = $form_state->getTriggeringElement();
    $hot_spot_values_parents = array_slice($button['#parents'], 0, -1);
    $element_name = $hot_spot_values_parents[0];
    if ($form_state->getValue([$element_name, 'currentscene'])) {
      $all_scenes_key = $element_name . '-allscenes';
      $allscenes = $form_state->get($all_scenes_key);
      $current_scene = $form_state->getValue([$element_name, 'currentscene']);
      $scene_key = 0;
      $existing_objects = [];
      foreach ($allscenes as $key => &$scene) {
        if ($scene['scene'] == $current_scene) {
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
      $form_state->setValue([$element_name, 'allscenes'], json_encode($allscenes));
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
      array_slice($button['#array_parents'], 0, -2)
    );
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

  /**
   * Submit Handler for deleting a Scene Orientation.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public static function deleteScene(
    array &$form,
    FormStateInterface $form_state
  ) {
    $button = $form_state->getTriggeringElement();
    $hot_spot_values_parents = array_slice($button['#parents'], 0, -1);
    $element_name = $hot_spot_values_parents[0];
    if ($form_state->getValue([$element_name, 'currentscene'])) {
      $all_scenes_key = $element_name . '-allscenes';
      $allscenes = $form_state->get($all_scenes_key);
      $current_scene = $form_state->getValue([$element_name, 'currentscene']);
      $scene_key = 0;

      foreach ($allscenes as $key => &$scene) {
        if ($scene['scene'] == $current_scene) {
          $scene_key = $key;
          break;
        }
      }
      // $scene key here is numeric, not the NODE ID.
      unset($allscenes[$scene_key]);
      // which will reorder keys
      $allscenes = array_merge($allscenes);
      // remove any hotspot pointing to this scene
      foreach ($allscenes as $key => $scene) {
        $allscenes[$key]['hotspots'] = array_filter($scene['hotspots'], function($e) use($current_scene) {
          $e = (array) $e;
          return (!isset($e["sceneId"]) || $e["sceneId"]!= (string) $current_scene);
        });
        // which will reorder keys of the hotspots
        $allscenes[$key]['hotspots'] = array_merge($allscenes[$key]['hotspots']);
      }
      $form_state->set($all_scenes_key, $allscenes);
      $form_state->setValue([$element_name, 'allscenes'], json_encode($allscenes));
      if (!empty($allscenes)) {
        $firstscene = reset($allscenes);
        $firstsceneid = $firstscene['scene'];
        $form_state->setValue([$element_name, 'currentscene'], $firstsceneid);
      } else {
        $form_state->setValue([$element_name, 'currentscene'], NULL);
      }
      error_log('Scene was removed');
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
  public static function deleteSceneCallBack(
    array $form,
    FormStateInterface $form_state
  ) {
    $button = $form_state->getTriggeringElement();
    $element = NestedArray::getValue(
      $form,
      array_slice($button['#array_parents'], 0, -2)
    );
    $response = new AjaxResponse();
    $element_name = $element['#name'];
    $data_selector = $element['#attributes']['data-drupal-selector'];


    $settingsclear = [
      'webform_strawberryfield' => [
        'WebformPanoramaTour' => [
          $element_name . '-hotspots' =>
            NULL
        ],
      ]
    ];
    $response->addCommand(new SettingsCommand($settingsclear, TRUE));

    // Now update the JS settings
    error_log('deleting scene');
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

      $settings = [
        'webform_strawberryfield' => [
          'WebformPanoramaTour' => [
            $element_name . '-hotspots' =>
              $existing_objects
          ],
        ]
      ];

      // Why twice? well because merge is deep merge. Gosh JS!
      // And merge = FALSE clears even my brain settings...
      $response->addCommand(new SettingsCommand($settings, TRUE));
    }

    // And now replace the container
    $response->addCommand(
      new ReplaceCommand(
        '[data-drupal-selector="' . $data_selector . '"]',
        $element
      )
    );

    return $response;
  }

  /**
   * Validates a composite element.
   *
   * @param $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param $complete_form
   */
  public static function validateWebformComposite(&$element, FormStateInterface $form_state, &$complete_form) {
    // IMPORTANT: Must get values from the $form_states since sub-elements
    // may call $form_state->setValueForElement() via their validation hook.
    // @see \Drupal\webform\Element\WebformEmailConfirm::validateWebformEmailConfirm
    // @see \Drupal\webform\Element\WebformOtherBase::validateWebformOther
    $value = NestedArray::getValue($form_state->getValues(), $element['#parents']);
    // Only validate composite elements that are visible.
    $has_access = (!isset($element['#access']) || $element['#access'] === TRUE);
    if ($has_access) {
      // Validate required composite elements.
      $composite_elements = static::getCompositeElements($element);
      $composite_elements = WebformElementHelper::getFlattened($composite_elements);
      foreach ($composite_elements as $composite_key => $composite_element) {
        $is_required = !empty($element[$composite_key]['#required']);
        $is_empty = (isset($value[$composite_key]) && $value[$composite_key] === '');
        if ($is_required && $is_empty) {
          WebformElementHelper::setRequiredError($element[$composite_key], $form_state);
        }
      }
    }

    // Clear empty composites value.
    if (empty(array_filter($value))) {
      $element['#value'] = NULL;
      $form_state->setValueForElement($element, NULL);
    }
  }

  public static function validatePanoramaTourElement(
    &$element,
    FormStateInterface $form_state,
    &$complete_form
  ) {

    if ($triggering = $form_state->getTriggeringElement()) {
      if (reset($triggering['#parents']) == $element['#name']) {
        // Means it was something inside the button
      }
      else {
        error_log('Clear our redundant values');
        $form_state->unsetValue([$element['#name'], 'scene']);
        $form_state->unsetValue([$element['#name'], 'hotspots']);
        $form_state->unsetValue([$element['#name'], 'hotspots_temp']);
        $form_state->unsetValue([$element['#name'], 'select_button']);
        $form_state->unsetValue([$element['#name'], 'select_button']);
        // sets that actual expanded array back into the element value
        // but only when the validation is triggered by the main form,
        // like in a next button action or a save one.
        $value = NestedArray::getValue($form_state->getValues(), $element['#parents']);
        // Let's try to get our direct form value into the main for element value
        // if its not there, lets use our input. Feels safer since people won't
        // have the chance to alter input via HTML.
        // Inverse of that the process function does.
        $all_scenes_key = $element['#name'] . '-allscenes';
        $all_scenes_from_form_state = !empty($form_state->getValue([$element['#name'],'allscenes'])) ? json_decode($form_state->getValue([$element['#name'],'allscenes']),TRUE) : [];
        $all_scenes_from_form_value = !empty($form_state->get($all_scenes_key)) ? $form_state->get($all_scenes_key) : [];
        // Basically. First try to get it from the defaults set by the ::valuecallback().
        // This will populate Form state value.
        // But once that happens, the #value elements does not survive.
        // So we store it in a form state variable. And keep setting it.
        $all_scenes = !empty($all_scenes_from_form_value) ? $all_scenes_from_form_value : $all_scenes_from_form_state;
        $form_state->setValueForElement($element, $all_scenes);
      }
    }
  }

}
