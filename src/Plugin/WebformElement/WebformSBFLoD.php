<?php

namespace Drupal\webform_strawberryfield\Plugin\WebformElement;

use Drupal\webform\Plugin\WebformElement\WebformCompositeBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class WebformSBFLoD extends WebformCompositeBase {


  /**
   * The entity storage class.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface;
   */
  protected $entityTypeManager;

  /**
   * The Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface;
   */
  protected $configFactory;


  /**
   * The StrawberryRunner Processor Plugin Manager.
   *
   * @var \Drupal\webform_strawberryfield\Plugin\CustomLoDendpointManager
   */
  protected $customLoDendpointPluginManager;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->configFactory = $container->get('config.factory');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->customLoDendpointPluginManager = $container->get('webform_strawberryfield.custom_lod_endpoint_manager');
    return $instance;
  }

  /**
   * @param bool $as_form_options
   *    If TRUE, instead of returning the Custom LOD Entity ID and configs, we
   *    return an array with `ID => Label` pairs to be used in a select element
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getCustomLoDEndpoints(bool $as_form_options = FALSE): array {
    $active_plugins = [];
    /* @var $plugin_config_entities \Drupal\webform_strawberryfield\Entity\LoDendpointEntity[] */
    $plugin_config_entities = $this->entityTypeManager->getListBuilder(
      'webform_sbf_lod_endpoint'
    )->load();

    foreach ($plugin_config_entities as $plugin_config_entity) {
      // Only get first level (no Parents) and Active ones.
      if ($plugin_config_entity->isActive()) {
        $entity_id = $plugin_config_entity->id();
        $configuration_options = $plugin_config_entity->getPluginconfig();
        $configuration_options['configEntity'] = $entity_id;
        /* @var \Drupal\strawberry_runners\Plugin\StrawberryRunnersPostProcessorPluginInterface $plugin_instance */
        if (!$as_form_options) {
          $plugin_instance
            = $this->customLoDendpointPluginManager->createInstance(
            $plugin_config_entity->getPluginid(),
            $configuration_options
          );

          $active_plugins[$entity_id]
            = $plugin_instance->getConfiguration();
        }
        else {
          // If $as_form_options == true, then we just return ID and labels
          // e.g. to be used in a select element in a form.
          $active_plugins[$entity_id] = $plugin_config_entity->label();
        }
      }
    }
    return $active_plugins;
  }

}