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
 * Provides an 'LoC Subject Heading' element.
 *
 * @WebformElement(
 *   id = "webform_metadata_getty",
 *   label = @Translation("Getty Vocabulary Term"),
 *   description = @Translation("Provides a form element to reconciliate against the Getty Vocabularies."),
 *   category = @Translation("Composite elements"),
 *   multiline = TRUE,
 *   composite = TRUE,
 *   states_wrapper = TRUE,
 * )
 */
class WebformGetty extends WebformCompositeBase {

  protected function defineDefaultBaseProperties() {
    return [
      'vocab' => 'aat',
      'matchtype' => 'fuzzy',
    ];
  }

  public function getDefaultProperties() {
    $properties = parent::getDefaultProperties() + [
        'vocab' => 'aat',
        'matchtype' => 'fuzzy',
      ] + parent::defineDefaultProperties()
      + $this->defineDefaultBaseProperties();

    return $properties;
  }

  /**
   * Set multiple element wrapper.
   *
   * @param array $element
   *   An element.
   */
  protected function prepareMultipleWrapper(array &$element) {

    parent::prepareMultipleWrapper($element);

    // Finally!
    // This is the last chance we have to affect the render array
    // This is where the original element type is also
    // swapped by webform_multiple
    // breaking all our #process callbacks.
    $vocab = 'aat';
    $matchtype = 'fuzzy';
    $vocab = $this->getElementProperty($element, 'vocab');
    $vocab = $vocab ?:  $this->getDefaultProperty($vocab);
    if ($vocab == 'aat') {
      $matchtype = trim($this->getElementProperty($element, 'matchtype'));
    }

    $matchtype = $matchtype ?: $this->getDefaultProperty('matchtype');
    // This seems to have been an old Webform module variation
    // Keeping it here until sure its not gone for good
    if (isset($element['#element']['#webform_composite_elements']['label'])) {
      $element['#element']['#webform_composite_elements']['label']["#autocomplete_route_parameters"] =
        [
          'auth_type' => 'getty',
          'vocab' => $vocab,
          'rdftype' => $matchtype,
          'count' => 10
        ];
    }
    // For some reason i can not understand, when multiples are using
    // Tables, the #webform_composite_elements -> 'label' is not used...
    if (isset($element["#multiple__header"]) && $element["#multiple__header"] == true) {
      $element['#element']['label']["#autocomplete_route_parameters"] =
        [
          'auth_type' => 'getty',
          'vocab' => $vocab,
          'rdftype' => $matchtype,
          'count' => 10
        ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginLabel() {
    return $this->elementManager->isExcluded('webform_metadata_getty') ? $this->t('Getty Subject Headings') : parent::getPluginLabel();
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
        'aat' => t('Art & Architecture Thesaurus'),
      ],
      '#default_value' => 'aat',
      '#title' => $this->t("What Getty Vocabulary SPARQL Source Provider to use."),
      '#description' => $this->t('Currently only AAT is supported but more are on the works'),
    ];
    // Not sure if this has a sub authority and how that works/if suggest
    $form['composite']['matchtype'] = [
      '#type' => 'select',
      '#options' => [
        'fuzzy' => 'Fuzzy, based on description',
        'exact' => 'Exact, based on recommended Label. Will give you a single result or none.',
        'terms' => 'Uses Terms and wildcards to narrow down queries',
      ],
      '#title' => $this->t("What type of Match Query to perform"),
      '#description' => $this->t('All match types return the same number of results. Exact matches only against the prefered label of the term, fuzzy will search inside the extended description. Terms will try to use indexed terms using each input word to find a closer match.'),
    ];
    return $form;
  }

}
