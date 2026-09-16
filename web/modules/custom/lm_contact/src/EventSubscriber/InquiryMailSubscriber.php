<?php

declare(strict_types=1);

namespace Drupal\lm_contact\EventSubscriber;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\lm_contact\Event\InquirySubmittedEvent;
use Drupal\lm_notify\Message;
use Drupal\lm_notify\NotifierInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends guest acknowledgment and desk copy after an inquiry is submitted.
 */
final class InquiryMailSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly NotifierInterface $notifier,
    private readonly RendererInterface $renderer,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EmailValidatorInterface $emailValidator,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      InquirySubmittedEvent::class => 'onSubmitted',
    ];
  }

  public function onSubmitted(InquirySubmittedEvent $event): void {
    $site = $this->configFactory->get('system.site');
    $siteName = (string) ($site->get('name') ?? 'Luxury Motors');
    $siteUrl = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();
    $siteMail = trim((string) ($site->get('mail') ?? ''));
    $desk ='iamputorraider09@gmail.com';

    $view = [
      'name' => $event->name,
      'email' => $event->email,
      'phone' => $event->phone,
      'requirement' => $event->requirement,
      'site_name' => $siteName,
      'site_url' => $siteUrl,
    ];
    $plain = $this->plainBody($event);


    if ($this->emailValidator->isValid($event->email)) {
      $this->send(
        to: $event->email,
        subject: (string) $this->t('We received your requirement'),
        key: 'contact_inquiry_guest',
        view: $view + ['audience' => 'guest'],
        plain: $plain,
        replyTo: $this->emailValidator->isValid($desk) ? $desk : NULL,
        ipHash: $event->ipHash,
        metadata: [
          'name' => $event->name,
          'email' => $event->email,
          'phone' => $event->phone,
          'message' => $event->requirement,
          'audience' => 'guest',
        ],
      );
    }

    if ($this->emailValidator->isValid($desk)) {
      $this->send(
        to: $desk,
        subject: (string) $this->t('New vehicle requirement'),
        key: 'contact_inquiry_desk',
        view: $view + ['audience' => 'desk'],
        plain: $plain,
        replyTo: $this->emailValidator->isValid($event->email) ? $event->email : NULL,
        ipHash: $event->ipHash,
        metadata: [
          'name' => $event->name,
          'email' => $event->email,
          'phone' => $event->phone,
          'message' => $event->requirement,
          'audience' => 'desk',
        ],
      );
    }
    else {
      $this->logger->warning('Skipped desk inquiry mail: invalid recipient.');
    }
  }

  /**
   * @param array<string, mixed> $view
   * @param array<string, string> $metadata
   */
  private function send(string $to, string $subject, string $key, array $view, string $plain, ?string $replyTo, ?string $ipHash, array $metadata): void {
    $result = $this->notifier->send(new Message(
      channel: 'email',
      to: $to,
      subject: $subject,
      bodyText: $plain,
      key: $key,
      replyTo: $replyTo,
      metadata: $metadata,
      ipHash: $ipHash,
      bodyHtml: $this->renderHtml($view),
    ));
    if (!$result->ok) {
      $this->logger->error('Inquiry mail @key to @to failed (@status).', [
        '@key' => $key,
        '@to' => $to,
        '@status' => $result->status,
      ]);
    }
  }

  /**
   * @param array<string, mixed> $view
   */
  private function renderHtml(array $view): string {
    $build = [
      '#theme' => 'lm_contact_mail_inquiry',
      '#audience' => $view['audience'] ?? 'guest',
      '#name' => $view['name'] ?? '',
      '#email' => $view['email'] ?? '',
      '#phone' => $view['phone'] ?? '',
      '#requirement' => $view['requirement'] ?? '',
      '#site_name' => $view['site_name'] ?? '',
      '#site_url' => $view['site_url'] ?? '',
    ];
    $markup = $this->renderer->renderInIsolation($build);
    return trim((string) ($markup instanceof MarkupInterface ? $markup : (string) $markup));
  }

  private function plainBody(InquirySubmittedEvent $event): string {
    return implode("\n", [
      'Luxury Motors — concierge inquiry',
      '',
      'Name: ' . $event->name,
      'Email: ' . $event->email,
      'Phone: ' . $event->phone,
      '',
      'Requirement:',
      $event->requirement,
    ]);
  }

}
