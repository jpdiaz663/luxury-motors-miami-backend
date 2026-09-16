<?php

declare(strict_types=1);

namespace Drupal\lm_booking\EventSubscriber;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\lm_booking\Event\BookingCancelledEvent;
use Drupal\lm_booking\Event\BookingConfirmedEvent;
use Drupal\lm_booking\ReservationCode;
use Drupal\lm_booking\ReservationMailer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends guest and desk mail for reservation confirm and cancel.
 */
final class BookingMailSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly ReservationMailer $mailer,
    private readonly ReservationCode $reservationCode,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      BookingConfirmedEvent::class => 'onConfirmed',
      BookingCancelledEvent::class => 'onCancelled',
    ];
  }

  public function onConfirmed(BookingConfirmedEvent $event): void {
    $code = $this->reservationCode->fromBooking($event->booking);
    $this->mailer->sendPair($event->booking, [
      'theme' => 'lm_booking_mail_confirmed',
      'guest_key' => 'booking_confirmed_guest',
      'desk_key' => 'booking_confirmed_desk',
      'guest_subject' => (string) $this->t('Reservation confirmed @code', ['@code' => $code]),
      'desk_subject' => (string) $this->t('New reservation @code', ['@code' => $code]),
      'plain_lead' => 'Luxury Motors — reservation confirmed',
    ]);
  }

  public function onCancelled(BookingCancelledEvent $event): void {
    $code = $this->reservationCode->fromBooking($event->booking);
    $this->mailer->sendPair($event->booking, [
      'theme' => 'lm_booking_mail_cancelled',
      'guest_key' => 'booking_cancelled_guest',
      'desk_key' => 'booking_cancelled_desk',
      'guest_subject' => (string) $this->t('Reservation cancelled @code', ['@code' => $code]),
      'desk_subject' => (string) $this->t('Vehicle released — @code cancelled', ['@code' => $code]),
      'plain_lead' => 'Luxury Motors — reservation cancelled. Vehicle window is open.',
    ]);
  }

}
