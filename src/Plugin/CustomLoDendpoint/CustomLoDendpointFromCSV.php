<?php

namespace Drupal\webform_strawberryfield\Plugin\CustomLoDendpoint;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\webform_strawberryfield\Plugin\CustomLoDendpointBase;
use GuzzleHttp\Exception\ClientException;
use Drupal\Core\Cache\CacheBackendInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 *
 *  CSV LoD Endpoint provider
 *
 * @CustomLoDendpoint(
 *   id = "lod_from_csv",
 *   label = @Translation("LOD Endpoint from CSV attached to an ADO"),
 *  )
 */

class CustomLoDendpointFromCSV extends CustomLoDendpointBase {

  public function settingsForm(array $parents, FormStateInterface $form_state) {
    $element['autocomplete_items'] = [
      '#type' => 'sbf_entity_autocomplete_uuid',
      '#title' => $this->t('Choose an ADO.'),
      '#target_type' => 'node',
      '#description' => 'The digital Object that holds a CSV containing the Vocabulary you want to autocomplete',
      '#selection_handler' => 'default:nodewithstrawberry',
      '#validate_reference' => TRUE,
      '#required' => TRUE,
    ];
    $element['autocomplete_label_header'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The CSV column(header name) that will be used for autocompleting'),
      '#required' => TRUE,
    ];
    $element['autocomplete_url_header'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The CSV column(header name) that will be used for the URL value'),
      '#required' => TRUE,
    ];
    $element['autocomplete_desc_headers'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The CSV columns(header names), separated by a comma, that will be used for additional context/description. Leave empty if not used. It has a limit of 2 headers. Any extra ones will be ignored.'),
      '#required' => FALSE,
    ];
    $element['autocomplete_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Autocomplete limit'),
      '#description' => $this->t("The maximum number of matches to be displayed."),
      '#min' => 1,
    ];
    $element['autocomplete_match'] = [
      '#type' => 'number',
      '#title' => $this->t('Autocomplete minimum number of characters'),
      '#description' => $this->t('The minimum number of characters a user must type before a search is performed.'),
      '#min' => 1,
    ];
    $element['autocomplete_match_operator'] = [
      '#type' => 'radios',
      '#title' => $this->t('Autocomplete matching operator'),
      '#description' => $this->t('Select the method used to collect autocomplete suggestions.'),
      '#options' => [
        'STARTS_WITH' => $this->t('Starts with'),
        'CONTAINS' => $this->t('Contains'),
      ],
    ];

    return $element;
  }

  /**
   * @inheritDoc
   */
  public function calculateDependencies() {
    return $this;
  }

  public function handleQuery(string $query): array {
    $config = $this->getConfiguration();
    /* @var \Drupal\node\Entity\Node[] $nodes */
    $nodes =  $this->entityTypeManager->getStorage('node')->loadByProperties(['uuid' => $config['autocomplete_items'] ?? '']);
    $autocomplete_match_operator = $config['autocomplete_match_operator'] ?? 'STARTS_WITH';
    $match = $config['autocomplete_match'] ?? 1;
    $limit =  $config['autocomplete_limit'] ?? 1;
    $label_header = strtolower($config['autocomplete_label_header'] ?? 'term');
    $url_header = strtolower($config['autocomplete_url_header'] ?? 'url');
    $desc_headers = strtolower($config['autocomplete_desc_headers'] ?? '');
    $desc_headers_exploded = [];
    $desc_headers_indexes = [];

    if (!$query || strlen(trim($query)) < $match) {
      return [];
    }
    else {
      $input = $query;
    }
    if (is_string($desc_headers)) {
      $desc_headers_exploded = explode(',', $desc_headers);
      $desc_headers_exploded = array_slice($desc_headers_exploded, 0, 2);
    }
    $results = [];

    if (count($nodes)) {
      $node = reset($nodes);
      if ($node->access('view', $this->currentUser, FALSE)) {
        if ($sbf_fields = \Drupal::service('strawberryfield.utility')
          ->bearsStrawberryfield($node)) {
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
            $file_data_all = \Drupal::service('strawberryfield.utility')->csv_read($file, 0, 0, TRUE, TRUE, 'webform_strawberryfield');
            $column_keys = $file_data_all['headers'] ?? [];
            $label_original_index = array_search($label_header, $column_keys);
            $url_original_index = array_search($url_header, $column_keys);
            foreach ($desc_headers_exploded as $desc_header) {
              $index = array_search($desc_header, $column_keys);
              if ($index !== FALSE) {
                $desc_headers_indexes[] = $index;
              }
            }

            $i = 0;
            if ($label_original_index !== FALSE && $url_original_index !== FALSE) {
              foreach ($file_data_all['data'] as $id => &$row) {
                if (isset($row[$label_original_index])) {
                  if (($autocomplete_match_operator == 'STARTS_WITH' && stripos($row[$label_original_index], $input) === 0) || ($autocomplete_match_operator == 'CONTAINS' && stripos($row[$label_original_index], $input) !== FALSE)) {
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
                      'label' => $row[$label_original_index] . ' ' . $desc_string,
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
      }
    }
    return $results;
  }

}