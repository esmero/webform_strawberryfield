<?php
/**
 * Created by PhpStorm.
 * User: dpino
 * Date: 12/2/18
 * Time: 5:17 PM
 */

namespace Drupal\webform_strawberryfield\Plugin\WebformElement;

use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Plugin\WebformElement\Date;
use Drupal\webform\Plugin\WebformElement\TextBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\file\FileInterface;
use Drupal\strawberryfield\Tools\JsonSimpleXMLElementDecorator;
use Drupal\strawberryfield\Tools\SimpleXMLtoArray;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\webform_strawberryfield\Plugin\WebformElement\MetadataDateBase;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\webform\Plugin\WebformElement\DateBase;
use Drupal\webform\Utility\WebformDateHelper;

/**
 * Provides a 'file element that can import into the submission/ process other
 * formats' element.
 *
 * @WebformElement(
 *   id = "webform_metadata_date",
 *   api = "https://api.drupal.org/api/drupal/core!lib!Drupal!Core!Render!Element!Date.php/class/Date",
 *   label = @Translation("Multi Format Date and Date Range"),
 *   description = @Translation("Provides a form element for setting/reading Dates indifferent formats suitable for metadata."),
 *   category = @Translation("Date/time elements"), states_wrapper = TRUE,
 * )
 */
class WebformMetadataDate extends MetadataDateBase {

  /**
   * {@inheritdoc}
   */
  protected function defineDefaultProperties() {
    $date_format = '';
    // Date formats cannot be loaded during install or update.
    if (!defined('MAINTENANCE_MODE')) {
      /** @var \Drupal\Core\Datetime\DateFormatInterface $date_format_entity */
      if ($date_format_entity = DateFormat::load('html_date')) {
        $date_format = $date_format_entity->getPattern();
      }
    }

    return [
        'datepicker' => FALSE,
        'showfreeformalways' => FALSE,
        'datepicker_button' => FALSE,
        'date_date_format' => $date_format,
        'placeholder' => '',
        'step' => '',
        'size' => '',
        'input_hide' => FALSE,
        'edtf_validateme' => FALSE,
        'edtf_validate_option_intervals' => FALSE,
        'edtf_validate_option_sets' => FALSE,
        'edtf_validate_option_strict' => FALSE,
      ] + parent::defineDefaultProperties()
      + $this->defineDefaultMultipleProperties();
  }

  /**
   * {@inheritdoc}
   */
  public function prepare(
    array &$element,
    WebformSubmissionInterface $webform_submission = NULL
  ) {
    // Unset unsupported date format for date elements that are not using a
    // datepicker.
    if (empty($element['#datepicker'])) {
      unset($element['#date_date_format']);
    }

    // Set default date format to HTML date.
    if (!isset($element['#date_date_format'])) {
      $element['#date_date_format'] = $this->getDefaultProperty(
        'date_date_format'
      );
    }

    // Set placeholder attribute.
    if (!empty($element['#placeholder'])) {
      $element['#attributes']['placeholder'] = $element['#placeholder'];
    }

    // Prepare element after date format has been updated.
    parent::prepare($element, $webform_submission);


    // Set the (input) type attribute to 'date'.
    // @see \Drupal\Core\Render\Element\Date::getInfo
    $element['#attributes']['type'] = 'date';

    // Convert date element into textfield with date picker.
    if (!empty($element['#datepicker'])) {
      $element['#attributes']['type'] = 'text';

      // Must manually set 'data-drupal-date-format' to trigger date picker.
      // @see \Drupal\Core\Render\Element\Date::processDate
      $element['#attributes']['data-drupal-date-format'] = [$element['#date_date_format']];
    }


  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['date']['showfreeformalways'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Always show the free Form date input too'),
      '#description' => $this->t(
        'If checked both Point and Range modes will also allow a non validated free form input'
      ),
      '#return_value' => TRUE,
    ];
    $form['date']['edtf'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Validate freeform date as EDTF'),
      '#description' => $this->t(
        'Options to validate the freeform date as extended date/time format date (EDTF)'
      ),
      '#states' => [
        'visible' => [
          ':input[name="properties[showfreeformalways]"]' => ['checked' => TRUE],
        ],
      ],
      'edtf_validateme' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Validate as EDTF'),
        '#return_value' => TRUE,
      ],
      'edtf_validate_options' => [
        '#type' => 'container',
        '#states' => [
          'visible' => [
            ':input[name="properties[edtf_validateme]"]' => ['checked' => TRUE],
          ],
        ],
        'edtf_validate_option_intervals' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Are interval expressions permitted?'),
          '#return_value' => TRUE,
        ],
        'edtf_validate_option_sets' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Are set expressions permitted?'),
          '#return_value' => TRUE,
        ],
        'edtf_validate_option_strict' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Are only valid calendar dates permitted?'),
          '#return_value' => TRUE,
        ],
      ],
    ];
    $form['date']['datepicker'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use date picker'),
      '#description' => $this->t(
        'If checked, the HTML5 date element will be replaced with a <a href="https://jqueryui.com/datepicker/">jQuery UI datepicker</a>'
      ),
      '#return_value' => TRUE,
    ];
    $form['date']['datepicker_button'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show date picker button'),
      '#description' => $this->t(
        'If checked, date picker will include a calendar button'
      ),
      '#return_value' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="properties[datepicker]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $date_format = DateFormat::load('html_date')->getPattern();
    $form['date']['date_date_format'] = [
      '#type' => 'webform_select_other',
      '#title' => $this->t('Date format'),
      '#options' => [
        $date_format => $this->t(
          'HTML date - @format (@date)',
          [
            '@format' => $date_format,
            '@date' => static::formatDate($date_format),
          ]
        ),
        'l, F j, Y' => $this->t(
          'Long date - @format (@date)',
          ['@format' => 'l, F j, Y', '@date' => static::formatDate('l, F j, Y')]
        ),
        'D, m/d/Y' => $this->t(
          'Medium date - @format (@date)',
          ['@format' => 'D, m/d/Y', '@date' => static::formatDate('D, m/d/Y')]
        ),
        'm/d/Y' => $this->t(
          'Short date - @format (@date)',
          ['@format' => 'm/d/Y', '@date' => static::formatDate('m/d/Y')]
        ),
      ],
      '#description' => $this->t(
        "Date format is only applicable for browsers that do not have support for the HTML5 date element. Browsers that support the HTML5 date element will display the date using the user's preferred format."
      ),
      '#other__option_label' => $this->t('Custom…'),
      '#other__placeholder' => $this->t('Custom date format…'),
      '#other__description' => $this->t(
        'Enter date format using <a href="http://php.net/manual/en/function.date.php">Date Input Format</a>.'
      ),
      '#states' => [
        'visible' => [
          ':input[name="properties[datepicker]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['date']['date_container']['step'] = [
      '#type' => 'number',
      '#title' => $this->t('Step'),
      '#description' => $this->t('Specifies the legal number intervals.'),
      '#min' => 1,
      '#size' => 4,
      '#states' => [
        'invisible' => [
          ':input[name="properties[datepicker]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Show placeholder for the datepicker only.
    $form['form']['placeholder']['#states'] = [
      'visible' => [
        ':input[name="properties[datepicker]"]' => ['checked' => TRUE],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemFormat(array $element) {
    return parent::getItemFormat($element);
  }

  /**
   * {@inheritdoc}
   */
  protected function formatHtmlItem(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    if (!$this->hasValue($element, $webform_submission, $options)) {
      return '';
    }

    $format = $this->getItemFormat($element);

    switch ($format) {
      case 'blabla':
        $items = $this->formatCompositeHtmlItems($element, $webform_submission, $options);
        return [
          '#theme' => 'item_list',
          '#items' => $items,
        ];

      default:
        $lines = $this->formatHtmlItemValue($element, $webform_submission, $options);
        if (empty($lines)) {
          return '';
        }
        foreach ($lines as $key => $line) {
          if (is_string($line)) {
            $lines[$key] = ['#markup' => $line];
          }
          $lines[$key]['#suffix'] = '<br />';
        }
        // Remove the <br/> suffix from the last line.
        unset($lines[$key]['#suffix']);
        return $lines;
    }
  }




  /**
   * {@inheritdoc}
   */
  protected function formatHtmlItemValue(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    return $this->formatTextItemValue($element, $webform_submission, $options);
  }

  /**
   * {@inheritdoc}
   */
  protected function formatTextItemValue(array $element, WebformSubmissionInterface $webform_submission, array $options = []) {
    $value = $this->getValue($element, $webform_submission, $options);

    $lines = [];

    $date_types = [
      'date_point' => 'Point Date',
      'date_range' => 'Date Range',
      'date_free' => 'Freeform Date',
    ];
    $type = 'date_free';
    if (!empty($value['date_type'])) {
      $type = isset($date_types[$value['date_type']]) ? $value['date_type'] : $type;
    }
    $lines[] = "Date type:". $date_types[$type];
    switch ($type) {
      case 'date_free':
        if (!empty($value['date_free'])) {
          $lines[] = $value['date_free'];
        } else {
          $lines[] = 'Not Set';
        }
        break;
      case 'date_range':
        $date_string = 'From:';
        if (!empty($value['date_from'])) {
          $date_string .= $value['date_from'];
        } else {
          $date_string .= 'Not Set';
        }
        $date_string = ' To:';
        if (!empty($value['date_to'])) {
          $date_string .= $value['date_to'];
        }
        else {
          $date_string .= 'Not Set';
        }
        if (!empty($value['date_free'])) {
          $date_string .= ', Display Date(free):'. $value['date_free'];
        }
        $lines[] = $date_string;
        break;
      case 'date_point':
        $date_string = 'Point Date:';
        if (!empty($value['date_from'])) {
          $date_string .= $value['date_from'];
        } else {
          $date_string .= 'Not Set';
        }
        if (!empty($value['date_free'])) {
          $date_string .= ', Display Date(free):'. $value['date_free'];
        }
        $lines[] = $date_string;
    }
    return $lines;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(array &$element, WebformSubmissionInterface $webform_submission, $update = TRUE) {
    // Get current value and original value for this element.

    parent::preSave($element, $webform_submission, $update);
    $key = $element['#webform_key'];

    $value = $this->getValue($element, $webform_submission, []);
    // Since this element generates empty entries because the date_type is actually a value
    // we will use this last moment to filter out those.
    $newvalue = [];
    if ($element['#multiple']) {
        foreach ($value as $item) {
          $filtered_value = array_filter($item);
          // Empty elements here will carry at least the date_type making them
          // not empty. So deal with that by unsetting the value completely in
          // that case.
          if (count($filtered_value) > 1 ) {
            $newvalue[] = $item;
          }
      }
      }
    else {
      $filtered_value = array_filter($value);
      if (count($filtered_value) > 1 ) {
        $newvalue = $value;
      }
    }
    $webform_submission->setElementData($key,$newvalue);
  }
}
