<?php

namespace Drupal\webform_strawberryfield;

use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformOptions;


/**
 * Class TwigExtension.
 *
 * @package Drupal\webform_strawberryfield
 */
class TwigExtension extends \Twig_Extension {

  /**
   * @inheritDoc
   */
  public function getFunctions() {
    return [
      new \Twig_SimpleFunction('sbf_webform_select_element_option_label',
        [$this, 'webformSelectElementOptionLabel']),
      new \Twig_SimpleFunction('sbf_webform_option_label',
        [$this, 'webformOptionLabel']),
    ];
  }

  /**
   * Given a webform select list element's option value, returns the corresponding option label.
   *
   * @param  string  $webform_id
   * @param  string  $element_id
   * @param  string|null  $option
   *
   * @return null|string
   *   The option label if found. Otherwise null.
   */
  public function webformSelectElementOptionLabel(
    string $webform_id,
    string $element_id,
    ?string $option = NULL
  ): ?string {
    if(is_string($option) && $option) {
      $webform = Webform::load($webform_id);
      if($webform) {
        $element = $webform->getElement($element_id);
        if (!empty($element)) {
          if (!empty($element['#options'][$option])) {
            return $element['#options'][$option];
          }
          elseif (!empty($element['#vocabulary'])) {
            $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
              ->loadByProperties(['tid' => $option, 'vid' => $element['#vocabulary']]);
            $term = reset($term);
            if($term && $term->label()) {
              return $term->label();
            }
          }
        }

      }
    }
    return NULL;
  }

  /**
   * Given a webform option list and a value, returns the corresponding option label.
   *
   * @param  string  $webform_option_list_id
   * @param  string|null  $option
   *
   * @return null|string
   *   The option label if found. Otherwise null.
   */
  public function webformOptionLabel(
    string $webform_option_list_id,
    ?string $option = NULL
  ): ?string {
    if (is_string($option) && $option) {
      $webform_option_list = WebformOptions::load($webform_option_list_id);
      if (!empty($webform_option_list)) {
        $labels = $webform_option_list->getOptions();
        if (!empty($labels) && !empty($labels[$option])) {
          return $labels[$option];
        }
      }
    }
    return NULL;
  }

}
