<?php


namespace Drupal\webform_strawberryfield\Controller;

use Drupal\ami\AmiUtilityService;
use Drupal\Core\Controller\ControllerBase;
use Drupal\strawberryfield\StrawberryfieldUtilityService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\ContentEntityInterface;


/**
 * Defines a route controller for CSV based Vocab autocomplete form elements.
 */
class RowAutocompleteController extends ControllerBase {

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\ami\AmiUtilityService
   */
  protected $AmiUtilityService;

  /**
   * The Strawberry Field Utility Service.
   *
   * @var \Drupal\strawberryfield\StrawberryfieldUtilityService
   */
  protected $strawberryfieldUtility;

  /**
   * Constructs a AmiMultiStepIngestBaseForm.
   *
   * @param \Drupal\ami\AmiUtilityService                         $ami_utility
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface        $entity_type_manager
   * @param \Drupal\strawberryfield\StrawberryfieldUtilityService $sbf_utility
   */
  public function __construct(AmiUtilityService $ami_utility,  EntityTypeManagerInterface $entity_type_manager, StrawberryfieldUtilityService $sbf_utility) {
    $this->entityTypeManager = $entity_type_manager;
    $this->AmiUtilityService = $ami_utility;
    $this->strawberryfieldUtility = $sbf_utility;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ami.utility'),
      $container->get('entity_type.manager'),
      $container->get('strawberryfield.utility')
    );
  }
  /**
   * Handler for AMI Set CSV autocomplete request.
   *
   * Filters against Labels
   *
   */
  public function handleAutocomplete(Request $request, ContentEntityInterface $node, $label_header, $url_header) {
    $results = [];
    $input = $request->query->get('q');
    $input = Xss::filter($input);
    $label_header = strtolower($label_header);
    $url_header = strtolower($url_header);

    // Find a CSV file in this ADO.
    // Get the typed string from the URL, if it exists.
    if (!$input) {
      return new JsonResponse($results);
    }
    $file = null;
    if ($sbf_fields = \Drupal::service('strawberryfield.utility')->bearsStrawberryfield($node)) {
      $files = $node->get('field_file_drop')->getValue();
      foreach($files as $offset => $fileinfo) {
        /** @var \Drupal\file\FileInterface $file|null */
        $file = $this->entityTypeManager
          ->getStorage('file')
          ->load($fileinfo['target_id']);
        if ($file) {
          $file->getMimeType() == 'text/csv';
          break;
        }
      }
      if ($file) {
        $file_data_all = $this->AmiUtilityService->csv_read($file, 0, 0, TRUE);
        $column_keys = $file_data_all['headers'] ?? [];
        $label_original_index = array_search($label_header, $column_keys);
        $url_original_index = array_search($url_header, $column_keys);
        $i = 0;
        if ($label_original_index !== FALSE && $url_original_index !== FALSE ) {
          foreach ($file_data_all['data'] as $id => &$row) {
            if (isset($row[$label_original_index]) && stripos($row[$label_original_index], $input) === 0) {
              $i++;

              $results[] = [
                'value' => $row[$url_original_index],
                'label' => $row[$label_original_index],
              ];
              if ($i == 10) {
                break;
              }
            }
          }
        }
      }
    }
    return new JsonResponse($results);
  }
}
