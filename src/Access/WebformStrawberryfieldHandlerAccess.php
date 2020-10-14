<?php


namespace Drupal\webform_strawberryfield\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform_strawberryfield\Plugin\WebformHandler\strawberryFieldharvester;

/**
 * Defines the custom access control handler for the webform submission entities.
 */
class WebformStrawberryfieldHandlerAccess {

  /**
   * Check that webform submission has a strawberryfield webform handler and the user can create bundled Nodes
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public static function checkIngestEntityAccess(WebformSubmissionInterface $webform_submission, AccountInterface $account) {
    $handlers = $webform_submission->getWebform()->getHandlers();
    foreach ($handlers as $id => $webform_handler) {
      if (($webform_handler instanceof strawberryFieldharvester) && $webform_handler->isEnabled()) {
        $configuration = $webform_handler->getConfiguration();
        if (isset($configuration['settings']['ado_crud_enabled']) && $configuration['settings']['ado_crud_enabled']) {
          $bundle =  $configuration['settings']['ado_settings']['bundles'];
          $access = \Drupal::entityTypeManager()
            ->getAccessControlHandler('node')
            ->createAccess($bundle, $account, [], false);
          if ($access) {
            return AccessResult::allowed();
          }
        }
      }
    }
    return AccessResult::forbidden();
  }

}
