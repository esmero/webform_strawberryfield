<?php
/**
 * Created by PhpStorm.
 * User: dpino
 * Date: 4/23/18
 * Time: 9:02 PM
 */

namespace Drupal\webform_strawberryfield\Controller;

use Drupal\webform\WebformInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Html;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Ramsey\Uuid\Uuid;

/**
 * StrawberryRunnerModalController class.
 */
class StrawberryRunnerModalController extends ControllerBase {

  /**
   * Callback for opening the modal form.
   * @param WebformInterface|NULL $webform
   * @param Request $request
   * @return AjaxResponse
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when not be accessible.
   */
  public function openModalForm(WebformInterface $webform = NULL, Request $request) {

    // @see \Drupal\archipel\Plugin\Field\FieldWidget\StrawberryFieldWebFormWidget::formElement
    //  Request Arguments we are expecting:
    // 'webform' =>  $my_webform_machinename,
    // 'source_entity_types' => $entity_type,
    // 'state'=> "$entity_uuid:$this_field_name";
    // Check!


    $source_entity_types = $request->get('source_entity_types');

    [$source_entity_type, $bundle] = explode(':', $source_entity_types);
    $access = \Drupal::entityTypeManager()->getAccessControlHandler('node')->createAccess($bundle);
    if (!$access) {
      throw new AccessDeniedHttpException('Sorry, seems like you can are not allowed to see this or to be here at all!');
    }

    //@TODO allow some type of per bundle hook?
    $state = $request->get('state');
    $modal = $request->get('modal') ? $request->get('modal') : FALSE;
    $clear_saved = $request->get('clear_saved') ? $request->get('clear_saved') : NULL;

    // with $uuid the uuid of the entity that is being edited and for which
    // a widget is being openend in the form of a webform
    // field name the machine name of the field that contains the original data
    // inside that source entity

    [$source_uuid, $field_name, $delta, $widgetid] = explode(':', $state);

    // @TODO check if all our arguments pass the format test.
    // Can be done via regex but i prefer next option...
    // throw new \InvalidArgumentException('Data type must be in the form of
    // "entityUUID:FIELD_NAME:DELTA:someSHA1hashthatidentifiesthegtriggeringwidget"');

    /* @var $source_entity \Drupal\Core\Entity\FieldableEntityInterface */
    $source_entity = NULL;
    // If uuid does not exist then it may be a new ADO. That is Ok.
    if ($source_uuid && Uuid::isValid($source_uuid)) {
      try {
        //@var $entities \Drupal\Core\Entity\ContentEntityInterface[] */
        $entities = \Drupal::entityTypeManager()
          ->getStorage($source_entity_type)
          ->loadByProperties(['uuid' => $source_uuid]);
        // IF this does not work, either the entity is new! or it does not exist at all.
      }
      catch (\Exception $e) {
        // @todo really make some fuzz if this happens.
        // @todo we need to be super responsive about all the errors
        // I image two layers, simple to follow issues with a code for endusers
        // And a very deep explanation for admins and devs that match the code

        \Drupal::messenger()
          ->addError($this->t('We could not find the referenced Entity @entity_type. Please report this to your Site Manage.',
            ['@entity_type' => $source_entity_type]));
        // Really no need to persist after this.
        $response = new AjaxResponse();
        $notfound = [
          '#type' => 'markup',
          '#markup' => '<p>Ups, missing Entity.<p>',
        ];
        $this->messenger->addWarning('Non existing entity passed. Error',
          MessengerInterface::TYPE_WARNING);
        $response->addCommand(new OpenModalDialogCommand(t('Error'),
          $notfound, ['width' => '90%']));
        return $response;
      }

      foreach ($entities as $entity) {
        // Means there was an entity stored! hu!
        // if you are following this you will know this foreach
        // makes little sense because we will either get a single one or none
        // but! makes sense anyway, shorter than checking if there, and if so
        // getting the first!
        //@var $source_entity \Drupal\Core\Entity\FieldableEntityInterface */
        $vid = \Drupal::entityTypeManager()
          ->getStorage($source_entity_type)
          ->getLatestRevisionId($entity->id());

        $source_entity = $vid ? \Drupal::entityTypeManager()
          ->getStorage($source_entity_type)
          ->loadRevision($vid) : $entity;
        if (!$source_entity->access('update')) {
          throw new AccessDeniedHttpException('Sorry, seems like you can are not allowed to see this or to be here at all!');
        }
      }
    }

    $data = array();

    // Stores our original field data
    // @TODO i know it needs to have a base value
    // but looks weird... refactor!

    $fielddata = [];
    // If new this won't exist
    $entityid = NULL;
    // If we actually loaded the entity then lets fetch the saved field value
    // @see \Drupal\archipel\Plugin\Field\FieldType\StrawberryField::propertyDefinitions
    // @var $source_entity \Drupal\Core\Entity\FieldableEntityInterface */
    if ($source_entity) {
      // In case we are editing an existing entity, this one gets the
      // Strawberryfield value
      $alldata = $source_entity->get($field_name)->getValue();
      $fielddata['value'] = $alldata[$delta]['value'] ?? "{}";
      $entityid = $source_entity->id();
    }

    $stored_value = $fielddata['value'] ?? "{}";

    $data_defaults = [
      'strawberry_field_widget_state_id' => $widgetid,
      // Can't remember why, but seems useful to pass around
      'strawberry_field_widget_source_entity_uuid' => $source_uuid,
      'strawberry_field_widget_source_entity_id' => $entityid,
      'strawberry_field_widget_autosave' => $entityid ? FALSE : TRUE,
      'strawberry_field_stored_values' => json_decode($stored_value,true)
    ];

    if (!isset($fielddata['value']) || empty($fielddata['value'])) {
      // No data
      $data['data'] = $data_defaults  +
        [
          'label' => NULL,
        ];
    }
    else {
      if (!function_exists('array_is_list')) {
        function array_is_list(array $arr)
        {
          if ($arr === []) {
            return true;
          }
          return array_keys($arr) === range(0, count($arr) - 1);
        }
      }
      $data['data'] = $data_defaults + json_decode($stored_value,true);
      /* @var \Drupal\webform\WebformInterface $webform */
      $webform_elements  = $webform->getElementsInitializedFlattenedAndHasValue();
      $webform_elements_clean = $webform->getElementsDecodedAndFlattened();
      $elements_in_data = array_intersect_key($webform_elements, $data['data']);
      // In case the saved data is "single valued" for a key
      // But the corresponding webform element is not
      // we cast to it multi valued so it can be read/updated
      // If the element itself does not allow multiple, is not a composite and we are passing an indexed array
      // we need to re-write data (take the first) to avoid a Render array error in Drupal 10.3
      // But also tell the user this form is not safe to use.
      if (is_array($elements_in_data) && count($elements_in_data) > 0) {
        $error_elements_why = [];
        $error_elements = StrawberryRunnerModalController::validateDataAgainstWebformElements($elements_in_data, $data, $error_elements_why);
      }
      foreach ($error_elements as $key => $value) {
        $element = $webform_elements_clean[$key];
        $element['#disabled'] = TRUE;
        $element['#required'] = FALSE;
        $webform->setElementProperties($key, $element);
      }
      $data['data']['strawberry_field_invalid_elements'] = $error_elements;
    }

    $confirmation_message = $webform->getSetting('confirmation_message', FALSE);
    $confirmation_message = !empty($confirmation_message) && strlen(trim($confirmation_message)) > 0 ? $confirmation_message : $this->t(
      'Thanks, you are all set! Please Save the content to persist the changes.');

    // Lets make sure this puppy never redirects
    // And also we need to reset some defaults here
    // @see \Drupal\webform\Entity\Webform::getDefaultSettings
    // @TODO autofill needs to be a setting that is respected
    // @TODO research option of using WebformInterface::CONFIRMATION_NONE
    // @SEE https://www.drupal.org/node/2996780
    // Does not work right now.
    // See workaround at \Drupal\webform_strawberryfield\Plugin\WebformHandler\strawberryFieldharvester::preprocessConfirmation
    $new_settings = [
      'confirmation_type' => WebformInterface::CONFIRMATION_INLINE,
      'confirmation_back' => TRUE,
      'results_disabled' => TRUE,
      'autofill' => FALSE,
      'ajax' => TRUE,
      'form_submit_once' => FALSE,
      'confirmation_exclude_token' => TRUE,
      'wizard_progress_link' => TRUE,
      'submission_user_duplicate' => TRUE,
      'submission_log' => FALSE,
      'confirmation_message' => $confirmation_message,
      'draft_saved_message' => t('Your progress was stored. You may return to this form before a week has passed and it will restore the current values.')
    ];


    // @todo make autofill v/s none a user setting.
    // Override in a way that the handler can actually act on
    // @See https://www.drupal.org/project/webform/issues/3088386
    // and where we do this
    // \Drupal\webform_strawberryfield\Plugin\WebformHandler\strawberryFieldharvester::overrideSettings
    $data['strawberryfield:override'] = $new_settings;

    // This really does not work on 5.x but could eventually on 6.x
    $webform->setSettingsOverride($new_settings);

    $lawebforma = $webform->getSubmissionForm($data);

    $response = new AjaxResponse();
    //@TODO deal with people opening, closing, reopening.
    // Do we show them the original data, every time they open the form?
    // As we do right now? Do we restore the ongoing session?
    // Idea. Store original info into original data structure of the
    // submission
    // Whatever is stored in temp storage as active one.
    // Make reset button clear deal with swapping back original to active.
    // Makes sense?

    // Add an AJAX command to open a modal dialog with the form as the content.
    //@TODO Allow widget to pass the mode, either inline, Dialog or Append.

    if ($modal) {
      // New window.
      $response->addCommand(new OpenModalDialogCommand(t('Please follow the steps.'), $lawebforma, ['width' => '90%']));
    }
    else {

      // inline replacement
      $selector = 'edit-'.Html::cleanCssIdentifier($field_name ."_". $delta);
      // This will clear the message and start fresh session without data..
      if ($clear_saved) {
        // If $clear_saved was passed means the user wants to get rid
        // Of the previous saved session.
        // We delete both, the session and the accumulated errors.
        /** @var \Drupal\Core\TempStore\PrivateTempStore $tempstore */
        $tempstore = \Drupal::service('tempstore.private')->get('archipel');
        $tempstore->delete($clear_saved.'-draft');
        $tempstore->delete($clear_saved.'-errors');
        // Selector us built using the field name and the delta.
        $response->addCommand(new \Drupal\Core\Ajax\HtmlCommand('#' . $selector  .' > .fieldset-wrapper',
          $lawebforma));
      }
      else {
        $response->addCommand(new \Drupal\Core\Ajax\AppendCommand('#' . $selector,
          $lawebforma));
      }
      $selector2 = '[data-drupal-selector="'.$selector .'-strawberry-webform-open-modal"]';
      $selector3 = '[data-drupal-selector="'.$selector .'-strawberry-webform-close-modal"]';
      $response->addCommand(new \Drupal\Core\Ajax\InvokeCommand($selector2, 'toggleClass', ['js-hide']));
      $response->addCommand(new \Drupal\Core\Ajax\InvokeCommand($selector3, 'toggleClass', ['js-hide']));
    }
    return $response;

  }


  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  public function closeModalForm(Request $request)
  {

    $state = $request->get('state');
    $modal = $request->get('modal') ? $request->get('modal') : FALSE;

    // with $uuid the uuid of the entity that is being edited and for which
    // a widget is being openend in the form of a webform
    // field name the machine name of the field that contains the original data
    // inside that source entity

    [$source_uuid, $field_name, $delta, $widgetid] = explode(':', $state);
    $response = new AjaxResponse();

    if ($modal) {
      $response->addCommand(new CloseDialogCommand());
      return $response;
    }
    else {
      // inline replacement
      $selector = 'edit-'.Html::cleanCssIdentifier($field_name ."_". $delta);
      // Selector us built using the field name and the delta.
      $response->addCommand(new \Drupal\Core\Ajax\RemoveCommand('#'.$selector. ' .webform-ajax-form-wrapper'));
      $selector2 = '[data-drupal-selector="'.$selector .'-strawberry-webform-open-modal"]';
      $selector3 = '[data-drupal-selector="'.$selector .'-strawberry-webform-close-modal"]';
      $response->addCommand(new \Drupal\Core\Ajax\InvokeCommand($selector2, 'toggleClass', ['js-hide']));
      $response->addCommand(new \Drupal\Core\Ajax\InvokeCommand($selector3, 'toggleClass', ['js-hide']));
      /// Shows the Save buttons back.
      /// @TODO this should go into Drupal.behaviors.webformstrawberryHideNodeActions JS
      $response->addCommand(new \Drupal\Core\Ajax\InvokeCommand('.path-node .node-form div[data-drupal-selector="edit-actions"]', 'show', []));

    }
    return $response;


  }

  /**
   * @param $elements_in_data
   *    The Flattened Elements that intersect keys present in data.
   * @param $data
   *    The JSON data as an Array coming from an ADO
   * @return array
   *    Empty if no errors, if not associative array with keys of elements that are not compatible with data
   *    holding the original data, so we can restore it on persistence (when saving).
   */
  public static function validateDataAgainstWebformElements(array $elements_in_data, array &$data, array &$error_elements_why): array {
    // In case the saved data is "single valued" for a key
    // But the corresponding webform element is not
    // we cast to it multivalued, so it can be read/updated
    // If the element itself does not allow multiple, is not a composite, and we are passing an indexed array
    // we need to re-write data (take the first) to avoid a Render array error in Drupal 10.3
    // But also tell the user this form is not safe to use.
    $error_elements = [];
    foreach ($elements_in_data as $key => $elements_in_datum) {
      if (isset($elements_in_datum['#webform_multiple']) &&
        $elements_in_datum['#webform_multiple'] !== FALSE) {
        //@TODO should we log this operation for admins?
        $data['data'][$key] = (array) $data['data'][$key];
        if (!array_is_list($data['data'][$key])) {
          // means we just made a composite element a composite element! So make it a list
          // And check also if the Source can be read/edited by the form element.
          if ($elements_in_datum['#webform_composite_elements'] ?? NULL) {
            $current_source_subkeys = array_keys($data['data'][$key]);
            $element_subkeys = array_keys($elements_in_datum['#webform_composite_elements']);
            // OK if element has more/less. Not OK if the source data has other/more.
            $diff = array_diff($current_source_subkeys, $element_subkeys);
            if (count($diff)) {
              foreach ($diff as $index => $propery_not_present) {
                // Don't complain if empty ... we won't lose anything.
                if ($data['data'][$key][$propery_not_present] === "" or $data['data'][$key][$propery_not_present] === NULL) {
                  unset($diff[$index]);
                }
              }
              $diff = array_values($diff);
              if (count($diff)) {
                $error_elements_why[$key] = t('@key contains @property not available for <em>@element_name</em>', [
                  '@key' => $key,
                  '@property' => (count($diff) > 1 ? "properties " : "property ") . implode(",", $diff),
                  '@element_name' => $elements_in_datum['#title'] ?? $key,
                ]);
                $error_elements[$key] = $data['data'][$key];
              }
              else {
                $data['data'][$key] = [$data['data'][$key]];
              }
            }
            else {
              $data['data'][$key] = [$data['data'][$key]];
            }
          } else {
            // NO need to count here since this was originally an Object already.
            $data['data'][$key] = [$data['data'][$key]];
          }
        }
        else {
          // count the values. The Element count might be lower than the source data
          // Unlimited has $elements_in_datum['#webform_multiple'] = TRUE
          if (($elements_in_datum['#webform_multiple'] ?? FALSE) !== TRUE && (count($data['data'][$key]) > (int) $elements_in_datum['#webform_multiple'])) {
            $error_elements_why[$key] = t('@key contains @count which is larger than the multiple values limit of @max for <em>@element_name</em>' , [
              '@key' => $key,
              '@count' => count($data['data'][$key]) . " entries ",
              '@max' => (int) $elements_in_datum['#webform_multiple'],
              '@element_name' => $elements_in_datum['#title'] ?? $key,
            ]);
            $error_elements[$key] = $data['data'][$key];
          }
        }
      }
      else {
        // Not a multiple element. So check what we are getting here.
        if (is_array($data['data'][$key]) && !empty($data['data'][$key])) {
          if (array_is_list($data['data'][$key])) {
            if ($elements_in_datum['#webform_plugin_id'] == "entity_autocomplete" && count($data['data'][$key]) == 1) {
              // Do nothing. We accept this bc the element actually can load a single entry array.
            }
            elseif ($elements_in_datum['#webform_plugin_id'] == "webform_metadata_panoramatour") {
              // Do nothing. We accept this bc webform panorama tour builder is a special computed/complex field.
            }
            elseif (str_ends_with($elements_in_datum['#webform_plugin_id'], '_file') && count($data['data'][$key]) == 1) {
              // Do nothing for Files.
            }
            elseif (count($data['data'][$key]) == 1) {
              // Take the first entry/cast it to a single entry.
              $data['data'][$key] = $data['data'][$key][0];
            }
            else {
              // Multiple entries for a single valued element. Bad.
              $error_elements_why[$key] = t('@key contains multiple values but <em>@element_name</em> is configured for a single one', [
                '@key' => $key,
                '@element_name' => $elements_in_datum['#title'] ?? $key,
              ]);
              $error_elements[$key] = $data['data'][$key];
            }
          }
          else {
            // The data is an object.
            if ($elements_in_datum['#webform_composite_elements'] ?? NULL) {
              $current_source_subkeys = array_keys($data['data'][$key]);
              $element_subkeys = array_keys($elements_in_datum['#webform_composite_elements']);
              // OK if element has more/less. Not OK if the source data has other/more.
              $diff = array_diff($current_source_subkeys, $element_subkeys);
              if (count($diff)) {
                foreach ($diff as $index => $propery_not_present) {
                  // Don't complain if empty ... we won't lose anything.
                  if ($data['data'][$key][$propery_not_present] === "" or $data['data'][$key][$propery_not_present] === NULL) {
                    unset($diff[$index]);
                  }
                }
                $diff = array_values($diff);
                if (count($diff)) {
                  $error_elements_why[$key] = t('@key contains @property not available for <em>@element_name</em>', [
                    '@key' => $key,
                    '@property' => (count($diff) > 1 ? "properties " : "property ") . implode(",", $diff),
                    '@element_name' => $elements_in_datum['#title'] ?? $key,
                  ]);
                  $error_elements[$key] = $data['data'][$key];
                }
              }
            }
          }
        }
      }
    }
    return $error_elements;
  }


}
