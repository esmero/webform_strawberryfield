<?php

namespace Drupal\webform_strawberryfield\Plugin;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;

/**
 * Defines and Interface for CustomLoDendpointManager Plugins
 *
 * Interface CustomLoDendpointInterface
 *
 * @package Drupal\webform_strawberryfield\Plugin
 */
interface CustomLoDendpointInterface extends PluginInspectionInterface, PluginWithFormsInterface, ConfigurableInterface, DependentPluginInterface{


  /**
   * Provides a list of Key name strawberryfield properties
   *
   * @param string $config_entity_id
   *   The Config Entity's id where this plugin instance's config is stored.
   *   This value comes from the config entity used to store all this settings
   *   and needed to generate separate cache bins for each
   *   Plugin Instance.
   *
   * @return mixed
   */
  public function provideWebformElement(string $config_entity_id):array;

  public function handleQuery(string $query):array;

  public function label();

  public function onDependencyRemoval(array $dependencies);



}