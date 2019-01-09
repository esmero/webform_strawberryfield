<?php
/**
 * Created by PhpStorm.
 * User: dpino
 * Date: 12/2/18
 * Time: 5:17 PM
 */

namespace Drupal\webform_strawberryfield\Plugin\WebformElement;

use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Plugin\WebformElement\WebformCompositeBase;

/**
 * Provides an 'LoC Subject Heading' element.
 *
 * @WebformElement(
 *   id = "webform_metadata_wikidataagent",
 *   label = @Translation("Wikidata Agent Items"),
 *   description = @Translation("Provides a form element to reconciliate Agents against Wikidata Items."),
 *   category = @Translation("Composite elements"),
 *   multiline = TRUE,
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 * )
 */
class WebformWikiDataAgent extends WebformCompositeBase {

  /**
   * {@inheritdoc}
   */
  public function getPluginLabel() {
    return $this->elementManager->isExcluded('webform_metadata_wikidataagent') ? $this->t('WIKIDATA Agents') : parent::getPluginLabel();
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
    if (!empty($value['role_uri'])) {
      $lines[] = $value['role_uri'];
    }

    if (!empty($value['role_label'])) {
      $lines[] = $value['role_label'];
    }

    if (!empty($value['agent_uri'])) {
      $lines[] = $value['role_uri'];
    }

    if (!empty($value['agent_label'])) {
      $lines[] = $value['role_label'];
    }
    return $lines;
  }

}
