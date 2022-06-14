<?php

namespace Drupal\da_commerce_cart_sharing\Component\Utility;

use Drupal\Component\Utility\Html;

/**
 * Provides a way to get if a request is an AJAX request.
 *
 * @ingroup utility
 */
class HtmlExtra extends Html {

  /**
   * Gets if this request is an Ajax request.
   */
  public static function getIsAjax() {
    return parent::$isAjax;
  }

}
