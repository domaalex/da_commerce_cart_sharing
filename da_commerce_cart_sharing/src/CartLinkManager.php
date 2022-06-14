<?php

namespace Drupal\da_commerce_cart_sharing;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;

/**
 * CartLinkManager service.
 */
class CartLinkManager {

  /**
   * Helper.
   */
  public static function generateUrl(OrderInterface $order) {
    return new Url('da_commerce_cart_sharing.get_cart', [
      'commerce_order' => $order->id(),
      'hash' => self::generateHash($order),
    ], ['absolute' => TRUE]);
  }

  /**
   * Helper.
   */
  public static function generateHash(OrderInterface $commerce_order) {
    return Crypt::hmacBase64($commerce_order->id(), Settings::getHashSalt());
  }

  /**
   * Helper.
   */
  public static function generateUrlFullAddToCart(OrderInterface $order) {
    return new Url('da_commerce_cart_sharing.add_to_cart_full', [
      'commerce_order' => $order->id(),
      'hash' => self::generateHash($order),
    ], ['absolute' => TRUE]);
  }

}
