<?php
/**
 * Created by PhpStorm.
 * User: dpino
 * Date: 8/15/18
 * Time: 2:33 PM
 */

namespace Drupal\webform_strawberryfield\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\webform\WebformInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\strawberryfield\Semantic\ActivityStream;

/**
 * Plugin implementation of the 'strawberryfield_webform_inline_widget' widget.
 *
 * @FieldWidget(
 *   id = "strawberryfield_webform_inline_widget",
 *   label = @Translation("Strawberryfield webform based input with inline rendering"),
 *   description = @Translation("A Strawberry Field widget that uses a webform as input and renders it inline."),
 *   field_types = {
 *     "strawberryfield_field"
 *   }
 * )
 */
class StrawberryFieldWebFormInlineWidget extends WidgetBase implements ContainerFactoryPluginInterface
{

  /**
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   *  Constructs a StrawberryFieldWebFormWidget.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentuser
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, AccountProxyInterface $currentuser) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->currentUser = $currentuser;
  }

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @return \Drupal\Core\Plugin\ContainerFactoryPluginInterface|\Drupal\webform_strawberryfield\Plugin\Field\FieldWidget\StrawberryFieldWebFormWidget
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'webform_id' => '',
        'placeholder' => '',
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    $element['webform_id'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'webform',
      '#default_value' => $this->getSetting('webform_id') ? \Drupal\webform\Entity\Webform::load($this->getSetting('webform_id')) : NULL,
      '#validate_reference' => FALSE,
      '#maxlength' => 1024,
      '#placeholder' => t('Select an existing Webform to be used as default input.')
    ];

    $element['placeholder'] = [
      '#type' => 'textfield',
      '#title' => t('Placeholder'),
      '#default_value' => $this->getSetting('placeholder'),
      '#description' => t('User friendly description of what this field will hold. E.g Metadata. Leave empty to use the Field\'s Label'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    return $summary;
  }


  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
  {

    // Override the title from the incoming $element.
    if ($this->getSetting('placeholder') && !empty(trim($this->getSetting('placeholder')))) {
      $element['#title'] = trim($this->getSetting('placeholder'));
    }

    //Lets gather some basics
    // Where is this field being used, a node?
    $entity_type = $items->getEntity()->getEntityTypeId();
    $bundle = $items->getEntity()->bundle();
    $bundle_label = $items->getEntity()->type->entity->label();
    // So does the current loaded entity, where this widget is shown
    // has an id? If it has means we are editing!
    // We can always check via $items->getEntity()->isNew()
    //$parents = $element['#field_parents'];
    // @todo give this some use maybe?
    // Future: we want as much as entity based info passed into the field
    // So we can expose the field as such via a new route
    // Uses are infinite, like a direct deposit of all the data and
    // also the famous main @id of the json-ld

    // Even if new or old, that entity has an uuid from the start on.
    // We will use that one to store the webform data around and reload when submitting.
    if ($items->getEntity()->isNew()) {
      $form_state->set('strawberryfield_webform_isnew', true);
      // Weird place but if still to be created there is no way i can keep a single value constant
      // Inside this form. All changes, or at least i can not find it yet!
      //@TODO smart people, ideas?
      // Consequence of this is that if a same user opens two new digital objects
      // at the same time data from one could permeate to the other.
      // For a little little while...
      $entity_uuid = NULL;
      $entity_id = NULL;
    } else {
      $form_state->set('strawberryfield_webform_isnew', false);
      // This entity was born to be wild.
      $entity_uuid = $items->getEntity()->uuid();
      $entity_id = $items->getEntity()->id();
    }
    $form_state->set('strawberryfield_webform_bundle_label', $bundle_label);

    // We will identify this widget amongst others using its form parents
    // as sha1 seed we use something unique what will stay the same across
    // form state reconstructs.
    // @IMPORTANT this is the piece of resistance
    // It brings all together, webform, widget and temp storage
    // in a balanced state of pure peace.

    // GOSH: Learned. Drupal assigns a new UUID to a new NODE BUT, wait..
    // On each form rebuild it creates a new one.. why would someone do
    // that????? So new check Mr. i Can't trust your UUID..


    $unique_widget_state_seed = array_merge(
      [$items->getName()],
      [$delta],
      [$entity_uuid]
    );

    $this_widget_id = sha1(implode('-', $unique_widget_state_seed));
    $this_widget_id_saved = $form_state->get('strawberryfield_webform_widget_id');


    if ($this_widget_id_saved) {
      $this_widget_id = $this_widget_id_saved;
      // If we already have a widgetid, keep using the same
      $form_state->set('strawberryfield_webform_widget_id', $this_widget_id_saved);
    }
    else {
      $form_state->set('strawberryfield_webform_widget_id', $this_widget_id);
    }

    // So which webform to load?
    // Logic says either use the one that was used originally or fall back to some default

    $my_webform_machinename = $this->getSetting('webform_id') ? $this->getSetting('webform_id') : NULL;

    if (empty($my_webform_machinename)) {
      $my_webform_machinename = 'webform_strawberry_default';
    }

    $my_webform = \Drupal\webform\Entity\Webform::load($my_webform_machinename);

    if ($my_webform == null) {
      // Well someone dropped the ball here
      // and removed our super default
      // or the original webform exists only in our hopes.
      return $this->_exceptionElement($items, $delta, $element,$form, $form_state);
    }
    $form_state->set('webform_machine_name', $my_webform_machinename);
    try {
      $form_state->set(
        'webform_machine_name_url',
        $my_webform->toUrl()->setAbsolute()->toString()
      );
    } catch (EntityMalformedException $e) {
      return $this->_exceptionElement($items, $delta, $element,$form, $form_state);
    }

    // This will be our temp storage id
    // Composed of the content entity uuid and this fields name
    // Since both are unique in this context we can't get wrong!

    //@todo add better limit validation errors to the full form.
    $parents = array_merge($element['#field_parents'], [
      $items->getName(),
      $delta,
      'strawberry_webform_inline'
    ]);
    $limit_validation_errors = $parents;

    // We add 'data-drupal-selector' = 'strawberry_webform_widget'
    // To allow JS to react/jquery select on this.
    $element += array(
      '#type' => 'fieldset',
      '#attributes' => [
        'data-strawberryfield-selector' => [
          'strawberry-webform-widget'
        ],
      ],
      '#title' => $this->getSetting('placeholder')?: $items->getName(),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      '#element_validate' => array(
        array($this, 'validateWebform'),
      ),
      '#limit_validation_errors' => $limit_validation_errors,
    );

    $savedvalue = $items[$delta]->getValue();
    $tempstore = \Drupal::service('tempstore.private')->get('archipel');
    $tempstoreId = $this_widget_id;

    /* @var $tempstore \Drupal\Core\TempStore\PrivateTempStore */
    // Which means an abandoned Metadata Sessions somewhere
    // Someone saved 'metadata' during a form update and left for coffee
    // WE can reuse!
    if (($tempstore->getMetadata($tempstoreId) != NULL) && $items->getEntity()->isNew()) {
      $json_string = $tempstore->get($tempstoreId);
      $json = json_decode($json_string, TRUE);
      $json_error = json_last_error();
      if ($json_error == JSON_ERROR_NONE) {
        $savedvalue['value'] = $json_string;
      }
      $element['strawberry_webform_inline_message'] = [
          '#type' => 'item',
          '#title' => $this
            ->t('Resuming metadata session:'),
          '#markup' =>  $this->t('We found and loaded a previous unfinished metadata session for you.')
      ];
    }

    // Autofill from a previus submision if current value is empty
    $webform_autofill = empty($savedvalue['value']) || $items->getEntity()->isNew();
    // If new this won't exist
    $stored_value = !empty($savedvalue['value']) ? $savedvalue['value']: "{}";

    $data_defaults = [
      'strawberry_field_widget_state_id' => $this_widget_id,
      'strawberry_field_widget_source_entity_uuid' => $entity_uuid,
      'strawberry_field_widget_source_entity_id' => $entity_id,
      'strawberry_field_stored_values' => json_decode($stored_value,true)
    ];

    if (!isset($stored_value) || empty($stored_value)) {
      // No data
      $data['data'] = $data_defaults  +
        [
          'label' => 'New metadata'
        ];
    }
    else {
      $data['data'] = $data_defaults + json_decode($stored_value,true);
    }

    // @see \Drupal\webform_strawberryfield\Element\WebformCustom element.
    // @TODO expose #override options in the widget config.
    // @see \Drupal\webform\Entity\Webform::getDefaultSettings for all settings

    // @TODO 'autofill' => $webform_autofill can potentially override
    // anny passed data. WE need to deal with that manually
    // IDEA: keep a draft version around in another temp storage
    // reload if empty, clean if correctly submitted

    // @TODO make this a setting
    // If the node is new show the inline form
    // But if the Node exists, SBF is there, show the on-click widget
    if ($items->getEntity()->isNew()) {

      $element['strawberry_webform_inline'] = [
        '#type' => 'webform_inline_fieldwidget',
        '#webform' => $my_webform_machinename,
        '#default_data' => $data['data'],
        '#override' => [
          'form_submit_once' => FALSE,
          'confirmation_type' => WebformInterface::CONFIRMATION_INLINE,
          'confirmation_back' => TRUE,
          'results_disabled' => TRUE,
          'confirmation_exclude_token' => TRUE,
          'wizard_progress_link' => TRUE,
          'submission_user_duplicate' => TRUE,
          'submission_log' => FALSE,
          'confirmation_message' => $this->t(
            'Thanks, you are all set! Please Save the content to persist the changes.'
          ),
        ],
      ];
      $element['strawberry_webform_inline']['#parents'] = $parents;
    }
    else {

      // Webform controller wrapper URL
      $this_field_name = $this->fieldDefinition->getName();
      //@see \Drupal\webform_strawberryfield\Controller\StrawberryRunnerModalController
      // We need to assume nothing of this will ever work without AJAX/JS.

      $webform_controller_url= Url::fromRoute('webform_strawberryfield.modal_webform',
        [
          'webform' =>  $my_webform_machinename,
          'source_entity_types' => "$entity_type:$bundle",
          'state'=> "$entity_uuid:$this_field_name:$delta:$this_widget_id",
          'modal' => FALSE
        ]
      );
      $element['strawberry_webform_open_modal']  = [
        '#type' => 'link',
        '#title' => $this->t('Edit @a', array('@a' => $this->getSetting('placeholder')?: $items->getName())),
        '#url' => $webform_controller_url,
        '#attributes' => [
          'class' => [
            'use-ajax',
            'button',
            'btn-primary',
            'btn'
          ],
        ],
      ];
      $webform_controller_url_close= Url::fromRoute('webform_strawberryfield.close_modal_webform',
        [
          'state'=> "$entity_uuid:$this_field_name:$delta:$this_widget_id",
          'modal' => FALSE,
        ]
      );
      $element['strawberry_webform_close_modal'] = [
        '#type' => 'link',
        '#title' => $this->t('Cancel @a editing', array('@a' => $this->getSetting('placeholder')?: $items->getName())),
        '#url' => $webform_controller_url_close,
        '#attributes' => [
          'class' => [
            'use-ajax',
            'button',
            'btn-warning',
            'btn',
            'js-hide'
          ],
        ],
      ];
    }


    // The following elements are kinda hidden and match the field properties
    $current_value = $items[$delta]->getValue();

    if (empty($current_value['creation_method'])){
      $current_value['creation_method'] = $my_webform_machinename;
    }

    if (empty($current_value['value'])){
      $current_value['value'] = '{}';
    }
    $element['strawberry_webform_widget']['json'] = [
      '#type' => 'value',
      '#id' => 'webform_output_' . $this_widget_id,
      '#default_value' => $current_value['value'],
    ];

    $element['strawberry_webform_widget']['creation_method'] = [
      '#type' => 'value',
      '#id' => 'webform_output_' . $this_widget_id,
      '#default_value' => $current_value['creation_method'],
    ];

    return $element;
  }


  /**
   * Generates default minimal input in case the desired webform is kaput.
   *
   * @param FieldItemListInterface $items
   * @param $delta
   * @param array $element
   * @param array $form
   * @param FormStateInterface $form_state
   * @return array
   */
  protected function _exceptionElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state)
  {
    $current_value = $items[$delta]->getValue();

    $element['strawberry_webform_widget']['json'] = [
      '#type' => 'textarea',
      '#id' => 'webform_output_json' .  $form_state->get('strawberryfield_webform_widget_id'),
      '#default_value' => $current_value['value'],
      '#title' => $this->t('Your metadata in JSON'),
      '#description' => $this->t('You are seeing this because the webform used to create this value does not exist',array('@webform' => $form_state->get('webform_machine_name'))),
      '#disabled' => FALSE,
      '#rows' => 15,
    ];
    $element['strawberry_webform_widget']['creation_method'] = [
      '#type' => 'value',
      '#id' => 'webform_output_webform' . $form_state->get('strawberryfield_webform_widget_id'),
      '#default_value' => $current_value['creation_method']
    ];

    return $element;

  }

  public function validateWebform($element, FormStateInterface $form_state) {
    // Validate

    $tempstoreId = $form_state->get('strawberryfield_webform_widget_id');
    /* @var $tempstore \Drupal\Core\TempStore\PrivateTempStore */

    $tempstore = \Drupal::service('tempstore.private')->get('archipel');
    if ($tempstore->getMetadata($tempstoreId) == NULL ) {
      // Means its empty. This can be Ok if something else than "save"
      // Is triggering the Ajax Submit action like the "Display Switch"
      // Or we are not enforcing (required) really any values
      if ($form_state->get('strawberryfield_webform_isnew')) {
        // But if this a new Object and the tempstore is empty means
        // No filling, no doing anything has happened
        // And that is bad. So mark an error
        $form_state->setError(
          $element,
          $this->t(
            "Please complete all steps. You can not Save this @bundle_label without completing the required Form.",
            [
              '@bundle_label' => $form_state->get('strawberryfield_webform_bundle_label')
            ]
          )
        );
      }
        return;
    }

    $json_string = $tempstore->get($tempstoreId);
    $json = json_decode($json_string, TRUE);
    $json_error = json_last_error();
    if ($json_error == JSON_ERROR_NONE) {
      $form_state->setValueForElement($element['strawberry_webform_widget']['json'], $json_string);
      // Let tempstore entry expire or be removed by \Drupal\webform_strawberryfield\EventSubscriber\WebformStrawberryfieldDeleteTmpStorage
      return;
    }
    else {
        $form_state->setError($element, $this->t("Something went wrong, so sorry. Your data does not taste like strawberry (JSON malformed) and we failed validating it: @json_error.",
          [
            '@json_error' => $json_error
          ]));
      }
  }


  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state)
  {
    parent::extractFormValues($items, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state)
  {
    $jsonarray = json_decode($values[0]['strawberry_webform_widget']['json'], true);
    // If "as:generator" is in place this will simply replace it
    // @TODO if previous form exists and is different to this one then we could
    // A) add new values to existing field ones..
    // B) merge them
    // c) totally replace them
    // Who do we ask?

    $jsonarray["as:generator"] = $this->addActivityStream($form_state);
    $jsonvalue  =  json_encode($jsonarray, JSON_PRETTY_PRINT);
    $values2[0]['value'] = $jsonvalue;
    // @TODO this no longer is part of wild strawberry field defintion. We already have other
    // ways of keeping track. Remove or deprecate.
    $values2[0]['creation_method'] = $values[0]['strawberry_webform_widget']['creation_method'];

    return parent::massageFormValues($values2, $form, $form_state);
  }


  protected function addActivityStream(FormStateInterface $form_state) {

    // We use this to keep track of the webform used to create/update the field's json
    $eventBody = [
      'summary' => 'Generator',
      'endTime' => date('c'),
    ];
    // @TODO We need to dispatch this too, as we did on Archipelago
    // @TODO TYPE = Create id new , Update if Old
    // @TODO also see how we are going to keep tombstones.

    $actor_properties = [
      'name' => $form_state->get('webform_machine_name') ?: 'NaW',
      'url' =>  $form_state->get('webform_machine_name_url') ?: '',
    ];
    $event_type =  $form_state->get('strawberryfield_webform_isnew') ? ActivityStream::ASTYPES['Create'] : ActivityStream::ASTYPES['Update'];

    $activitystream = new ActivityStream($event_type, $eventBody);

    $activitystream->addActor(ActivityStream::ACTORTYPES['Service'], $actor_properties);
    return $activitystream->getAsBody()?:[];

  }

}