<?php
/**
 * Created by PhpStorm.
 * User: dpino
 * Date: 9/18/18
 * Time: 8:56 PM
 */

namespace Drupal\webform_strawberryfield\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Simplistic Strawberry Field formatter.
 */
#[FieldFormatter(
  id: 'strawberry_configurable_formatter',
  label: new TranslatableMarkup('Strawberry Field Pretty Formatter'),
  field_types: ['strawberryfield_field']
)]
class StrawberryConfigurableFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Displays JSON');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      // Render each element as markup.
      $element[$delta] = [
        '#markup' => $item->value,
        '#prefix' => '<pre>',
        '#suffix' => '</pre>',
      ];
    }

    return $element;
  }

}