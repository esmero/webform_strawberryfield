<?php
/**
 * Created by PhpStorm.
 * User: dpino
 * Date: 9/19/19
 * Time: 6:42 PM
 */

namespace Drupal\webform_strawberryfield\Plugin\EntityReferenceSelection;

use Drupal\Core\DependencyInjection\DeprecatedServicePropertyTrait;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Component\Utility\Xss;
use Drupal\views\Render\ViewsRenderPipelineMarkup;

/**
 * Plugin implementation of the 'selection' entity_reference.
 *
 * @EntityReferenceSelection(
 *   id = "solr_views",
 *   label = @Translation("Views Solr: Filter by an entity reference view"),
 *   entity_types = {"node"},
 *   group = "solr_views",
 *   weight = 1
 * )
 */
class ViewsSolrSelection extends SelectionPluginBase implements ContainerFactoryPluginInterface {

  use DeprecatedServicePropertyTrait;

  /**
   * {@inheritdoc}
   */
  protected $deprecatedProperties = ['entityManager' => 'entity.manager'];

  /**
   * The loaded View object.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected $view;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new ViewsSelection object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandlerInterface $module_handler,
    AccountInterface $current_user,
    RendererInterface $renderer = NULL
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->currentUser = $current_user;
    if (!$renderer) {
      @trigger_error(
        'Calling ViewsSelection::__construct() with the $renderer argument is supported in drupal:8.7.0 and will be required before drupal:9.0.0.',
        E_USER_DEPRECATED
      );
      $renderer = \Drupal::service('renderer');
    }
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
        'view' => [
          'view_name' => NULL,
          'display_name' => NULL,
          'arguments' => [],
        ],
      ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state
  ) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $view_settings = $this->getConfiguration()['view'];
    $displays = Views::getApplicableViews('entity_reference_display');
    // Filter views that list the entity type we want, and group the separate
    // displays by view.
    // $entity_type = $this->entityTypeManager->getDefinition($this->configuration['target_type']);
    $view_storage = $this->entityTypeManager->getStorage('view');

    $options = [];
    foreach ($displays as $data) {
      list($view_id, $display_id) = $data;
      $view = $view_storage->load($view_id);
      $display = $view->get('display');
      $options[$view_id . ':' . $display_id] = $view_id . ' - ' . $display[$display_id]['display_title'];
    }

    // The value of the 'view_and_display' select below will need to be split
    // into 'view_name' and 'view_display' in the final submitted values, so
    // we massage the data at validate time on the wrapping element (not
    // ideal).
    $form['view']['#element_validate'] = [
      [
        get_called_class(),
        'settingsFormValidate',
      ],
    ];

    if ($options) {
      $default = !empty($view_settings['view_name']) ? $view_settings['view_name'] . ':' . $view_settings['display_name'] : NULL;
      $form['view']['view_and_display'] = [
        '#type' => 'select',
        '#title' => $this->t('View used to select the entities'),
        '#required' => TRUE,
        '#options' => $options,
        '#default_value' => $default,
        '#description' => '<p>' . $this->t(
            'Choose the view and display that select the entities that can be referenced.<br />Only views with a display of type "Entity Reference" are eligible.'
          ) . '</p>',
      ];

      $default = !empty($view_settings['arguments']) ? implode(
        ', ',
        $view_settings['arguments']
      ) : '';
      $form['view']['arguments'] = [
        '#type' => 'textfield',
        '#title' => $this->t('View arguments'),
        '#default_value' => $default,
        '#required' => FALSE,
        '#description' => $this->t(
          'Provide a comma separated list of arguments to pass to the view.'
        ),
      ];
    }
    else {
      if ($this->currentUser->hasPermission(
          'administer views'
        ) && $this->moduleHandler->moduleExists('views_ui')) {
        $form['view']['no_view_help'] = [
          '#markup' => '<p>' . $this->t(
              'No eligible views were found. <a href=":create">Create a view</a> with an <em>Entity Reference</em> display, or add such a display to an <a href=":existing">existing view</a>.',
              [
                ':create' => Url::fromRoute('views_ui.add')->toString(),
                ':existing' => Url::fromRoute('entity.view.collection')
                  ->toString(),
              ]
            ) . '</p>',
        ];
      }
      else {
        $form['view']['no_view_help']['#markup'] = '<p>' . $this->t(
            'No eligible views were found.'
          ) . '</p>';
      }
    }
    return $form;
  }

  /**
   * Initializes a view.
   *
   * @param string|null $match
   *   (Optional) Text to match the label against. Defaults to NULL.
   * @param string $match_operator
   *   (Optional) The operation the matching should be done with. Defaults
   *   to "CONTAINS".
   * @param int $limit
   *   Limit the query to a given number of items. Defaults to 0, which
   *   indicates no limiting.
   * @param array|null $ids
   *   Array of entity IDs. Defaults to NULL.
   *
   * @return bool
   *   Return TRUE if the view was initialized, FALSE otherwise.
   */
  protected function initializeView(
    $match = NULL,
    $match_operator = 'CONTAINS',
    $limit = 0,
    $ids = NULL
  ) {
    $view_name = $this->getConfiguration()['view']['view_name'];
    $display_name = $this->getConfiguration()['view']['display_name'];

    // Check that the view is valid and the display still exists.
    $this->view = Views::getView($view_name);
    if (!$this->view || !$this->view->access($display_name)) {
      \Drupal::messenger()->addWarning(
        t(
          'The reference view %view_name cannot be found.',
          ['%view_name' => $view_name]
        )
      );
      return FALSE;
    }

    $this->view->setDisplay($display_name);

    // Pass options to the display handler to make them available later.
    // We can not pass 'match' as option since \Drupal\views\Plugin\views\display\EntityReference::query
    // Tries to deal with this as it was an SQL Query!
    $lang_code = \Drupal::languageManager()->getCurrentLanguage()->getId();
    // We are aiming at LoD here. We want to allow other than default language
    // To be valid too.
    $ids_solr = NULL;
    if (!empty($ids)) {
      foreach ($ids as $id) {
        $ids_solr[] = 'entity:node/' . $id . ':' . $lang_code;
        $ids_solr[] = 'entity:node/' . $id . ':' . 'en';
        $ids_solr[] = 'entity:node/' . $id . ':' . LanguageInterface::LANGCODE_NOT_SPECIFIED;
        $ids_solr[] = 'entity:node/' . $id . ':' . LanguageInterface::LANGCODE_NOT_APPLICABLE;
      }
      // Remove doubles.
      $ids_solr = array_unique($ids_solr);
    }

    $entity_reference_options = [
      'match_operator_solr' => $match_operator,
      'match_solr' => $match,
      'limit' => $limit,
      'ids_solr' => $ids_solr,
      'ids' => $ids,
    ];

    $this->view->displayHandlers->get($display_name)->setOption(
      'entity_reference_options',
      $entity_reference_options
    );
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities(
    $match = NULL,
    $match_operator = 'CONTAINS',
    $limit = 0
  ) {
    $entities = [];
    if ($display_execution_results = $this->getDisplayExecutionResults(
      $match,
      $match_operator,
      $limit
    )) {
      $entities = $this->stripAdminAndAnchorTagsFromResults(
        $display_execution_results
      );
    }

    return $entities;
  }

  /**
   * Fetches the results of executing the display.
   *
   * @param string|null $match
   *   (Optional) Text to match the label against. Defaults to NULL.
   * @param string $match_operator
   *   (Optional) The operation the matching should be done with. Defaults
   *   to "CONTAINS".
   * @param int $limit
   *   Limit the query to a given number of items. Defaults to 0, which
   *   indicates no limiting.
   * @param array|null $ids
   *   Array of entity IDs. Defaults to NULL.
   *
   * @return array
   *   The results.
   */
  protected function getDisplayExecutionResults(
    string $match = NULL,
    string $match_operator = 'CONTAINS',
    int $limit = 0,
    array $ids = NULL
  ): array {
    $display_name = $this->getConfiguration()['view']['display_name'];
    $arguments = $this->getConfiguration()['view']['arguments'];
    $results = [];

    if ($this->initializeView($match, $match_operator, $limit)) {
      $results = $this->view->executeDisplay($display_name, $arguments);
    }
    return $results ?? [];
  }

  /**
   * Strips all admin and anchor tags from a result list.
   *
   * These results are usually displayed in an autocomplete field, which is
   * surrounded by anchor tags. Most tags are allowed inside anchor tags, except
   * for other anchor tags.
   *
   * @param array $results
   *   The result list.
   *
   * @return array
   *   The provided result list with anchor tags removed.
   */
  protected function stripAdminAndAnchorTagsFromResults(array $results): array {
    $allowed_tags = Xss::getAdminTagList();
    if (($key = array_search('a', $allowed_tags)) !== FALSE) {
      unset($allowed_tags[$key]);
    }

    $stripped_results = [];

    foreach ($results as $id => $row) {
      /* @var $entityadapter EntityAdapter */
      if (isset($row['#row'])) {
        // Means field render or default
        $entityadapter = $row['#row']->_object;
        $entity = $entityadapter->getValue();
      }
      elseif (isset($row['#node'])) {
        $entity = $row['#node'];
      }
      else {
        // We don't know what we got, but its no content entity.
        break;
      }

      $stripped_results[$entity->bundle()][$entity->id(
      )] = ViewsRenderPipelineMarkup::create(
        Xss::filter($this->renderer->renderPlain($row), $allowed_tags)
      );
    }
    return $stripped_results;
  }

  /**
   * {@inheritdoc}
   */
  public function countReferenceableEntities(
    $match = NULL,
    $match_operator = 'CONTAINS'
  ) {
    $this->getReferenceableEntities($match, $match_operator);
    return $this->view->pager->getTotalItems();
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableEntities(array $ids) {
    // This class differs from the SQL implemententation
    // Because we need to transform Solr entity:node/9:en into 9
    $display_name = $this->getConfiguration()['view']['display_name'];
    $arguments = $this->getConfiguration()['view']['arguments'];
    $result = [];
    if ($this->initializeView(NULL, 'CONTAINS', 0, $ids)) {
      // Get the results.
      $entities = $this->view->executeDisplay($display_name, $arguments);
      $result = array_keys($entities);
      foreach ($result as &$id) {
        // We want to convert entity:node/9:en into 9
        $parts = explode(':', str_replace('entity:node/', '', $id));
        $id = $parts[0];
      }
    }
    return $result;
  }

  /**
   * Element validate; Check View is valid.
   */
  public static function settingsFormValidate(
    $element,
    FormStateInterface $form_state,
    $form
  ) {
    // Split view name and display name from the 'view_and_display' value.
    if (!empty($element['view_and_display']['#value'])) {
      list($view, $display) = explode(
        ':',
        $element['view_and_display']['#value']
      );
    }
    else {
      $form_state->setError(
        $element,
        t('The views entity selection mode requires a view.')
      );
      return;
    }

    // Explode the 'arguments' string into an actual array. Beware, explode()
    // turns an empty string into an array with one empty string. We'll need an
    // empty array instead.
    $arguments_string = trim($element['arguments']['#value']);
    if ($arguments_string === '') {
      $arguments = [];
    }
    else {
      // array_map() is called to trim whitespaces from the arguments.
      $arguments = array_map('trim', explode(',', $arguments_string));
    }

    $value = [
      'view_name' => $view,
      'display_name' => $display,
      'arguments' => $arguments,
    ];
    $form_state->setValueForElement($element, $value);
  }

}
