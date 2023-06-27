<?php
/**
 * Created by PhpStorm.
 * User: dpino
 * Date: 9/20/19
 * Time: 2:19 PM
 */

namespace Drupal\webform_strawberryfield\Plugin\views\display;

use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\search_api\Query\Condition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The plugin that handles an EntityReference display for Solr Search API.
 *
 * "entity_reference_display" is a custom property, used with
 * \Drupal\views\Views::getApplicableViews() to retrieve all views with a
 * 'Entity Reference' display.
 *
 * This View Display applies only to search_api_index_default_solr_index
 *
 * @ingroup views_display_plugins
 *
 * @ViewsDisplay(
 *   id = "entity_reference_solr",
 *   title = @Translation("Solr Search API Entity Reference"),
 *   admin = @Translation("Solr Search API Entity Reference Source"),
 *   help = @Translation("Selects reference-able entities for an entity reference field via the Solr Search API."),
 *   theme = "views_view",
 *   base = {
 *   "search_api_index_default_solr_index"
 *   },
 *   register_theme = FALSE,
 *   uses_menu_links = FALSE,
 *   entity_reference_display = TRUE
 * )
 */
class EntityReference extends DisplayPluginBase {

  /**x
   * {@inheritdoc}
   */
  protected $usesAJAX = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesPager = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $usesAttachments = FALSE;

  /**
   * Constructs a new EntityReference object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    // Force the style plugin to 'entity_reference_style' and the row plugin to
    // 'fields'.
    $options['style']['contains']['type'] = ['default' => 'entity_reference'];
    $options['defaults']['default']['style'] = FALSE;
    $options['row']['contains']['type'] = ['default' => 'entity_reference'];
    $options['defaults']['default']['row'] = FALSE;

    // Set the display title to an empty string (not used in this display type).
    $options['title']['default'] = '';
    $options['defaults']['default']['title'] = FALSE;

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);
    // Disable 'title' so it won't be changed from the default set in
    // \Drupal\views\Plugin\views\display\EntityReference::defineOptions.
    unset($options['title']);
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return 'entity_reference';
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    return $this->view->render($this->display['id']);
  }

  /**
   * Builds the view result as a renderable array.
   *
   * @return array
   *   Renderable array or empty array.
   */
  public function render() {
    if (!empty($this->view->result) && $this->view->style_plugin->evenEmpty()) {
      return $this->view->style_plugin->render($this->view->result);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function usesExposed() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Make sure the id field is included in the results.
    // @see \Drupal\search_api\Plugin\views\ResultRow::$lazyLoad
    // "search_api_id" is always the base field id that gets lazy resolved
    // against the getId() == entity->id on result rows.
    // But that means you can not filter against it using directly the id int.
    // Means our id's passed by \Drupal\webform_strawberryfield\Plugin\EntityReferenceSelection\ViewsSolrSelection
    // Need to have that form too.
    $id_field = $this->view->storage->get('base_field');
    $id_table = $this->view->storage->get('base_table');

    // This is weird. Since this extends DisplayPluginBase and viewsexecutable
    // Assumes that the $query is of a base type instead extending an interface
    // The Dev IDE gets can not access the real properties we have at hand
    // For this specific type of query. So this helps.
    /* @var $search_api_query \Drupal\search_api\Plugin\views\query\SearchApiQuery */
    $search_api_query = $this->view->query;

    $this->id_field_alias = $this->view->query->addField($id_table, $id_field);
    if (!empty($this->view->live_preview)) {
      // We hate blind Views on the UI. Give it a starting Match so we can
      // see the logic happening
    }

    // Make sure the id field is included in the results.
    $id_field = $this->view->storage->get('base_field');
    $id_table = $this->view->storage->get('base_table');
    $this->id_field_alias = $search_api_query->addField($id_table, $id_field);

    $options = $this->getOption('entity_reference_options');
    // Restrict the autocomplete options based on what's been typed already.
    if (isset($options['match_solr'])) {
      $style_options = $this->getOption('style');
      // See if we need to use some escape mechanism from here
      // @see \Drupal\search_api_solr\Utility\Utility
      $value = $options['match_solr'];

      // Multiple search fields are OR'd together.
      $match_condition_group = $search_api_query->createConditionGroup('OR');

      // we can't use $field_id as field.
      // We need to resolve it back to its original Solr field.
      // Build the condition using the selected search fields.
      foreach ($style_options['options']['search_fields'] as $field_id) {
        if (!empty($field_id)) {
          $realfieldname = $this->getHandlers('field')[$field_id]->field;
          // Add an OR condition for the field.
          // We use = operator since we expect fields to be ngrams indexed
          $match_condition_group->addCondition($realfieldname, $value, '=');
        }
      }
      // Removed deprecated in Search API 1.24+ string as condition group/tag.
      $search_api_query->addConditionGroup($match_condition_group);
    }
    // Add an IN condition for validation.
    if (!empty($options['ids_solr'])) {
      $search_api_query->addWhere(0, $id_field, $options['ids_solr'], 'IN');
    }
    $this->view->setItemsPerPage($options['limit']);
  }

  /**
   * {@inheritdoc}
   */
  public function validate() {
    $errors = parent::validate();
    // Verify that search fields are set up.
    $style = $this->getOption('style');
    if (!isset($style['options']['search_fields'])) {
      $errors[] = $this->t('Display "@display" needs a selected search fields to work properly. See the settings for the Entity Reference list format.', ['@display' => $this->display['display_title']]);
    }
    else {
      // Verify that the search fields used actually exist.
      $fields = array_keys($this->handlers['field']);
      foreach ($style['options']['search_fields'] as $field_alias => $enabled) {
        if ($enabled && !in_array($field_alias, $fields)) {
          $errors[] = $this->t('Display "@display" uses field %field as search field, but the field is no longer present. See the settings for the Entity Reference list format.', ['@display' => $this->display['display_title'], '%field' => $field_alias]);
        }
      }
    }
    return $errors;
  }

}
