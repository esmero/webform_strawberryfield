<?php
/**
 * Created by PhpStorm.
 * User: dpino
 * Date: 10/1/19
 * Time: 11:30 AM
 */

namespace Drupal\webform_strawberryfield\Ajax;
use Drupal\Core\Ajax\CommandInterface;
class RemoveHotSpotCommand implements CommandInterface
{

  /**
   * Constructs a Remove HotSpot Command.
   *
   * @param $selector
   * @param $hotspotid
   * @param $sceneid
   */
  public function __construct(
      /**
       * The JQuery() selector
       */
      protected $selector,
      /**
       * The Hotspot ID
       *
       * @var string;
       */
      protected $hotspotid,
      /**
       * The Scene ID
       */
      protected $sceneid
  )
  {
  }

  /**
   * Implements Drupal\Core\Ajax\CommandInterface:render().
   */
  public function render() {

    return [
      'command' => 'webform_strawberryfield_pannellum_editor_removeHotSpot',
      'selector' => $this->selector,
      'hotspotid' => $this->hotspotid,
      'sceneid' => $this->sceneid,
    ];
  }

}

