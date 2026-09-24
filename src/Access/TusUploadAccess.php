<?php


namespace Drupal\webform_strawberryfield\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform_strawberryfield\Plugin\WebformHandler\strawberryFieldharvester;
use Drupal\webform_strawberryfield\Plugin\WebformHandler\strawberryFieldhandPicker;

/**
 * Defines the custom access control handler for the webform Tus Upload routes.
 */
class TusUploadAccess {

  /**
   * Check if the key used  + webform match/are a TUS and the webform is ADO driven.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   A webform submission.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccess(WebformInterface $webform, string $key, AccountInterface $account) {
    $possible_upload_element = $webform->getElement($key);
    $is_tus = FALSE;
    $access_result = AccessResult::forbidden()->addCacheableDependency($webform)->cachePerUser();
    if ($account->isAnonymous()) {
      return $access_result;
    }
    if ($possible_upload_element && $possible_upload_element['#type'] == 'webform_tus_file') {
      $is_tus = TRUE;
    }
    $handlers = $webform->getHandlers();
    foreach ($handlers as $id => $webform_handler) {
      if (($webform_handler instanceof strawberryFieldharvester || $webform_handler instanceof strawberryFieldhandPicker) && $webform_handler->isEnabled()) {
        return AccessResult::allowedIf($is_tus)->addCacheableDependency($webform)
        ->cachePerUser();
      }
    }
    return $access_result;
  }
}
