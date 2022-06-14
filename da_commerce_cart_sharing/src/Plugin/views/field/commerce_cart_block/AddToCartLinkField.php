<?php

namespace Drupal\da_commerce_cart_sharing\Plugin\views\field\commerce_cart_block;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commerce_add_to_cart_link\AddToCartLink;

/**
 * A handler to provide a field that is completely custom by the administrator.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("add_to_cart_link_field")
 */
class AddToCartLinkField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // We don't need to modify query for this field.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $result = [];

    $product_variation = $values->_entity->getPurchasedEntity();
    $id = $product_variation->id();
    if (!empty($id)) {
      $url = AddToCartLink::fromVariationId($id)->url();
      $result[] = [
        '#type' => 'link',
        '#url' => $url,
        '#title' => 'Add to cart 1 product',
      ];
      $result[] = [
        '#type' => 'markup',
        '#markup' => ' || ',
      ];

      $url = AddToCartLink::fromVariationId($id)->url();
      $query = (array) $url->getOption('query');
      if ($values->_entity->get('quantity')->getValue()[0]['value'] != 1) {
        $query += ['quantity' => $values->_entity->get('quantity')->getValue()[0]['value']];
      }
      $url->setOption('query', $query);

      $result[] = [
        '#type' => 'link',
        '#url' => $url,
        '#title' => 'Add to cart all quantity',
      ];
    }

    return $result;
  }

}
