<?php

declare(strict_types=1);

namespace Drupal\lm_contact\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\lm_contact\Form\InquiryForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Inquiry form plus email, phone, and WhatsApp lines.
 *
 * @Block(
 *   id = "lm_contact",
 *   admin_label = @Translation("Contact desk"),
 *   category = @Translation("Luxury Motors")
 * )
 */
final class ContactBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly FormBuilderInterface $formBuilder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
    );
  }

  public function defaultConfiguration(): array {
    return [
      'kicker' => 'Concierge',
      'heading' => 'Tell us the requirement',
      'intro' => 'Dates, model, and how the desk should reach you. Replies go out from Miami.',
      'email' => 'contact@luxurymotorsmiami.com',
      'phone' => '',
      'phone_alt' => '',
      'whatsapp' => '',
    ];
  }

  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);
    $config = $this->configuration;

    $form['kicker'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Kicker'),
      '#default_value' => $config['kicker'],
      '#maxlength' => 64,
    ];
    $form['heading'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Heading'),
      '#default_value' => $config['heading'],
      '#maxlength' => 128,
      '#required' => TRUE,
    ];
    $form['intro'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Intro'),
      '#default_value' => $config['intro'],
      '#rows' => 3,
    ];
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#default_value' => $config['email'],
      '#required' => TRUE,
    ];
    $form['phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone'),
      '#default_value' => $config['phone'],
      '#description' => $this->t('Leave empty until the number is confirmed.'),
    ];
    $form['phone_alt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Second phone'),
      '#default_value' => $config['phone_alt'],
      '#description' => $this->t('Leave empty until the number is confirmed.'),
    ];
    $form['whatsapp'] = [
      '#type' => 'textfield',
      '#title' => $this->t('WhatsApp'),
      '#default_value' => $config['whatsapp'],
      '#description' => $this->t('International number, digits only or with +. Leave empty until you add it.'),
    ];

    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state): void {
    parent::blockSubmit($form, $form_state);
    foreach (['kicker', 'heading', 'intro', 'email', 'phone', 'phone_alt', 'whatsapp'] as $key) {
      $this->configuration[$key] = trim((string) $form_state->getValue($key));
    }
  }

  public function build(): array {
    $email = $this->configuration['email'] ?: 'contact@luxurymotorsmiami.com';

    return [
      '#theme' => 'lm_contact',
      '#kicker' => $this->configuration['kicker'],
      '#heading' => $this->configuration['heading'],
      '#intro' => $this->configuration['intro'],
      '#form' => $this->formBuilder->getForm(InquiryForm::class, $email),
      '#channels' => $this->channels($email),
    ];
  }

  public function getCacheMaxAge(): int {
    return 0;
  }

  /**
   * @return list<array{id: string, label: string, value: string, href: string, pending: bool}>
   */
  private function channels(string $email): array {
    return [
      $this->channel('email', (string) $this->t('Email'), $email, 'mailto:'),
      $this->channel('phone', (string) $this->t('Phone'), (string) $this->configuration['phone'], 'tel:'),
      $this->channel('phone_alt', (string) $this->t('Phone'), (string) $this->configuration['phone_alt'], 'tel:'),
      $this->channel('whatsapp', (string) $this->t('WhatsApp'), (string) $this->configuration['whatsapp'], 'whatsapp'),
    ];
  }

  /**
   * @return array{id: string, label: string, value: string, href: string, pending: bool}
   */
  private function channel(string $id, string $label, string $raw, string $scheme): array {
    $value = trim($raw);
    $pending = !$this->isReady($value);

    $href = '';
    if (!$pending) {
      $href = match ($scheme) {
        'mailto:' => 'mailto:' . $value,
        'tel:' => 'tel:' . $this->digits($value, TRUE),
        'whatsapp' => 'https://wa.me/' . $this->digits($value, FALSE),
        default => '',
      };
    }

    return [
      'id' => $id,
      'label' => $label,
      'value' => $pending ? (string) $this->t('To be confirmed') : $value,
      'href' => $href,
      'pending' => $pending,
    ];
  }

  private function isReady(string $value): bool {
    if ($value === '') {
      return FALSE;
    }
    $probe = mb_strtolower($value);
    foreach (['definir', 'tbd', 'n/a', 'coming soon', 'pendiente'] as $marker) {
      if (str_contains($probe, $marker)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  private function digits(string $value, bool $keep_plus): string {
    $kept = preg_replace($keep_plus ? '/[^\d+]/' : '/\D+/', '', $value) ?? '';
    return $kept;
  }

}
