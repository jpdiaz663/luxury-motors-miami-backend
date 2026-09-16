<?php

declare(strict_types=1);

namespace Drupal\lm_booking;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\lm_booking\Event\BookingGraceExpiredEvent;
use Drupal\lm_booking\Event\BookingGraceStartedEvent;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Drupal cron: 2-hour pickup grace notice, then auto-cancel if still confirmed.
 */
final class GracePeriodProcessor {

  use StringTranslationTrait;

  private bool $autoExpiring = FALSE;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly BookingClock $clock,
    private readonly ReservationGuestMailerInterface $mailer,
    private readonly EventDispatcherInterface $eventDispatcher,
    private readonly LoggerInterface $logger,
  ) {}

  public function isAutoExpiring(): bool {
    return $this->autoExpiring;
  }

  public function process(): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'booking')
      ->condition('status', 1)
      ->condition('field_booking_status', ['confirmed', 'cancelled'], 'IN')
      ->condition('field_booking_start', $this->clock->lookbackStartDate(), '>=')
      ->condition('field_booking_start', $this->clock->todayDate(), '<=')
      ->execute();

    foreach ($storage->loadMultiple($ids) as $booking) {
      if (!$booking instanceof NodeInterface) {
        continue;
      }
      try {
        $this->processBooking($booking);
      }
      catch (\Throwable $e) {
        $this->logger->error('Grace period processing failed for booking @id: @error', [
          '@id' => (string) $booking->id(),
          '@error' => mb_substr($e->getMessage(), 0, 500),
        ]);
      }
    }
  }

  public function processBooking(NodeInterface $booking): void {
    $pickup = $this->clock->pickupFromBooking($booking);
    if ($pickup === NULL) {
      return;
    }
    $status = $this->status($booking);
    if ($status === 'confirmed') {
      if ($this->clock->inStartWindow($pickup) && $this->isEmpty($booking, 'field_booking_grace_notified')) {
        $this->notifyStart($booking, $pickup);
      }
      elseif ($this->clock->graceExpired($pickup) && $this->clock->withinLookback($pickup)) {
        $this->expire($booking, $pickup);
      }
    }
    elseif ($status === 'cancelled') {
      $this->retryCancelMail($booking, $pickup);
    }
  }

  private function notifyStart(NodeInterface $booking, \DateTimeImmutable $pickup): void {
    $code = $this->code($booking);
    $result = $this->mailer->sendGuest($booking, [
      'theme' => 'lm_booking_mail_grace_started',
      'guest_key' => 'booking_grace_started_guest',
      'guest_subject' => (string) $this->t('Grace period started @code', ['@code' => $code]),
      'plain_lead' => 'Luxury Motors — your 2-hour pickup grace period has started.',
      'grace_ends_when' => $this->clock->formatInstant($this->clock->graceEnds($pickup)),
    ]);
    if (!$result->ok) {
      $this->logger->error('Grace start mail failed for booking @id (@status).', [
        '@id' => (string) $booking->id(),
        '@status' => $result->status,
      ]);
      return;
    }
    $booking->set('field_booking_grace_notified', $this->clock->now()->getTimestamp());
    $booking->save();
    $this->eventDispatcher->dispatch(new BookingGraceStartedEvent($booking));
    $this->logger->notice('Grace period started for reservation @code.', [
      '@code' => $code,
    ]);
  }

  private function expire(NodeInterface $booking, \DateTimeImmutable $pickup): void {
    $this->autoExpiring = TRUE;
    try {
      $booking->set('field_booking_status', 'cancelled');
      $booking->save();
    }
    finally {
      $this->autoExpiring = FALSE;
    }
    $this->eventDispatcher->dispatch(new BookingGraceExpiredEvent($booking));
    $this->logger->notice('Reservation @code auto-cancelled after grace period.', [
      '@code' => $this->code($booking),
    ]);
    $this->sendCancelMail($booking, $pickup);
  }

  private function retryCancelMail(NodeInterface $booking, \DateTimeImmutable $pickup): void {
    if (!$this->clock->graceExpired($pickup) || !$this->isEmpty($booking, 'field_booking_grace_cancel_sent')) {
      return;
    }
    if (!$this->clock->withinLookback($pickup) && $this->isEmpty($booking, 'field_booking_grace_notified')) {
      return;
    }
    $this->sendCancelMail($booking, $pickup);
  }

  private function sendCancelMail(NodeInterface $booking, \DateTimeImmutable $pickup): void {
    if (!$this->isEmpty($booking, 'field_booking_grace_cancel_sent')) {
      return;
    }
    $code = $this->code($booking);
    $result = $this->mailer->sendGuest($booking, [
      'theme' => 'lm_booking_mail_grace_cancelled',
      'guest_key' => 'booking_grace_cancelled_guest',
      'guest_subject' => (string) $this->t('Reservation cancelled after grace period @code', ['@code' => $code]),
      'plain_lead' => 'Luxury Motors — reservation cancelled because the 2-hour pickup grace period expired.',
      'grace_ends_when' => $this->clock->formatInstant($this->clock->graceEnds($pickup)),
    ]);
    if (!$result->ok) {
      $this->logger->error('Grace cancel mail failed for booking @id (@status). Reservation remains cancelled.', [
        '@id' => (string) $booking->id(),
        '@status' => $result->status,
      ]);
      return;
    }
    $booking->set('field_booking_grace_cancel_sent', $this->clock->now()->getTimestamp());
    $booking->save();
  }

  private function status(NodeInterface $booking): string {
    if (!$booking->hasField('field_booking_status') || $booking->get('field_booking_status')->isEmpty()) {
      return '';
    }
    return $this->fieldString($booking, 'field_booking_status');
  }

  private function code(NodeInterface $booking): string {
    if ($booking->hasField('field_booking_code') && !$booking->get('field_booking_code')->isEmpty()) {
      $code = $this->fieldString($booking, 'field_booking_code');
      if ($code !== '') {
        return $code;
      }
    }
    return (string) ($booking->id() ?? '');
  }

  private function isEmpty(NodeInterface $booking, string $field): bool {
    return !$booking->hasField($field) || $booking->get($field)->isEmpty();
  }

  private function fieldString(NodeInterface $booking, string $field): string {
    $list = $booking->get($field);
    $raw = $list->value ?? NULL;
    if (!is_scalar($raw) || (string) $raw === '') {
      $raw = $list->getString();
    }
    return trim((string) $raw);
  }

}
