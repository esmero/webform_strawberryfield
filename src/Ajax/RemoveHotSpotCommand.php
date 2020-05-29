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
   * The Hotspot ID
   *
   * @var string;
   */
  protected $hotspotid;


  /**
   * The Scene ID
   *
   * @var string
   */
  protected $sceneid;

  /**
   * The JQuery() selector
   *
   * @var string
   */
  protected $selector;

  /**
   * Constructs an AlertCommand object.
   *
   * @param string $text
   *   The text to be displayed in the alert box.
   */
  public function __construct($selector, $hotspotid, $sceneid) {
    $this->selector = $selector;
    $this->hotspotid = $hotspotid;
    $this->sceneid = $sceneid;

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

