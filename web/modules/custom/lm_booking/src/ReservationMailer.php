<?php

declare(strict_types=1);

namespace Drupal\lm_booking;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\lm_notify\Message;
use Drupal\lm_notify\NotifierInterface;
use Drupal\lm_notify\SendResult;
use Drupal\lm_vehicle\FleetCatalog;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Guest + desk HTML mail for reservation lifecycle notices.
 */
final class ReservationMailer implements ReservationGuestMailerInterface {

  public function __construct(
    private readonly NotifierInterface $notifier,
    private readonly ReservationPresenter $presenter,
    private readonly ReservationCode $reservationCode,
    private readonly RendererInterface $renderer,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EmailValidatorInterface $emailValidator,
    private readonly LoggerInterface $logger,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * @param array{theme: string, guest_key: string, desk_key: string, guest_subject: string, desk_subject: string, plain_lead: string} $notice
   */
  public function sendPair(NodeInterface $booking, array $notice): void {
    $view = $this->view($booking);
    $code = (string) ($view['reference'] ?? '');
    $guestEmail = trim((string) ($view['guest']['email'] ?? ''));
    $deskEmail = trim((string) ($this->configFactory->get('system.site')->get('mail') ?? ''));
    $plain = $this->plainItinerary($view, $code, $notice['plain_lead']);
    $id = (string) ($booking->id() ?? '');

    if ($this->emailValidator->isValid($guestEmail)) {
      $this->send(
        $guestEmail,
        $notice['guest_subject'],
        $notice['guest_key'],
        $notice['theme'],
        $view + ['audience' => 'guest'],
        $plain,
        $this->emailValidator->isValid($deskEmail) ? $deskEmail : NULL,
        [
          'booking_id' => $id,
          'code' => $code,
          'audience' => 'guest',
        ],
      );
    }
    else {
      $this->logger->warning('Skipped guest mail @key for booking @id: invalid email.', [
        '@key' => $notice['guest_key'],
        '@id' => $id,
      ]);
    }

    if ($this->emailValidator->isValid($deskEmail)) {
      $this->send(
        $deskEmail,
        $notice['desk_subject'],
        $notice['desk_key'],
        $notice['theme'],
        $view + ['audience' => 'desk'],
        $plain,
        $this->emailValidator->isValid($guestEmail) ? $guestEmail : NULL,
        [
          'booking_id' => $id,
          'code' => $code,
          'audience' => 'desk',
          'guest_email' => $guestEmail,
        ],
      );
    }
    else {
      $this->logger->warning('Skipped desk mail @key for booking @id: invalid site mail.', [
        '@key' => $notice['desk_key'],
        '@id' => $id,
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function sendGuest(NodeInterface $booking, array $notice): SendResult {
    $view = $this->view($booking);
    if (!empty($notice['grace_ends_when'])) {
      $view['grace_ends_when'] = $notice['grace_ends_when'];
    }
    $code = (string) ($view['reference'] ?? '');
    $guestEmail = trim((string) ($view['guest']['email'] ?? ''));
    $deskEmail = trim((string) ($this->configFactory->get('system.site')->get('mail') ?? ''));
    $plain = $this->plainItinerary($view, $code, $notice['plain_lead']);
    if (!empty($notice['grace_ends_when'])) {
      $plain .= "\nGrace period ends: " . $notice['grace_ends_when'];
    }
    $id = (string) ($booking->id() ?? '');

    if (!$this->emailValidator->isValid($guestEmail)) {
      $this->logger->warning('Skipped guest mail @key for booking @id: invalid email.', [
        '@key' => $notice['guest_key'],
        '@id' => $id,
      ]);
      return SendResult::failed('invalid_guest_email');
    }

    return $this->send(
      $guestEmail,
      $notice['guest_subject'],
      $notice['guest_key'],
      $notice['theme'],
      $view + ['audience' => 'guest'],
      $plain,
      $this->emailValidator->isValid($deskEmail) ? $deskEmail : NULL,
      [
        'booking_id' => $id,
        'code' => $code,
        'audience' => 'guest',
      ],
    );
  }

  /**
   * @return array<string, mixed>
   */
  private function view(NodeInterface $booking): array {
    $view = $this->presenter->build($booking);
    $code = $this->reservationCode->fromBooking($booking);
    $id = $booking->id();
    $view['confirmation_url'] = ($id && $code !== '' && ($view['status'] ?? '') === 'confirmed')
      ? $this->confirmationUrl((string) $id, $code)
      : '';
    $view['manage_url'] = Url::fromRoute('lm_booking.reservation', [], [
      'absolute' => TRUE,
      'query' => array_filter(['code' => $code]),
    ])->toString();
    $view['fleet_url'] = Url::fromUserInput(FleetCatalog::PATH, ['absolute' => TRUE])->toString();
    $view['site_url'] = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();
    $view['site_name'] = (string) ($this->configFactory->get('system.site')->get('name') ?? 'Luxury Motors');
    $image = $view['trip']['image'] ?? NULL;
    if (is_array($image)) {
      $view['trip']['image']['url'] = $this->absoluteUrl((string) ($image['url'] ?? ''));
    }
    return $view;
  }

  /**
   * @param array<string, mixed> $view
   * @param array<string, string> $metadata
   */
  private function send(string $to, string $subject, string $key, string $theme, array $view, string $plain, ?string $replyTo, array $metadata): SendResult {
    $result = $this->notifier->send(new Message(
      channel: 'email',
      to: $to,
      subject: $subject,
      bodyText: $plain,
      key: $key,
      replyTo: $replyTo,
      metadata: $metadata,
      bodyHtml: $this->renderHtml($theme, $view),
    ));
    if (!$result->ok) {
      $this->logger->error('Booking mail @key to @to failed (@status).', [
        '@key' => $key,
        '@to' => $to,
        '@status' => $result->status,
      ]);
    }
    return $result;
  }

  /**
   * @param array<string, mixed> $view
   */
  private function renderHtml(string $theme, array $view): string {
    $build = [
      '#theme' => $theme,
      '#audience' => $view['audience'] ?? 'guest',
      '#reference' => $view['reference'] ?? '',
      '#guest' => $view['guest'] ?? [],
      '#trip' => $view['trip'] ?? [],
      '#quote' => $view['quote'] ?? [],
      '#confirmation_url' => $view['confirmation_url'] ?? '',
      '#manage_url' => $view['manage_url'] ?? '',
      '#fleet_url' => $view['fleet_url'] ?? '',
      '#site_name' => $view['site_name'] ?? '',
      '#site_url' => $view['site_url'] ?? '',
      '#grace_ends_when' => $view['grace_ends_when'] ?? '',
    ];
    $markup = $this->renderer->renderInIsolation($build);
    return trim((string) ($markup instanceof MarkupInterface ? $markup : (string) $markup));
  }

  private function confirmationUrl(string $bookingId, string $code): string {
    return Url::fromRoute('lm_booking.confirmation', ['booking' => $bookingId], [
      'absolute' => TRUE,
      'query' => [
        ReservationCode::QUERY_KEY => $this->reservationCode->digest($code),
      ],
    ])->toString();
  }

  private function absoluteUrl(string $url): string {
    $url = trim($url);
    if ($url === '' || preg_match('#^https?://#i', $url)) {
      return $url;
    }
    $request = $this->requestStack->getCurrentRequest();
    if ($request === NULL) {
      return $url;
    }
    if (!str_starts_with($url, '/')) {
      $url = '/' . $url;
    }
    return $request->getSchemeAndHttpHost() . $url;
  }

  /**
   * @param array<string, mixed> $view
   */
  private function plainItinerary(array $view, string $code, string $lead): string {
    $guest = $view['guest'] ?? [];
    $trip = $view['trip'] ?? [];
    $quote = $view['quote'] ?? [];
    $lines = [
      $lead . ' ' . $code,
      '',
      'Vehicle: ' . ($trip['title'] ?? ''),
      'Pick up: ' . ($trip['pickup_place'] ?? '') . ' / ' . ($trip['pickup_when'] ?? ''),
      'Drop off: ' . ($trip['dropoff_place'] ?? '') . ' / ' . ($trip['dropoff_when'] ?? ''),
      'Name: ' . ($guest['name'] ?? ''),
      'Email: ' . ($guest['email'] ?? ''),
      'Phone: ' . ($guest['phone'] ?? ''),
      'Total: ' . ($quote['total_formatted'] ?? ''),
    ];
    if (!empty($view['fleet_url'])) {
      $lines[] = 'Fleet: ' . $view['fleet_url'];
    }
    return implode("\n", $lines);
  }

}
