<?php

namespace Drupal\webform_strawberryfield\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Element\WebformAjaxElementTrait;
use Drupal\webform\Plugin\WebformHandlerMessageInterface;
use Drupal\webform\WebformRequestInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform_strawberryfield\Plugin\WebformHandler\strawberryFieldharvester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBase;

class WebformSubmissionToADO extends FormBase {

  use WebformAjaxElementTrait;

  /**
   * A webform submission.
   *
   * @var \Drupal\webform\WebformSubmissionInterface
   */
  protected $webformSubmission;

  /**
   * The source entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'webform_strawberryfield_submission_toado';
  }

  /**
   * The webform request handler.
   *
   * @var \Drupal\webform\WebformRequestInterface
   */
  protected $requestHandler;

  /**
   * Constructs a WebformSubmissionToADO object.
   *
   * @param \Drupal\webform\WebformRequestInterface $request_handler
   *   The webform request handler.
   */
  public function __construct(WebformRequestInterface $request_handler) {
    $this->requestHandler = $request_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('webform.request')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission = NULL) {
    $this->webformSubmission = $webform_submission;

    // Apply variants to the webform.
    $webform = $webform_submission->getWebform();
    $webform->applyVariants($webform_submission);

    // Get header.
    $header = [];
    $header['title'] = [
      'data' => $this->t('Title / Description'),
    ];
    $header['id'] = [
      'data' => $this->t('ID'),
      'class' => [RESPONSIVE_PRIORITY_LOW],
    ];
    $header['summary'] = [
      'data' => $this->t('summary'),
      'class' => [RESPONSIVE_PRIORITY_LOW],
    ];
    $header['status'] = [
      'data' => $this->t('Status'),
      'class' => [RESPONSIVE_PRIORITY_LOW],
    ];

    // Add Create ADO button.
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create or Update an ADO (Node)'),
    ];

    // Add submission navigation.
    $source_entity = $this->requestHandler->getCurrentSourceEntity('webform_submission');
    $form['navigation'] = [
      '#type' => 'webform_submission_navigation',
      '#webform_submission' => $webform_submission,
      '#weight' => -20,
    ];
    $form['information'] = [
      '#type' => 'webform_submission_information',
      '#webform_submission' => $webform_submission,
      '#source_entity' => $source_entity,
      '#weight' => -19,
    ];
    $form['#attached']['library'][] = 'webform/webform.admin';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    foreach ($this->webformSubmission->getWebform()->getHandlers() as $id => $webform_handler) {
      if (($webform_handler instanceof strawberryFieldharvester) && $webform_handler->isEnabled()) {
        $configuration = $webform_handler->getConfiguration();

        if (isset($configuration['settings']['ado_crud_enabled']) && $configuration['settings']['ado_crud_enabled']) {
          $bundle = $configuration['settings']['ado_settings']['bundles'];
          $webform_handler->postSave($this->webformSubmission, FALSE);
          break;
          $this->messenger()->addStatus($this->t('New ADO of type @bundle for this submission created',['@bundle' => $bundle]));
        }
      }
    }
  }
}