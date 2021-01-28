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
use Drupal\Core\Entity\ContentEntityInterface;


use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * StrawberryRunnerModalController class.
 */
class StrawberryRunnerModalController extends ControllerBase
{

    /**
     * Callback for opening the modal form.
     * @param WebformInterface|NULL $webform
     * @param Request $request
     * @return AjaxResponse
     *
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     *   Thrown when update.php should not be accessible.
     */
    public function openModalForm(WebformInterface $webform = NULL, Request $request)
    {

        // @see \Drupal\archipel\Plugin\Field\FieldWidget\StrawberryFieldWebFormWidget::formElement
        //  Request Arguments we are expecting:
        // 'webform' =>  $my_webform_machinename,
        // 'source_entity_types' => $entity_type,
        // 'state'=> "$entity_uuid:$this_field_name";
        // Check!

        \Drupal::entityTypeManager()->getAccessControlHandler('node')->createAccess('article');

        $source_entity_types = $request->get('source_entity_types');

        list($source_entity_type, $bundle) = explode(':', $source_entity_types);
        //@TODO allow some type of per bundle hook?
        $state = $request->get('state');
        $modal = $request->get('modal') ? $request->get('modal') : FALSE;

        // with $uuid the uuid of the entity that is being edited and for which
        // a widget is being openend in the form of a webform
        // field name the machine name of the field that contains the original data
        // inside that source entity

        list($source_uuid, $field_name, $delta, $widgetid) = explode(':', $state);

        // @TODO check if all our arguments pass the format test.
        // Can be done via regex but i prefer next option...
        // throw new \InvalidArgumentException('Data type must be in the form of
        // "entityUUID:FIELD_NAME:DELTA:someSHA1hashthatidentifiesthegtriggeringwidget"');

        // If uuid does not exist then well, we need to return with an error..
        // if field name missing/non existing the same
        // @TODO deal with exceptions

        try {
            //@var $entities \Drupal\Core\Entity\ContentEntityInterface[] */
            $entities = \Drupal::entityTypeManager()->getStorage($source_entity_type)->loadByProperties(['uuid' => $source_uuid]);
            // IF this does not work, either the entity is new! or it does not exist at all.
        }
        catch (\Exception $e) {
            // @todo really make some fuzz if this happens.
            // @todo we need to be super responsive about all the errors
            // I image two layers, simple to follow issues with a code for endusers
            // And a very deep explanation for admins and devs that match the code

            \Drupal::messenger()->addError($this->t('We could not find the referenced Entity @entity_type. Please report this to your Site Manage.',['@entity_type' => $source_entity_type]));
            // Really no need to persist after this.
          $response = new AjaxResponse();
          $notfound = [
            '#type' => 'markup',
            '#markup' => '<p>Ups, missing Form!<p>',
          ];
          $this->messenger->addWarning('Seems your configured Form for does not exist anymore. Please correct or check with your Site admin.', MessengerInterface::TYPE_WARNING);
          $response->addCommand(new OpenModalDialogCommand(t('Please follow the steps.'), $notfound, ['width' => '90%']));
          return $response;
        }
        //@var $source_entity \Drupal\Core\Entity\FieldableEntityInterface */
        $source_entity = null;

        foreach($entities as $entity) {
            // Means there was an entity stored! hu!
            // if you are following this you will know this foreach
            // makes little sense because we will either get a single one or none
            // but! makes sense anyway, shorter than checking if there, and if so
            // getting the first!
            //@var $source_entity \Drupal\Core\Entity\FieldableEntityInterface */
            $vid = \Drupal::entityTypeManager()
              ->getStorage($source_entity_type)
              ->getLatestRevisionId($entity->id());

            $source_entity = $vid ? \Drupal::entityTypeManager()->getStorage($source_entity_type)->loadRevision($vid) : $entity;
            if (!$source_entity->access('update')) {
                throw new AccessDeniedHttpException('Sorry, seems like you can are not allowed to see this or to be here at all!');
            }
        }

        $data = array();

        // Stores our original field data
        // @TODO i know it needs to have a base value
        // but looks weird... refactor!

        $fielddata = array();
        // If new this won't exist
        $entityid = NULL;
        // If we actually loaded the entity then lets fetch the saved field value
        // @see \Drupal\archipel\Plugin\Field\FieldType\StrawberryField::propertyDefinitions
        // @var $source_entity \Drupal\Core\Entity\FieldableEntityInterface */
        if ($source_entity) {
            // In case we are editing an existing entity, this one gets the
            // Strawberryfield value
           $alldata = $source_entity->get($field_name)->getValue();
           $fielddata['value'] = !empty($alldata) ? $alldata[$delta]['value']: "{}";
           $entityid = $source_entity->id();
        }

        $stored_value = (isset($fielddata['value']) && !empty($fielddata['value'])) ? $fielddata['value'] : "{}";

        $data_defaults = [
            'strawberry_field_widget_state_id' => $widgetid,
            // Can't remember why, but seems useful to pass around
            'strawberry_field_widget_source_entity_uuid' => $source_uuid,
            'strawberry_field_widget_source_entity_id' => $entityid,
            'strawberry_field_stored_values' => json_decode($stored_value,true)
        ];

        if (!isset($fielddata['value']) || empty($fielddata['value'])) {
            // No data
            $data['data'] = $data_defaults  +
                [
                'label' => 'New metadata'
                ];
        }
        else {
            $data['data'] = $data_defaults + json_decode($stored_value,true);
          // In case the saved data is "single valued" for a key
          // But the corresponding webform element is not
          // we cast to it multi valued so it can be read/updated
          /* @var \Drupal\webform\WebformInterface $webform */
          $webform_elements  = $webform->getElementsInitializedFlattenedAndHasValue();
          $elements_in_data = array_intersect_key($webform_elements, $data['data']);
          if (is_array($elements_in_data) && count($elements_in_data)>0) {
            foreach($elements_in_data as $key => $elements_in_datum) {
              if (isset($elements_in_datum['#webform_multiple']) &&
                $elements_in_datum['#webform_multiple']!== FALSE) {
                $data['data'][$key] = (array) $data['data'][$key];
              }
            }
          }
        }

      $confirmation_message = $webform->getSetting('confirmation_message', FALSE);
      $confirmation_message = !empty($confirmation_message) && strlen(trim($confirmation_message)) > 0 ? $confirmation_message : $this->t(
        'Thanks, you are all set! Please Save the content to persist the changes.');

      // Lets make sure this puppy never redirects
      // And also we need to reset some defaults here
      // @see \Drupal\webform\Entity\Webform::getDefaultSettings
      // @TODO autofill needs to be a setting that is respected
      // But Kerri thought this could get in our way
      // Need to thing about this.
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
          // Selector us built using the field name and the delta.
          $response->addCommand(new \Drupal\Core\Ajax\AppendCommand('#'.$selector, $lawebforma));
          $selector2 = '[data-drupal-selector="'.$selector .'-strawberry-webform-open-modal"]';
          $selector3 = '[data-drupal-selector="'.$selector .'-strawberry-webform-close-modal"]';
          $response->addCommand(new \Drupal\Core\Ajax\InvokeCommand($selector2, 'toggleClass', ['js-hide']));
          $response->addCommand(new \Drupal\Core\Ajax\InvokeCommand($selector3, 'toggleClass', ['js-hide']));
        }
      return $response;

    }



  public function closeModalForm(Request $request)
  {

    $state = $request->get('state');
    $modal = $request->get('modal') ? $request->get('modal') : FALSE;

    // with $uuid the uuid of the entity that is being edited and for which
    // a widget is being openend in the form of a webform
    // field name the machine name of the field that contains the original data
    // inside that source entity

    list($source_uuid, $field_name, $delta, $widgetid) = explode(':', $state);
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


}
