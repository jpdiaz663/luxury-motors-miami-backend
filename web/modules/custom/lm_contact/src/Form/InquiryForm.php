<?php

declare(strict_types=1);

namespace Drupal\lm_contact\Form;

use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lm_contact\Event\InquirySubmittedEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Concierge inquiry: name, dates, vehicle, and the rental requirement.
 */
final class InquiryForm extends FormBase {

  private const FLOOD_NAME = 'lm_contact.inquiry';
  private const FLOOD_WINDOW = 3600;
  private const FLOOD_LIMIT = 3;

  public function __construct(
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly FloodInterface $flood,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('event_dispatcher'),
      $container->get('flood'),
    );
  }

  public function getFormId(): string {
    return 'lm_contact_inquiry_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, string $recipient = ''): array {
    $recipient = trim($recipient);
    $form_state->set('recipient', $recipient !== '' ? $recipient : 'contact@luxurymotorsmiami.com');

    $form['#theme'] = 'lm_contact_inquiry_form';
    $form['#theme_wrappers'] = [];
    $form['#theme_wrappers'] = [];
    $form['#cache']['max-age'] = 0;
    $form['#attributes']['class'][] = 'contact-form';
    $form['#attributes']['novalidate'] = 'novalidate';

    $form['#attributes']['method'] = 'post';
    $form['#attributes']['accept-charset'] = 'UTF-8';
    if (!empty($form['#action'])) {
      $form['#attributes']['action'] = $form['#action'];
    }

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Full name'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#attributes' => ['autocomplete' => 'name'],
    ];
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#attributes' => ['autocomplete' => 'email'],
    ];
    $form['phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone'),
      '#required' => TRUE,
      '#attributes' => ['autocomplete' => 'tel'],
    ];
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('The requirement'),
      '#required' => TRUE,
      '#rows' => 5,
      '#maxlength' => 4000,
      '#attributes' => [
        'placeholder' => $this->t('Occasion, passenger count, chauffeur, airport, hotel, or anything the desk should know.'),
      ],
    ];
    $form['website'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Website'),
      '#required' => FALSE,
      '#maxlength' => 255,
      '#attributes' => [
        'tabindex' => '-1',
        'autocomplete' => 'off',
      ],
      '#wrapper_attributes' => ['class' => ['contact-honeypot']],
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Send requirement'),
        '#attributes' => ['class' => ['btn', 'btn--primary', 'contact-form__submit']],
      ],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (trim((string) $form_state->getValue('website')) !== '') {
      return;
    }
    if ($form_state->hasAnyErrors()) {
      return;
    }
    if (!$this->flood->isAllowed(self::FLOOD_NAME, self::FLOOD_LIMIT, self::FLOOD_WINDOW)) {
      $form_state->setErrorByName('message', $this->t('Too many messages. Try again later.'));
      return;
    }
    $this->flood->register(self::FLOOD_NAME, self::FLOOD_WINDOW);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (trim((string) $form_state->getValue('website')) !== '') {
      $this->messenger()->addStatus($this->t('Received. The desk will reply shortly.'));
      return;
    }

    $ip = (string) $this->getRequest()->getClientIp();
    try {
     
      $this->eventDispatcher->dispatch(new InquirySubmittedEvent(
        name: $this->plain((string) $form_state->getValue('name'), 255),
        email: $this->plain((string) $form_state->getValue('email'), 254),
        phone: $this->plain((string) $form_state->getValue('phone'), 64),
        requirement: $this->plain((string) $form_state->getValue('message'), 4000),
        deskRecipient: $this->plain((string) $form_state->get('recipient'), 254),
        ipHash: $ip !== '' ? hash('sha256', $ip) : NULL,
      ));
    }
    catch (\Throwable $e) {
      dd('entra');
      $this->logger('lm_contact')->error('Inquiry submitted event failed: @error', [
        '@error' => mb_substr($e->getMessage(), 0, 500),
      ]);
    }

    $this->messenger()->addStatus($this->t('Received. The desk will reply shortly.'));
  }

  private function plain(string $value, int $max): string {
    $value = trim(strip_tags($value));
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? $value;
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    return mb_substr($value, 0, $max);
  }

}
