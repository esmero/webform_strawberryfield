<?php

namespace Drupal\webform_strawberryfield;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;

class ElementHelper {

    /**
     * Form element validation handler for Core elements that require a string
     * E.g Url, Email, Color.
     *
     * When passed a non string See \Drupal\Core\Render\Element\Url::validateUrl
     * A 500 is returned. Our RAW JSON can contain empties as arrays.
     *
     */
    public static function prevalidateForStringAndEmpty(&$element, FormStateInterface $form_state, &$complete_form) {
        $is_empty_multiple = is_countable($element['#value']) && count($element['#value']) == 0;
        $is_empty_string = (is_string($element['#value']) && mb_strlen(trim($element['#value'])) == 0);
        $is_empty_value = ($element['#value'] === 0);
        $is_empty_null = is_null($element['#value']);
        if ($is_empty_multiple || $is_empty_string || $is_empty_value || $is_empty_null) {
            $value = "";
            $form_state->setValueForElement($element, $value);
        }
        elseif (!is_scalar($element['#value'])) {
            $value = $element['#value'];
            $form_state->setError($element, t('The passed value %url is not a string.', ['%url' => json_encode($value)]));
            $element['#value'] =  json_encode($value);
        }
    }
}