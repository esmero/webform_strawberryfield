<?php

namespace Drupal\webform_strawberryfield\Controller;
use Drupal\Core\Database\Database;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\webform\Controller\WebformElementController;

/**
 * Defines a route controller for Webform Option autocomplete form elements.
 *
 * @see webform_strawberryfield.routing.yml:51
 */
class WebformOptionsAutocompleteController extends WebformElementController implements ContainerInjectionInterface {

  protected function getMatchesFromOptionsRecursive($q, array $options,
    array &$matches, $operator = 'CONTAINS'
  ) {
    foreach ($options as $value => $label) {
      if (is_array($label)) {
        $this->getMatchesFromOptionsRecursive($q, $label, $matches, $operator);
        continue;
      }

      // Cast TranslatableMarkup to string.
      $label = (string) $label;

      if ($operator === 'STARTS_WITH' && stripos($label, $q) === 0) {
        $matches[$label] = [
          'value' => $value,
          'label' => $label,
        ];
      }
      // Default to CONTAINS even when operator is empty.
      elseif (stripos($label, $q) !== FALSE) {
        $matches[$label] = [
          'value' => $value,
          'label' => $label,
        ];
      }
    }
  }
}
