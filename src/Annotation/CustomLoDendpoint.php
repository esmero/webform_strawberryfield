<?php

declare(strict_types=1);

namespace Drupal\webform_strawberryfield\Annotation;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Component\Annotation\Plugin;

/**
 * Defines a CustomLoDendpoint item Attribute object.
 *
 * Class LoDendpointEntity
 *
 * @ingroup webform_strawberryfield
 *
 *
 * @Annotation
 */
class CustomLoDendpoint extends Plugin {

    public readonly string $id;
    public readonly TranslatableMarkup $label;
    public readonly ?TranslatableMarkup $description;
}