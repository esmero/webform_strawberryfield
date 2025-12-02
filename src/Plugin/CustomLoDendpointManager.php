<?php

namespace Drupal\webform_strawberryfield\Plugin;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\webform_strawberryfield\Attribute\CustomLoDendpoint;

/**
 * Provides the CustomLoDendpoint Plugin  Manager.
 *
 * Class CustomLoDendpointManager
 *
 * @package Drupal\webform_strawberryfield\Plugin
 */
class CustomLoDendpointManager extends DefaultPluginManager {

  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler
  ) {
    parent::__construct(
      'Plugin/CustomLoDendpoint',
      $namespaces,
      $module_handler,
      CustomLoDendpointInterface::class,
      CustomLoDendpoint::class,
      'Drupal\webform_strawberryfield\Annotation\CustomLoDendpoint'
    );

    $this->alterInfo('webform_strawberryfield_customlodendpoint_info');
    $this->setCacheBackend($cache_backend,'customlodendpoint_plugins');
  }


}