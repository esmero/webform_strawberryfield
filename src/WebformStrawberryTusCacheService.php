<?php

namespace Drupal\webform_strawberryfield;

use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\SharedTempStore;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use TusPhp\Cache\AbstractCache;
use Drupal\Core\Database\Connection;

class WebformStrawberryTusCacheService extends AbstractCache {

  CONST COLLECTION = 'webform_strawberry.tus';

  /**
   * @var \Drupal\Core\TempStore\SharedTempStore
   */
  private readonly SharedTempStore $tempStore;


  public function __construct(SharedTempStoreFactory $tempStoreFactory, private readonly Connection $db) {
    $this->tempStore = $tempStoreFactory->get(static::COLLECTION);
  }

  public function get(string $key, bool $withExpired = FALSE) {
    return $this->tempStore->get($key);
  }

  /**
   * Sets a Cache entry
   *
   * @param string $key
   * @param $value
   *
   * @return void
   */
  public function set(string $key, $value): void {
    // when setting we need to get the cache
    $data = $this->get($key);
    if (!empty($data) && is_array($data)) {
      foreach ($value as $k => $v) {
        $data[$k] = $v;
      }
    }
    else {
      $data = $value;
    }
    try {
      $this->tempStore->set($key, $data);
    }
    catch (\Exception) {
      //@TODO: log errors here. Worried about locks.
      return;
    }
  }

  /**
   * Deletes a Cache Entry
   *
   * @param string $key
   *
   * @return bool
   */
  public function delete(string $key): bool {
    try {
      $this->tempStore->delete($key);
      return TRUE;
    }
    catch (\Exception) {
      //@TODO: log errors here. Worried about locks.
      return FALSE;
    }
  }

  /**
   * Lists all keys of a collection directly from DB.
   *
   * @return array
   */
  public function keys(): array {
    $query = $this->db->select('key_value_expire', 'kve');
    $query->addField('kve', 'name');
    $query->condition('collection', 'tempstore.shared.'.static::COLLECTION);
    return $query->execute()->fetchCol();
  }
}
