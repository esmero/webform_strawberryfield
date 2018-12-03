<?php

namespace Drupal\webform_strawberryfield\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformInterface;
use Drupal\webform\webformSubmissionInterface;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\webform\WebformSubmissionConditionsValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\webform\WebformTokenManagerInterface;
use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\file\FileInterface;
use Drupal\Core\Field\FieldTypePluginManager;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Serialization\Yaml;
/**
 * Form submission handler for strawberry field. This handle creates new Nodes.
 *
 * Webforms using this handler will get the node creation mechanic disabled
 * while acting as a field widget to avoid collision.
 *
 * @WebformHandler(
 *   id = "strawberryFieldandNode_webform_handler",
 *   label = @Translation("A strawberryField handpicker"),
 *   category = @Translation("Form Handler"),
 *   description = @Translation("StrawberryField hand Picker"),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class strawberryFieldhandPicker extends WebformHandlerBase
{
    /**
     * @var bool
     */
    private $isWidgetDriven = FALSE;

    /**
     * The entityTypeManager factory.
     *
     * @var $entityTypeManage EntityTypeManagerInterface
     */
    protected $entityTypeManager;

    /**
     * @var \Drupal\webform\WebformTokenManagerInterface
     */
    protected $tokenManager;
    /**
     * @var \Drupal\Core\File\FileSystemInterface
     */
    protected $fileSystem;

    /**
     * @var \Drupal\file\FileUsage\FileUsageInterface
     */
    protected $fileUsage;
    /**
     * @var \Drupal\Component\Transliteration\TransliterationInterface
     */
    protected $transliteration;
    /**
     * @var \Drupal\Core\Language\LanguageManagerInterface
     */
    protected $languageManager;

    /**
     * @var \Drupal\Core\Field\FieldTypePluginManager
     */
    protected $fieldTypePluginManager;

    /**
     * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
     */
    protected $entityTypeBundleInfo;

    /**
     * strawberryFieldharvester constructor.
     * @param array $configuration
     * @param $plugin_id
     * @param $plugin_definition
     * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
     * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
     * @param \Drupal\webform\WebformSubmissionConditionsValidatorInterface $conditions_validator
     * @param \Drupal\webform\WebformTokenManagerInterface $token_manager
     * @param \Drupal\Core\File\FileSystemInterface $file_system
     * @param $file_usage
     * @param \Drupal\Component\Transliteration\TransliterationInterface $transliteration
     * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelFactoryInterface $logger_factory, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, WebformSubmissionConditionsValidatorInterface $conditions_validator, WebformTokenManagerInterface $token_manager, FileSystemInterface $file_system, FileUsageInterface $file_usage, TransliterationInterface $transliteration, LanguageManagerInterface $language_manager, FieldTypePluginManager $fieldtype_pluginmanager, EntityTypeBundleInfoInterface $entitytype_bundleinfo) {
        parent::__construct($configuration, $plugin_id, $plugin_definition, $logger_factory, $config_factory,  $entity_type_manager,  $conditions_validator);
        $this->entityTypeManager = $entity_type_manager;
        $this->tokenManager = $token_manager;
        $this->fileSystem = $file_system;
        $this->fileUsage = $file_usage;
        $this->transliteration = $transliteration;
        $this->languageManager = $language_manager;
        $this->fieldTypePluginManager = $fieldtype_pluginmanager;
        $this->entityTypeBundleInfo = $entitytype_bundleinfo;
    }


    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
          $configuration,
          $plugin_id,
          $plugin_definition,
          $container->get('logger.factory'),
          $container->get('config.factory'),
          $container->get('entity_type.manager'),
          $container->get('webform_submission.conditions_validator'),
          $container->get('webform.token_manager'),
          $container->get('file_system'),
          // Soft depend on "file" module so this service might not be available.
          $container->get('file.usage'),
          $container->get('transliteration'),
          $container->get('language_manager'),
          $container->get('plugin.manager.field.field_type'),
          $container->get('entity_type.bundle.info')
        );
    }


    public function getSummary() {
        return [
          '#markup' => Yaml::encode($this->configuration),
          '#prefix' => '<pre>',
          '#suffix' => '<pre>',
        ];
    }

    /**
     * @return bool
     */
    public function isWidgetDriven(): bool
    {
        return $this->isWidgetDriven;
    }

    /**
     * @param bool $isWidgetDriven
     */
    public function setIsWidgetDriven(bool $isWidgetDriven): void
    {
        $this->isWidgetDriven = $isWidgetDriven;
    }



    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration()
    {
        return [
          'bundles' => [],
          'fields' => [],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {

        $this->applyFormStateToConfiguration($form_state);
        $bundle_options = [];
        //Only node bundles with a strawberry field are allows
        // @TODO allow in the future other entities, not only nodes
        // @TODO on bundle delete, remove reference here too!

        // Define #ajax callback.
        $ajax = [
          'callback' => [get_class($this), 'ajaxCallback'],
          'wrapper' => 'webform-handler-ajax-container',
        ];

        /**************************************************************************/
        // Node types with Strawberry fields
        /**************************************************************************/
        $strawberry_field_class = $class = $this->fieldTypePluginManager->getPluginClass('strawberryfield_field');

        $bundles = $this->entityTypeBundleInfo->getBundleInfo('node');
        foreach ($bundles as $bundle => $bundle_info) {
            $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);

            $is_ripe = false;
            /** @var FieldDefinitionInterface[] $fields */
            foreach ($fields as $allfield) {
                $class = $allfield->getItemDefinition()->getClass();
                $is_ripe = ($class === $strawberry_field_class) || is_subclass_of(
                    $class,
                    $strawberry_field_class
                  );
            }
            if ($is_ripe) {
                $bundle_options[$bundle] = $bundle_info['label'];
            }
        }

        $form['bundles'] = [
          '#title' => $this->t('Node types this webform can manipulate'),
          '#type' => 'checkboxes',
          '#options' => $bundle_options,
          '#default_value' => $this->configuration['bundles'],
          '#required'=> true,
          '#weight' => -9,
          '#ajax' => $ajax

        ];

        if (empty($bundle_options)) {
            $access = FALSE;
        }
        else {
            $access = TRUE;
        }

        $form['container'] = [
          '#type' => 'container',
          '#attributes' => ['id' => 'webform-handler-ajax-container'],
          '#weight' => 100,
        ];



        //@see \Drupal\content_moderation\Form\ContentModerationConfigureForm::buildConfigurationForm
        // as alternative option for this

        /**************************************************************************/
        // Fields.
        /**************************************************************************/

        // Get elements options.
        $element_options = [];
        $elements = $this->webform->getElementsInitializedFlattenedAndHasValue();
        foreach ($elements as $element_key => $element) {
            $element_options[$element_key] = (isset($element['#title'])) ? $element['#title'] : $element_key;
        }

        // Get field options.

        if ($this->configuration['bundles']) {
            $bundles = is_array($this->configuration['bundles']) ? $this->configuration['bundles'] : [$this->configuration['bundles']];
            foreach ($bundles as $bundle => $value) {
                if ($value) {
                    /** @var FieldDefinitionInterface[] $fields */
                    // @TODO add as dependency injection too. How many?
                    $fields = \Drupal::service('entity_field.manager')
                      ->getFieldDefinitions('node', $bundle);
                    $field_options = [];
                    foreach ($fields as $field_name => $field) {

                        $field_options[$field_name] = $field->getLabel();
                    }
                    //@TODO make it work per bundle. Mapping is being set same for all.
                    $form['container'][$bundle]['fields'] = [
                      '#type' => 'webform_mapping',
                      '#title' => $this->t('Fields for @bundle', ['@bundle' => $bundle]) ,
                      '#description' => $this->t(
                        'Please select which fields webform submission data should be mapped to'
                      ),
                      '#description_display' => 'before',
                      '#default_value' => isset($this->configuration['fields']) ? $this->configuration['fields'] : null,
                      '#required' => true,
                      '#parents' => ['settings', 'fields'],
                      '#source' => $element_options,
                      '#destination' => $field_options,
                      '#weight' => 200,
                    ];
                }
            }
        }


        return parent::buildConfigurationForm($form, $form_state);

    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
        parent::submitConfigurationForm($form, $form_state);
        $this->configuration['bundles'] = $form_state->getValue('bundles');
        $this->configuration['fields'] = $form_state->getValue('fields');
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

        return NestedArray::getValue($form, ['settings', 'container']);
    }

}
