<?php
/**
 * Created by PhpStorm.
 * User: dpino
 * Date: 12/2/18
 * Time: 5:17 PM
 */

namespace Drupal\webform_strawberryfield\Plugin\WebformElement;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Plugin\WebformElement\WebformCompositeBase;

/**
 * Provides an 'Wikidata Items' element.
 *
 * @WebformElement(
 *   id = "webform_metadata_wikidata",
 *   label = @Translation("Wikidata Items"),
 *   description = @Translation("Provides a form element to reconciliate against Wikidata Items."),
 *   category = @Translation("Composite elements"),
 *   multiline = TRUE,
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 * )
 */
class WebformWikiData extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  public function getPluginLabel() {
    return $this->elementManager->isExcluded('webform_metadata_wikidata') ? $this->t('WIKIDATA Subject Headings') : parent::getPluginLabel();
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
    if (!empty($value['uri'])) {
      $lines[] = $value['uri'];
    }

    if (!empty($value['label'])) {
      $lines[] = $value['label'];
    }
    return $lines;
  }

  /**
   * @param array $element
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public static function hiddenElementAfterBuild(
    array $element,
    FormStateInterface $form_state
  ) {
    // We override this method only to convert non accsible elements
    // Into hidden ones, in case Multiples are being show as tables
    // Because initialization in that case happens does not happen in
    // \Drupal\webform_strawberryfield\Element\WebformWikiData::processWebformComposite

    $element = parent::hiddenElementAfterBuild(
      $element,
      $form_state
    );
    if (isset($element['#element'])) {
      foreach ($element['#element'] as $subelement_key => &$subelement) {
        if (isset($subelement['#access']) && !$subelement['#access']) {
          $subelement['#type'] = 'hidden';
          $subelement['#access'] = TRUE;
        }
      }
    }
    return $element;
  }


}
