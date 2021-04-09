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
        if(!empty($element) && !empty($element['#options']) && !empty($element['#options'][$option])) {
          return $element['#options'][$option];
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
    if(is_string($option) && $option) {
      $webform_option_list = WebformOptions::load($webform_option_list_id);
        if(!empty($webform_option_list) && !empty($webform_option_list['#options']) && !empty($webform_option_list['#options'][$option])) {
          return $webform_option_list['#options'][$option];
      }
    }
    return NULL;
  }

}
