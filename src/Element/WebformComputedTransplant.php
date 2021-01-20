<?php
/**
 * Created by PhpStorm.
 * User: dpino
 * Date: 7/20/20
 * Time: 12:08 PM
 */

namespace Drupal\webform_strawberryfield\Element;

use Drupal\webform\Twig\WebformTwigExtension;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Element\WebformComputedBase;
use Drupal\webform\Element\WebformComputedInterface;

/**
 * Provides an item to display computed webform submission values using Twig.
 *
 * @RenderElement("webform_computed_transplant")
 */
class WebformComputedTransplant extends WebformComputedBase {

  /**
   * Whitespace spaceless.
   *
   * Remove whitespace around the computed value and between HTML tags.
   */
  const WHITESPACE_SPACELESS = 'spaceless';

  /**
   * Whitespace trim.
   *
   * Remove whitespace around the computed value.
   */
  const WHITESPACE_TRIM = 'trim';

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return parent::getInfo() + [
        '#whitespace' => '',
        '#transplanted' => '',
      ];
  }

  /**
   * {@inheritdoc}
   */
  public static function computeValue(array $element, WebformSubmissionInterface $webform_submission) {
    $whitespace = (!empty($element['#whitespace'])) ? $element['#whitespace'] : '';

    $template = ($whitespace === static::WHITESPACE_SPACELESS) ? '{% spaceless %}' . $element['#template'] . '{% endspaceless %}' : $element['#template'];
    // To avoid issue related to keys like 'ap:importeddata' are handled as tokens.
    // We can have a setting that allows us to pass one particular element
    // into the context.

    $options = ['html' => (static::getMode($element) === WebformComputedInterface::MODE_HTML)];

    $value = WebformTwigExtension::renderTwigTemplate($webform_submission, $template, $options, []);

    return ($whitespace === static::WHITESPACE_TRIM) ? trim($value) : $value;
  }

}