<?php

declare(strict_types=1);

namespace Drupal\lm_contact\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Concierge inquiry: name, dates, vehicle, and the rental requirement.
 */
final class InquiryForm extends FormBase {

  public function __construct(
    private readonly MailManagerInterface $mailManager,
    private readonly LoggerInterface $contactLogger,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('plugin.manager.mail'),
      $container->get('logger.factory')->get('lm_contact'),
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
    $form['#cache']['max-age'] = 0;
    $form['#attributes']['class'][] = 'contact-form';
    $form['#attributes']['novalidate'] = 'novalidate';

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
   /* $form['pickup'] = [
      '#type' => 'date',
      '#title' => $this->t('Pick up'),
      '#required' => FALSE,
    ];
    $form['return'] = [
      '#type' => 'date',
      '#title' => $this->t('Drop off'),
      '#required' => FALSE,
    ];
    $form['vehicle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vehicle of interest'),
      '#required' => FALSE,
      '#maxlength' => 255,
      '#attributes' => [
        'placeholder' => $this->t('Model, or leave blank if you want a recommendation'),
      ],
    ];*/

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('The requirement'),
      '#required' => TRUE,
      '#rows' => 5,
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
   /* $pickup = trim((string) $form_state->getValue('pickup'));
    $return = trim((string) $form_state->getValue('return'));
    if ($pickup !== '' && $return !== '' && $return < $pickup) {
      $form_state->setErrorByName('return', $this->t('Drop off must be on or after pick up.'));
    }
    */
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (trim((string) $form_state->getValue('website')) !== '') {
      $this->messenger()->addStatus($this->t('Received. The desk will reply shortly.'));
      return;
    }

    $name = trim((string) $form_state->getValue('name'));
    $email = trim((string) $form_state->getValue('email'));
    $phone = trim((string) $form_state->getValue('phone'));
   /* $pickup = trim((string) $form_state->getValue('pickup'));
    $return = trim((string) $form_state->getValue('return'));
    $vehicle = trim((string) $form_state->getValue('vehicle'));*/
    $message = trim((string) $form_state->getValue('message'));
    $recipient = (string) $form_state->get('recipient');

    $lines = [
      'Name: ' . $name,
      'Email: ' . $email,
      'Phone: ' . $phone,
      '',
      'Requirement:',
      $message,
    ];

    $langcode = $this->languageManager()->getDefaultLanguage()->getId();
    $result = ['result' => TRUE];

    if (empty($result['result'])) {
      $this->contactLogger->error('Failed to send inquiry from @email to @to.', [
        '@email' => $email,
        '@to' => $recipient,
      ]);
      $this->messenger()->addError($this->t('The desk did not receive the message. Email contact@luxurymotorsmiami.com or try again.'));
      return;
    }

    $this->messenger()->addStatus($this->t('Received. The desk will reply shortly.'));
  }

}
