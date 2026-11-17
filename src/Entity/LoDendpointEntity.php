<?php

namespace Drupal\webform_strawberryfield\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\webform_strawberryfield\Form\LoDendpointEntityForm;
use Drupal\webform_strawberryfield\LoDendpointEntityHtmlRouteProvider;
use Drupal\webform_strawberryfield\LoDendpointEntityListBuilder;

/**
 * Defines the Strawberry Key Name Providers entity.
 */
#[ConfigEntityType(
  id: 'webform_sbf_lod_endpoint',
  label: new TranslatableMarkup('Webform Strawberryfield Custom LoD endpoints'),
  config_prefix: 'webform_sbf_lod',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid'
  ],
  handlers: [
    'view_builder' => EntityViewBuilder::class,
    'list_builder' => LoDendpointEntityListBuilder::class,
    'form' => [
      'add' => LoDendpointEntityForm::class,
      'edit' => LoDendpointEntityForm::class,
      'delete' => 'Drupal\webform_strawberryfield\Form\LoDendpointEntityDeleteForm'
    ],
    'route_provider' => [
      'html' => LoDendpointEntityHtmlRouteProvider::class]
  ],
  links: [
    'canonical' => '/admin/structure/webform_strawberryfield_lod/{webform_sbf_lod_endpoint}',
    'add-form' => '/admin/structure/webform_strawberryfield_lod/add',
    'edit-form' => '/admin/structure/webform_strawberryfield_lod/{webform_sbf_lod_endpoint}/edit',
    'delete-form' => '/admin/structure/webform_strawberryfield_lod/{webform_sbf_lod_endpoint}/delete',
    'collection' => '/admin/structure/webform_strawberryfield_lod'],
  admin_permission: 'administer site configuration',
  config_export: [
    'id',
    'label',
    'uuid',
    'pluginid',
    'pluginconfig',
    'active'
  ]
)]
class LoDendpointEntity extends ConfigEntityBase implements LoDendpointEntityInterface {

  /**
   * The Webform Strawberryfield LOD Endpoint ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Webform Strawberryfield LOD Endpoint label.
   *
   * @var string
   */
  protected $label = '';

  /**
   * The plugin id that will be initialized with this config.
   *
   * @var string
   */
  protected $pluginid;


  /**
   * If the plugin should be processed or not.
   *
   * @var boolean
   */
  protected $active = true;

  /**
   * Plugin specific Config
   *
   * @var array
   */
  protected $pluginconfig = [];

  /**
   * @return string
   */
  public function getPluginid(): string {
    return $this->pluginid ?: '';
  }

  /**
   * @param string $pluginid
   */
  public function setPluginid(string $pluginid): void {
    $this->pluginid = $pluginid;
  }

  /**
   * @return bool
   */
  public function isActive(): bool {
    return $this->active;
  }

  /**
   * @param bool $active
   */
  public function setActive(bool $active): void {
    $this->active = $active;
  }

  /**
   * @return array
   */
  public function getPluginconfig(): array {
    return $this->pluginconfig ?:[];
  }

  /**
   * @param array $pluginconfig
   */
  public function setPluginconfig(array $pluginconfig): void {
    $this->pluginconfig = $pluginconfig;
  }




}
