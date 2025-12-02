<?php

namespace Drupal\webform_strawberryfield\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform_strawberryfield\Plugin\CustomLoDendpointManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\strawberryfield\Entity\lodendpointentity;
use Drupal\Component\Utility\NestedArray;

/**
 * Class LoDendpointEntityForm.
 */
class LoDendpointEntityForm extends EntityForm {


  /**
   * The Custom LOD ndpoint Plugin Manager.
   *
   * @var CustomLoDendpointManager;
   */
  protected $customLoDendpointManager;

  public function __construct(CustomLoDendpointManager $CustomLoDendpointManager) {
    $this->customLoDendpointManager = $CustomLoDendpointManager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('webform_strawberryfield.custom_lod_endpoint_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /* @var \Drupal\webform_strawberryfield\Entity\LoDendpointEntity $LoDendpointEntity */
    $LoDendpointEntity = $this->entity;


    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $LoDendpointEntity->label(),
      '#description' => $this->t("Label for the Custom LOD Endpoint."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $LoDendpointEntity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\webform_strawberryfield\Entity\LoDendpointEntity::load',
      ],
      '#disabled' => !$LoDendpointEntity->isNew(),
    ];

    $ajax = [
      'callback' => [get_class($this), 'ajaxCallback'],
      'wrapper' => 'lodendpointentity-ajax-container',
    ];
    /* @var \Drupal\webform_strawberryfield\Plugin\CustomLoDendpointManager $plugin_definitions */
    $plugin_definitions = $this->customLoDendpointManager->getDefinitions();
    $options = [];
    foreach ($plugin_definitions as $id => $definition) {
      $options[$id] = $definition['label'];
    }

    $form['pluginid'] = [
      '#type' => 'select',
      '#title' => $this->t('The LOD Plugin'),
      '#default_value' => $LoDendpointEntity->getPluginid(),
      '#options' => $options,
      "#empty_option" =>t('- Select One -'),
      '#required'=> true,
      '#ajax' => $ajax
    ];

    $form['container'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'lodendpointentity-ajax-container'],
      '#weight' => 100,
      '#tree' => true
    ];

    $pluginid = $form_state->getValue('pluginid')?:$LoDendpointEntity->getPluginid();
    if (!empty($pluginid))  {
      $this->messenger()->addMessage($form_state->getValue('pluginid'));
      $form['container']['pluginconfig'] = [
        '#type' => 'container',
        '#parents' => ['pluginconfig']
      ];
      $parents = ['container','pluginconfig'];
      $elements = $this->customLoDendpointManager->createInstance($pluginid,[])->settingsForm($parents, $form_state);
      $pluginconfig = $LoDendpointEntity->getPluginconfig();

      $form['container']['pluginconfig'] = array_merge($form['container']['pluginconfig'],$elements);
      if (!empty($pluginconfig)) {
        foreach ($pluginconfig as $key => $value) {
            if (isset($form['container']['pluginconfig'][$key])) {
              ($form['container']['pluginconfig'][$key]['#default_value'] = $value);
            }
        }
      }
    } else {
      $form['container']['pluginconfig'] = [
        '#type' => 'container',
      ];

    }

    $form['active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Is this plugin active?'),
      '#return_value' => TRUE,
      '#default_value' => $LoDendpointEntity->isActive(),
    ];

    //@TODO allow a preview of the processing via ajax

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $LoDendpointEntity = $this->entity;
    $status = $LoDendpointEntity->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger->addStatus($this->t('Created the %label LoD Endpoint.', [
          '%label' => $LoDendpointEntity->label(),
        ]));
        break;

      default:
        $this->messenger->addStatus($this->t('Saved the %label LoD Endpoint.', [
          '%label' => $LoDendpointEntity->label(),
        ]));
    }
    $form_state->setRedirectUrl($LoDendpointEntity->toUrl('collection'));
  }

  /**
   * Ajax callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   An associative array containing entity reference details element.
   */
  public static function ajaxCallback(array $form, FormStateInterface $form_state) {
    $form_state->setRebuild();
    return $form['container'];
  }


}
