<?php
/**
 * Created by PhpStorm.
 * User: dpino
 * Date: 10/1/19
 * Time: 11:30 AM
 */

namespace Drupal\webform_strawberryfield\Ajax;
use Drupal\Core\Ajax\CommandInterface;
class AddHotSpotCommand implements CommandInterface
{

  /**
   * Constructs an Add HotSpot Command.
   *
   * @param $selector
   * @param $hotspot
   * @param $sceneid
   */
  public function __construct(
      /**
       * The JQuery() selector
       */
      protected $selector,
      /**
       * The Hotspot Object
       *
       * @var \stdClass;
       */
      protected $hotspot,
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
      'command' => 'webform_strawberryfield_pannellum_editor_addHotSpot',
      'selector' => $this->selector,
      'hotspot' => $this->hotspot,
      'sceneid' => $this->sceneid,
    ];
  }

}

