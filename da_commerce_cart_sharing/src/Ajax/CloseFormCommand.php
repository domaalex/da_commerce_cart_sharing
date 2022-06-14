<?php

namespace Drupal\da_commerce_cart_sharing\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Class to close dialog window.
 */
class CloseFormCommand implements CommandInterface {

  /**
   * Delay time to close dialog modal.
   *
   * @var int
   */
  protected $delayTime;

  /**
   * {@inheritdoc}
   */
  public function __construct($delayTime = 0) {
    $this->delayTime = $delayTime;
  }

  /**
   * Implements Drupal\Core\Ajax\CommandInterface:render().
   */
  public function render() {
    return [
      'command' => 'closePopup',
      'delayTime' => $this->delayTime,
    ];
  }

}
