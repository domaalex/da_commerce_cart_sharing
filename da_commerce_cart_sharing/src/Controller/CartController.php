<?php

namespace Drupal\da_commerce_cart_sharing\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Form\FormBuilder;
use Drupal\da_commerce_cart_sharing\Component\Utility\HtmlExtra;
use Drupal\da_commerce_cart_sharing\CartLinkManager;
use Drupal\commerce\Context;
use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_order\Resolver\OrderTypeResolverInterface;
use Drupal\commerce_price\Resolver\ChainPriceResolverInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Builds an cart page.
 */
class CartController extends ControllerBase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Entity form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * The cart manager.
   *
   * @var \Drupal\commerce_cart\CartManagerInterface
   */
  protected $cartManager;

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * The chain base price resolver.
   *
   * @var \Drupal\commerce_price\Resolver\ChainPriceResolverInterface
   */
  protected $chainPriceResolver;

  /**
   * The order type resolver.
   *
   * @var \Drupal\commerce_order\Resolver\OrderTypeResolverInterface
   */
  protected $orderTypeResolver;

  /**
   * The current store.
   *
   * @var \Drupal\commerce_store\CurrentStoreInterface
   */
  protected $currentStore;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * CartController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   EntityTypeManager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Renderer.
   * @param \Drupal\Core\Form\FormBuilder $form_builder
   *   Form Builder.
   * @param \Drupal\commerce_cart\CartManagerInterface $cart_manager
   *   The cart manager.
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider.
   * @param \Drupal\commerce_order\Resolver\OrderTypeResolverInterface $order_type_resolver
   *   The order type resolver.
   * @param \Drupal\commerce_store\CurrentStoreInterface $current_store
   *   The current store.
   * @param \Drupal\commerce_price\Resolver\ChainPriceResolverInterface $chain_price_resolver
   *   The chain base price resolver.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RendererInterface $renderer,
    FormBuilder $form_builder,
    CartManagerInterface $cart_manager,
    CartProviderInterface $cart_provider,
    OrderTypeResolverInterface $order_type_resolver,
    CurrentStoreInterface $current_store,
    ChainPriceResolverInterface $chain_price_resolver,
    AccountInterface $current_user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
    $this->formBuilder = $form_builder;
    $this->cartManager = $cart_manager;
    $this->cartProvider = $cart_provider;
    $this->orderTypeResolver = $order_type_resolver;
    $this->currentStore = $current_store;
    $this->chainPriceResolver = $chain_price_resolver;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('form_builder'),
      $container->get('commerce_cart.cart_manager'),
      $container->get('commerce_cart.cart_provider'),
      $container->get('commerce_order.chain_order_type_resolver'),
      $container->get('commerce_store.current_store'),
      $container->get('commerce_price.chain_price_resolver'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function sendShareableLink(OrderInterface $commerce_order) {
    if (!HtmlExtra::getIsAjax()) {
      return new RedirectResponse('/');
    }
    $prepared_form_values = [
      'commerce_order' => $commerce_order->id(),
    ];
    $send_shareable_link_form = $this->formBuilder->getForm('Drupal\da_commerce_cart_sharing\Form\SendShareableLinkForm', $prepared_form_values);

    return [
      '#markup' => render($send_shareable_link_form),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCart(OrderInterface $commerce_order, $hash = NULL) {
    $view = views_embed_view('shareable_cart', 'block_1');
    $args = [
      'order_id' => $commerce_order->id(),
    ];
    $view['#arguments'] = $args;
    $view = $this->renderer->render($view);

    $url = CartLinkManager::generateUrlFullAddToCart($commerce_order)->toString();

    return [
      '#theme' => 'shared_cart',
      '#title' => 'Shared cart',
      '#url' => $url,
      '#view' => $view,
    ];

  }

  /**
   * Checks access.
   */
  public function access($commerce_order = NULL, $hash = NULL) {
    if (empty($commerce_order) || empty($hash)) {
      return AccessResult::forbidden();
    }

    $order = $this->entityTypeManager->getStorage('commerce_order')->load($commerce_order);
    if (empty($order)) {
      return AccessResult::forbidden();
    }
    if ($hash === CartLinkManager::generateHash($order)) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

  /**
   * Performs the add to cart action and redirects to cart.
   *
   * @param Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The product entity.
   * @param string $hash
   *   The Hash.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the cart after adding the product.
   *
   * @throws \Exception
   *   When the call to self::selectStore() throws an exception because the
   *   entity can't be purchased from the current store.
   */
  public function addToCartFull(OrderInterface $commerce_order, $hash = NULL) {
    $combine = TRUE;
    $order_items = $commerce_order->getItems();
    if (!empty($order_items)) {
      foreach ($order_items as $order_item) {
        $quantity = ($order_item->get('quantity')->getValue()[0]['value']) ? $order_item->get('quantity')->getValue()[0]['value'] : 1;
        $commerce_product_variation = $order_item->getPurchasedEntity();

        $order_item = $this->cartManager->createOrderItem($commerce_product_variation, $quantity);
        $store = $this->selectStore($commerce_product_variation);

        $context = new Context($this->currentUser, $store);
        // Explicitly resolve the product price. @todo check necessity after https://www.drupal.org/project/commerce/issues/3088582 has been fixed.
        $resolved_price = $this->chainPriceResolver->resolve($commerce_product_variation, $quantity, $context);
        $order_item->setUnitPrice($resolved_price);

        $order_type_id = $this->orderTypeResolver->resolve($order_item);
        $cart = $this->cartProvider->getCart($order_type_id, $store);
        if (!$cart) {
          $cart = $this->cartProvider->createCart($order_type_id, $store);
        }
        $this->cartManager->addOrderItem($cart, $order_item, $combine);
      }
    }

    return $this->redirect('commerce_cart.page');
  }

  /**
   * Selects the store for the given variation.
   *
   * If the variation is sold from one store, then that store is selected.
   * If the variation is sold from multiple stores, and the current store is
   * one of them, then that store is selected.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface $variation
   *   The variation being added to cart.
   *
   * @throws \Exception
   *   When the variation can't be purchased from the current store.
   *
   * @return \Drupal\commerce_store\Entity\StoreInterface
   *   The selected store.
   */
  protected function selectStore(ProductVariationInterface $variation) {
    $stores = $variation->getStores();
    if (count($stores) === 1) {
      $store = reset($stores);
    }
    else {
      $store = $this->currentStore->getStore();
      if (!in_array($store, $stores)) {
        // Indicates that the site listings are not filtered properly.
        throw new \Exception("The given entity can't be purchased from the current store.");
      }
    }

    return $store;
  }

}
