<?php

namespace Drupal\da_commerce_cart_sharing\Form;

use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\da_commerce_cart_sharing\CartLinkManager;
use Drupal\da_commerce_cart_sharing\Ajax\CloseFormCommand;

/**
 * Class SendShareableLinkForm.
 *
 * Adds Employee to not administrative department and
 * to administrative staff tab of administrative department.
 */
class SendShareableLinkForm extends FormBase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The renderer service instance.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Mail Manager interface.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * Language Manager Interface.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Logger Channel Factory Interface.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $logger;

  /**
   * AddDepartmentForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Renderer interface.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   Mail manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Language manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   Logger.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    RendererInterface $renderer,
    MailManagerInterface $mail_manager,
    LanguageManagerInterface $language_manager,
    LoggerChannelFactoryInterface $logger) {
    $this->entityTypeManager = $entityTypeManager;
    $this->renderer = $renderer;
    $this->mailManager = $mail_manager;
    $this->languageManager = $language_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('plugin.manager.mail'),
      $container->get('language_manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'send_shareable_link_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $prepared_form_values = NULL) {
    $form['system_messages'] = [
      '#markup' => '<div id="form-system-messages"></div>',
      '#weight' => -101,
      '#suffix' => '<div class="hide-form">',
    ];

    $form['order_id'] = [
      '#type' => 'hidden',
      '#value' => $prepared_form_values['commerce_order'],
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#ajax' => [
        'callback' => '::ajaxSubmitCallback',
        'event' => 'click',
        'progress' => [
          'type' => 'throbber',
        ],
      ],
      '#suffix' => '</div>',
    ];

    // Attach the close popup library.
    $form['#attached']['library'] = [
      'da_commerce_cart_sharing/close_popup',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $order_id = $form_state->getValue('order_id');
    $email = $form_state->getValue('email');
    $order = $this->entityTypeManager->getStorage('commerce_order')->load($order_id);

    $url = CartLinkManager::generateUrl($order)->toString();

    // Send email.
    $parameters = [
      'subject' => $this->t('Share cart'),
      'body' => $this->t('User share cart with you. Please, check @url', ['@url' => $url]),
    ];
    if (!empty($email)) {
      $this->mailManager->mail(
        'da_commerce_cart',
        'share_cart_email',
        $email,
        $this->languageManager->getCurrentLanguage()->getId(),
        $parameters
      );
    }

    $this->messenger()->addMessage('Email was sent!');
    $this->logger->get('cart_logger')->info($url);
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxSubmitCallback(array &$form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();
    $this->handleAjaxSubmit($form_state, $ajax_response);

    $message = [
      '#type' => 'status_messages',
    ];
    $messages = $this->renderer->renderRoot($message);

    $ajax_response->addCommand(new HtmlCommand('#form-system-messages', $messages));

    return $ajax_response;
  }

  /**
   * Handles ajax request.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param \Drupal\Core\Ajax\AjaxResponse $ajax_response
   *   Ajax response.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Ajax Response.
   */
  protected function handleAjaxSubmit(FormStateInterface $form_state, AjaxResponse $ajax_response) {
    $errors = $this->messenger()->messagesByType('error');

    if (empty($errors)) {
      $ajax_response->addCommand(new CloseFormCommand(1000));
      $ajax_response->addCommand(new HtmlCommand('.hide-form', ''));
    }

    return $ajax_response;
  }

}
