<?php

namespace Drupal\webform_strawberryfield\Plugin\WebformHandler;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\TempStoreException;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\webformSubmissionInterface;


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
use Drupal\strawberryfield\Tools\Ocfl\OcflHelper;


/**
 * Form submission handler when Webform is used as strawberyfield widget.
 *
 * @WebformHandler(
 *   id = " strawberryField_webform_handler",
 *   label = @Translation("A strawberryField harvester"),
 *   category = @Translation("Form Handler"),
 *   description = @Translation("StrawberryField Harvester"),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class strawberryFieldharvester extends WebformHandlerBase {

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
   * Internal storage for overriden Webform Settings
   *
   * @var array
   */
  protected $customWebformSettings = [];

  /**
   * strawberryFieldharvester constructor.
   *
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
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerChannelFactoryInterface $logger_factory,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    WebformSubmissionConditionsValidatorInterface $conditions_validator,
    WebformTokenManagerInterface $token_manager,
    FileSystemInterface $file_system,
    FileUsageInterface $file_usage,
    TransliterationInterface $transliteration,
    LanguageManagerInterface $language_manager
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $logger_factory,
      $config_factory,
      $entity_type_manager,
      $conditions_validator
    );
    $this->entityTypeManager = $entity_type_manager;
    $this->tokenManager = $token_manager;
    $this->fileSystem = $file_system;
    $this->fileUsage = $file_usage;
    $this->transliteration = $transliteration;
    $this->languageManager = $language_manager;
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
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('webform_submission.conditions_validator'),
      $container->get('webform.token_manager'),
      $container->get('file_system'),
      // Soft depend on "file" module so this service might not be available.
      $container->get('file.usage'),
      $container->get('transliteration'),
      $container->get('language_manager')
    );
  }

  /**
   * @return bool
   */
  public function isWidgetDriven(): bool {
    return $this->isWidgetDriven;
  }

  /**
   * @param bool $isWidgetDriven
   */
  public function setIsWidgetDriven(bool $isWidgetDriven): void {
    $this->isWidgetDriven = $isWidgetDriven;
  }


  /**
   * {@inheritdoc}
   */
  public function postLoad(WebformSubmissionInterface $webform_submission) {
    parent::postLoad(
      $webform_submission
    ); // TODO: Change the autogenerated stub

  }


  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    // @TODO this will be sent to Esmero.
    return [
      'submission_url' => 'https://api.example.org/SOME/ENDPOINT',
      'upload_scheme' => 'public://',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $configuration = $this->getConfiguration();
    $settings = $configuration['settings'];
    return [
        '#settings' => $settings,
      ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state
  ) {
    $form['submission_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secondary submission URL to api.example.org'),
      '#description' => $this->t('The URL to post the submission data to.'),
      '#default_value' => $this->configuration['submission_url'],
      '#required' => TRUE,
    ];
    $scheme_options = OcflHelper::getVisibleStreamWrappers();
    $form['upload_scheme'] = [
      '#type' => 'radios',
      '#title' => $this->t('Permanent destination for uploaded files'),
      '#description' => $this->t(
        'The Permanent URI Scheme destination for uploaded files.'
      ),
      '#default_value' => $this->configuration['upload_scheme'],
      '#required' => TRUE,
      '#options' => $scheme_options,
    ];

    return $form;
  }

  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state
  ) {
    parent::submitConfigurationForm($form, $form_state);
    $this->applyFormStateToConfiguration($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(WebformSubmissionInterface $webform_submission) {

    $values = $webform_submission->getData();
    $cleanvalues = $values;
    $processedcleanvalues = [];
    // Helper structure to keep elements that map to entities around
    $entity_mapping_structure = isset($cleanvalues['ap:entitymapping']) ? $cleanvalues['ap:entitymapping'] : [];
    // Check which elements carry files around
    $allelements = $webform_submission->getWebform()->getElementsManagedFiles();
    foreach ($allelements as $element) {
      $originalelement = $webform_submission->getWebform()->getElement(
        $element
      );
      // Track what fields map to file entities.
      $entity_mapping_structure['entity:file'][] = $originalelement['#webform_key'];
      // Process each managed files field.
      $processedcleanvaluesforfield = $this->processFileField(
        $originalelement,
        $webform_submission,
        $cleanvalues
      );
      // Merge since different fields can contribute to same as:filetype structure.
      $processedcleanvalues = array_merge_recursive(
        $processedcleanvalues,
        $processedcleanvaluesforfield
      );
    }
    // Check also which elements carry entity references around
    // @see https://www.drupal.org/project/webform/issues/3067958
    if (isset($entity_mapping_structure['entity:node'])) {
      //@TODO change this stub. Get every element that extends Drupal\webform\Plugin\WebformElementEntityReferenceInterface()
      $entity_mapping_structure['entity:node'] = array_unique(
        $entity_mapping_structure['entity:node'],
        SORT_STRING
      );
    }

    if (isset($entity_mapping_structure['entity:file'])) {
      $entity_mapping_structure['entity:file'] = array_unique(
        $entity_mapping_structure['entity:file'],
        SORT_STRING
      );
    }
    // Distribute all processed AS values for each field into its final JSON
    // Structure, e.g as:image, as:application, as:documents, etc.
    foreach ($processedcleanvalues as $askey => $info) {
      //@TODO ensure non managed files inside structure are preserved.
      //Could come from another URL only field or added manually by some
      // Advanced user.
      $cleanvalues[$askey] = $info;
    }
    $cleanvalues['ap:entitymapping'] = $entity_mapping_structure;

    if (isset($values["strawberry_field_widget_state_id"])) {

      $this->setIsWidgetDriven(TRUE);
      /* @var $tempstore \Drupal\Core\TempStore\PrivateTempStore */
      $tempstore = \Drupal::service('tempstore.private')->get('archipel');

      unset($cleanvalues["strawberry_field_widget_state_id"]);
      unset($cleanvalues["strawberry_field_stored_values"]);

      // That way we keep track who/what created this.
      $cleanvalues["strawberry_field_widget_id"] = $this->getWebform()->id();
      // Set data back to the Webform submission so we don't keep track
      // of the strawberry_field_widget_state_id if the submission is also saved
      $webform_submission->setData($cleanvalues);

      $cleanvalues = json_encode($cleanvalues, JSON_PRETTY_PRINT);

      try {
        $tempstore->set(
          $values["strawberry_field_widget_state_id"],
          $cleanvalues
        );
      } catch (TempStoreException $e) {
        $this->messenger()->addError(
          $this->t(
            'Sorry, we have issues writing metadata to your session storage. Please reload this form and/or contact your system admin.'
          )
        );
        $this->loggerFactory->get('archipelago')->error(
          'Webform @webformid can not write to temp storage! with error @message. Attempted Metadata input was <pre><code>%data</code></pre>',
          [
            '@webformid' => $this->getWebform()->id(),
            '%data' => print_r($webform_submission->getData(), TRUE),
            '@error' => $e->getMessage(),
          ]
        );
      }


    }
    elseif ($this->IsWidgetDriven()) {
      $this->messenger()->addError(
        $this->t(
          'We lost TV reception in the middle of the match...Please contact your system admin.'
        )
      );
      $this->loggerFactory->get('archipelago')->error(
        'Webform @webformid lost connection to temp storage and its Widget!. No Widget State id present. Attempted Metadata input was <pre><code>%data</code></pre>',
        [
          '@webformid' => $this->getWebform()->id(),
          '%data' => print_r($webform_submission->getData(), TRUE),
        ]
      );
    }

    parent::preSave($webform_submission); // TODO: Change the autogenerated stub
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(
    array &$form,
    FormStateInterface $form_state,
    WebformSubmissionInterface $webform_submission
  ) {
    $values = $webform_submission->getData();

    if ((!isset($values["strawberry_field_widget_state_id"]) || empty($values["strawberry_field_widget_state_id"])) && $this->IsWidgetDriven(
      )) {
      $this->messenger()->addError(
        $this->t(
          'Sorry, we have issues reading your session storage identifier. Error was logged. Please reload this form and/or contact your system admin.'
        )
      );

      $this->loggerFactory->get('archipelago')->error(
        'Webform @webformid lost connection to temp storage!. No Widget State id present. Attempted Metadata input was <pre><code>%data</code></pre>',
        [
          '@webformid' => $this->getWebform()->id(),
          '%data' => print_r($webform_submission->getData(), TRUE),
        ]
      );
    }
    // All data is available here $webform_submission->getData()));
    // @TODO what should be validated here?
    parent::validateForm(
      $form,
      $form_state,
      $webform_submission
    ); // TODO: Change the autogenerated stub
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(
    array &$form,
    FormStateInterface $form_state,
    WebformSubmissionInterface $webform_submission
  ) {
    $values = $webform_submission->getData();

    if (isset($values["strawberry_field_widget_state_id"])) {
      $this->setIsWidgetDriven(TRUE);
    }
    // @TODO add a full-blown values cleaner
    // @TODO add the webform name used to create this as additional KEY
    // @TODO make sure widget can read that too.
    // @If Widget != setup form, ask for User feedback
    // @TODO, i need to alter node submit handler to add also the
    // Entities full URL as an @id to the top of the saved JSON.
    // FUN!
    // Get the URL to post the data to.
    // @todo esmero a.k.a as Fedora-mockingbird
    $post_url = $this->configuration['submission_url'];
  }

  /**
   * Process temp files and give them SBF structure
   *
   * @param array $element
   *   An associative array containing the file webform element.
   * @param \Drupal\webform\webformSubmissionInterface $webform_submission
   * @param $cleanvalues
   *
   * @return array
   *   Associative array keyed by AS type with binary info.
   */
  public function processFileField(
    array $element,
    WebformSubmissionInterface $webform_submission,
    $cleanvalues
  ) {

    $key = $element['#webform_key'];
    $type = $element['#type'];
    // Equivalent of getting original data from an node entity
    $original_data = $webform_submission->getOriginalData();
    $processedAsValues = [];

    $value = isset($cleanvalues[$key]) ? $cleanvalues[$key] : [];
    $fids = (is_array($value)) ? $value : [$value];

    $original_value = isset($original_data[$key]) ? $original_data[$key] : [];
    $original_fids = (is_array(
      $original_value
    )) ? $original_value : [$original_value];

    // Delete the old file uploads?
    // @TODO build some cleanup logic here. Could be moved to attached field hook.
    // Issue with this approach is that it is 100% webform dependant
    // Won't apply in the same way if using direct JSON input and node save.

    $delete_fids = array_diff($original_fids, $fids);

    // @TODO what do we do with removed files?
    // Idea. Check the fileUsage. If there is still some other than this one
    // don't remove.
    // @see \Drupal\webform\Plugin\WebformElement\WebformManagedFileBase::deleteFiles

    // Exit if there is no fids.
    if (empty($fids)) {
      return $processedAsValues;
    }

    /* @see \Drupal\strawberryfield\StrawberryfieldFilePersisterService */
    $processedAsValues = \Drupal::service('strawberryfield.file_persister')
      ->generateAsFileStructure($fids, $key, $cleanvalues);
    return $processedAsValues;

  }

  /**
   * {@inheritdoc}
   */
  public function confirmForm(
    array &$form,
    FormStateInterface $form_state,
    WebformSubmissionInterface $webform_submission
  ) {
    // We really want to avoid being redirected. This is how it is done.
    //@TODO manage file upload if there is no submission save handler
    //@ see \Drupal\webform\Plugin\WebformElement\WebformManagedFileBase::postSave

    $form_state->disableRedirect();
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessConfirmation(array &$variables) {
    if ($this->isWidgetDriven()) {
      unset($variables['back']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function overrideSettings(
    array &$settings,
    WebformSubmissionInterface $webform_submission
  ) {
    // We can not check if they are already overridden
    // Because this is acting as an alter
    // But never ever touches the Webform settings.
    $settings = $this->customWebformSettings + $settings;
    parent::overrideSettings(
      $settings,
      $webform_submission
    );
  }

  /**
   * {@inheritdoc}
   */
  public function preCreate(array &$values) {
    if (isset($values['strawberryfield:override']) && !empty($values['strawberryfield:override']) && empty($this->customWebformSettings)) {
      $this->customWebformSettings = $values['strawberryfield:override'];
    }
    parent::preCreate($values);
  }

  /**
   * Gets valid upload stream wrapper schemes.
   *
   * @param array $element
   *
   * @return mixed|string
   */
  protected function getUriSchemeForManagedFile(array $element) {
    if (isset($element['#uri_scheme'])) {
      return $element['#uri_scheme'];
    }
    $scheme_options = \Drupal\webform\Plugin\WebformElement\WebformManagedFileBase::getVisibleStreamWrappers(
    );
    if (isset($scheme_options['private'])) {
      return 'private';
    }
    elseif (isset($scheme_options['public'])) {
      return 'public';
    }
    else {
      return 'private';
    }
  }

}
