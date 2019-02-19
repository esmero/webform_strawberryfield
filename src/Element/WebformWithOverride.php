<?php

namespace Drupal\webform_strawberryfield\Element;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\Element\RenderElement;
use Drupal\webform\Entity\Webform as WebformEntity;
use Drupal\webform\WebformInterface;
use Drupal\webform\Element\Webform;

/**
 * Provides a render element to display a webform inline as a widget.
 *
 * @RenderElement("webform_inline_fieldwidget")
 */
class WebformWithOverride extends Webform {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#pre_render' => [
        [$class, 'preRenderWebformElement'],
      ],
      '#webform' => NULL,
      '#default_data' => [],
      '#action' => NULL,
      '#override'=> [],
    ];
  }


  /**
   * Webform element pre render callback.
   *
   * @param $element
   *
   * @return mixed
   */
  public static function preRenderWebformElement($element) {
    $webform = ($element['#webform'] instanceof WebformInterface) ? $element['#webform'] : WebformEntity::load($element['#webform']);
    if (!$webform) {
      return $element;
    }

    if ($webform->access('submission_create')) {
      $values = [];

      // Set data.
      $values['data'] = $element['#default_data'];

      // Set source entity type and id.
      if (!empty($element['#entity']) && $element['#entity'] instanceof EntityInterface) {
        $values['entity_type'] = $element['#entity']->getEntityTypeId();
        $values['entity_id'] = $element['#entity']->id();
      }
      elseif (!empty($element['#entity_type']) && !empty($element['#entity_id'])) {
        $values['entity_type'] = $element['#entity_type'];
        $values['entity_id'] = $element['#entity_id'];
      }

      if (!empty($element['#override'])) {
        $new_settings = $element['#override'];
        $webform->setSettingsOverride($new_settings);
        $values['strawberryfield:override'] = $new_settings;
        //$webform->resetSettings();
      }

      // Build the webform.
      $element['webform_build'] = $webform->getSubmissionForm($values);

      // Set custom form action.
      if (!empty($element['#action'])) {
        $element['webform_build']['#action'] = $element['#action'];
      }
    }
    elseif ($webform->getSetting('form_access_denied') !== WebformInterface::ACCESS_DENIED_DEFAULT) {
      // Set access denied message.
      $element['webform_access_denied'] = static::buildAccessDenied($webform);
    }
    else {
      // Add config and webform to cache contexts.
      $config = \Drupal::configFactory()->get('webform.settings');
      $renderer = \Drupal::service('renderer');
      $renderer->addCacheableDependency($element, $config);
      $renderer->addCacheableDependency($element, $webform);
    }

    return $element;
  }

}
