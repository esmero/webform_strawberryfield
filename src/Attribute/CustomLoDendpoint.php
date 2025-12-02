<?php

declare(strict_types=1);

namespace Drupal\webform_strawberryfield\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a CustomLoDendpoint item Attribute object.
 *
 * Class CustomLoDendpoint
 *
 * @ingroup webform_strawberryfield
 *
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class CustomLoDendpoint extends Plugin {

  /**
   * Constructs a webform strawberryfield custom LoD endpoint attribute.
   *
   * @param string $id
   *   The CustomLoDendpoint Plugin  ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable name of the Key Name Provider Plugin.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *    A human-readable description of the Key Name Provider Plugin.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?TranslatableMarkup $description = NULL,
  ) {}
}