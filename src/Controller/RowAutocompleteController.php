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
  public function handleAutocomplete(Request $request, ContentEntityInterface $node, $label_header, $url_header, $match = 'STARTS_WITH', $limit = 10, $min = 2, $desc_headers = NULL) {
    $results = [];
    $input = $request->query->get('q');
    $input = Xss::filter($input);
    $label_header = strtolower($label_header);
    $url_header = strtolower($url_header);
    $desc_headers = strtolower($desc_headers);
    $desc_headers_exploded = [];
    $desc_headers_indexes = [];
    // Find a CSV file in this ADO.
    // Get the typed string from the URL, if it exists.
    if (!$input && strlen(trim($input)) < $min) {
      return new JsonResponse($results);
    }
    if (is_string($desc_headers)) {
      $desc_headers_exploded = explode(',', $desc_headers);
      $desc_headers_exploded = array_slice($desc_headers_exploded, 0, 2);
    }

    $file = null;
    if ($sbf_fields = \Drupal::service('strawberryfield.utility')->bearsStrawberryfield($node)) {
      $files = $node->get('field_file_drop')->getValue();
      foreach ($files as $offset => $fileinfo) {
        /** @var \Drupal\file\FileInterface $file |null */
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
        foreach ($desc_headers_exploded as $desc_header) {
          $index = array_search($desc_header, $column_keys);
          if ($index!== FALSE) {
            $desc_headers_indexes[] = $index;
          }
        }

        $i = 0;
        if ($label_original_index !== FALSE && $url_original_index !== FALSE) {
          foreach ($file_data_all['data'] as $id => &$row) {
            if (isset($row[$label_original_index])) {
              if (($match == 'STARTS_WITH' && stripos($row[$label_original_index], $input) === 0) || ($match == 'CONTAINS' && stripos($row[$label_original_index], $input) !== FALSE)) {
                $i++;
                $desc = [];
                $desc_string = '';
                foreach ($desc_headers_indexes as $desc_header_index) {
                  $desc[] = $row[$desc_header_index];
                }
                $desc = array_filter($desc);
                if (count($desc)) {
                  $desc_string = implode('|', $desc);
                }
                $desc_string = ($desc_string !== '') ? '(' . $desc_string . ')' : NULL;
                $results[] = [
                  'value' => $row[$url_original_index],
                  'label' => $row[$label_original_index].' '.$desc_string,
                  'desc' => $desc_string
                ];
                if ($i == $limit) {
                  break;
                }
              }
            }
          }
        }
      }
    }
    return new JsonResponse($results);
  }
}
