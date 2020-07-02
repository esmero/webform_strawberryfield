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
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides an 'LoC Heading' element.
 *
 * @WebformElement(
 *   id = "webform_metadata_loc",
 *   label = @Translation("LoC heading"),
 *   description = @Translation("Provides a form element to reconciliate against LoC Headings."),
 *   category = @Translation("Composite elements"),
 *   multiline = TRUE,
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 * )
 */
class WebformLoC extends WebformCompositeBase {


  public function getDefaultProperties() {
    $properties = [
        'vocab' => 'subjects',
        'rdftype' => 'FullNameElement',
      ] + $this->getDefaultBaseProperties();
    return $properties;
  }

  public function prepare(
    array &$element,
    WebformSubmissionInterface $webform_submission = NULL
  ) {
    parent::prepare(
      $element,
      $webform_submission
    );
    // @TODO explore this method to act on submitted data v/s element behavior
    // e.g $webform_submission->getData());
    // $vocab = $this->getElementProperty($element, 'vocab');

  }

  /**
   * {@inheritdoc}
   */
  public function getPluginLabel() {
    return $this->elementManager->isExcluded('webform_metadata_loc') ? $this->t('Subject Headings') : parent::getPluginLabel();
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
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    //@NOTE    'classification' => 'classification(LCCS)', is not working
    // Not sure if this has a sub authority and how that works/if suggest
    $form['composite']['vocab'] = [
      '#type' => 'select',
      '#options' => [
        'subjects' => 'subjects(LCSH)',
        'names' => 'LC Name Authority File (LCNAF)',
        'genreForms' => 'LC Genre/Form Terms (LCGFT',
        'graphicMaterials' => 'Thesaurus of Graphic Materials (TGN)',
        'geographicAreas' => 'MARC List for Geographic Areas',
        'rdftype' => 'Filter Suggest results by RDF Type',
      ],
      '#title' => $this->t("What LoC Autocomplete Source Provider to use."),
      '#description' => $this->t('See <a href="http://id.loc.gov">Linked Data Service</a>. If the link is to an Authority is http://id.loc.gov/authorities/names then the value to use there is <em>names</em>'),
      '#default_value' => 'subjects',
    ];
    // Not sure if this has a sub authority and how that works/if suggest
    $form['composite']['rdftype'] = [
      '#type' => 'textfield',
      '#title' => $this->t("What RDF type to use as filter"),
      '#description' => $this->t('See <a href="http://id.loc.gov/ontologies/madsrdf/v1.html">Use one of the Classes listed here</a>. Defaults to <em>FullNameElement</em>'),
      '#default_value' => 'FullNameElement',
      '#states' => [
        'visible' => [
          ':input[name="properties[vocab]"]' => ['value' => 'rdftype'],
        ],
      ],
    ];

    return $form;
  }


}
