<?php


namespace Drupal\webform_strawberryfield\Controller;

use Drupal\ami\AmiUtilityService;
use Drupal\Core\Controller\ControllerBase;
use Drupal\strawberryfield\StrawberryfieldUtilityService;
use Drupal\webform_strawberryfield\Entity\LoDendpointEntity;
use Drupal\webform_strawberryfield\Plugin\CustomLoDendpointManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\ContentEntityInterface;


/**
 * Defines a route controller for CSV based Vocab autocomplete form elements.
 */
class CustomLoDendpointController extends ControllerBase {

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Custom LOD ndpoint Plugin Manager.
   *
   * @var CustomLoDendpointManager;
   */
  protected $customLoDendpointManager;



  /**
   * Constructs a Custom LoD Controller.
   *
   * @param \Drupal\ami\AmiUtilityService                         $ami_utility
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface        $entity_type_manager
   * @param \Drupal\strawberryfield\StrawberryfieldUtilityService $sbf_utility
   * @param \Drupal\webform_strawberryfield\Plugin\CustomLoDendpointManager $CustomLoDendpointManager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CustomLoDendpointManager $CustomLoDendpointManager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->customLoDendpointManager = $CustomLoDendpointManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('webform_strawberryfield.custom_lod_endpoint_manager')
    );
  }
  /**
   * Handler for AMI Set CSV autocomplete request.
   *
   * Filters against Labels
   *
   */
  public function handleAutocomplete(Request $request, LoDendpointEntity $custom_lod_entity_id) {

    $loDendpointEntityPluginID = $custom_lod_entity_id->getPluginid();
    $loDendpointEntityConfig = $custom_lod_entity_id->getPluginconfig();
    if (!$custom_lod_entity_id->isActive()) {
      return [];
    }

    $results = [];
    $input = $request->query->get('q');
    $input = Xss::filter($input);
    /* @var \Drupal\webform_strawberryfield\Plugin\CustomLoDendpointInterface $plugin_instance */
    if (strlen($input) > 0) {
      $plugin_instance = $this->customLoDendpointManager->createInstance($loDendpointEntityPluginID, $loDendpointEntityConfig);
      if ($plugin_instance) {
        $results = $plugin_instance->handleQuery($input);
      }
    }

    return new JsonResponse($results);
  }
}
