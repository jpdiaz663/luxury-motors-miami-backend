<?php

declare(strict_types=1);

namespace Drupal\lm_booking;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\lm_booking\Quote\QuoteCalculator;
use Drupal\lm_vehicle\FleetCatalog;
use Drupal\lm_vehicle\VehiclePresenter;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Customer-facing reservation details from a stored booking node.
 */
final class ReservationPresenter {

  use StringTranslationTrait;

  public function __construct(
    private readonly VehiclePresenter $presenter,
    private readonly QuoteCalculator $quoteCalculator,
    private readonly ReservationCode $reservationCode,
    private readonly FleetCatalog $fleetCatalog,
  ) {}

  /**
   * @return array<string, mixed>
   */
  public function build(NodeInterface $booking): array {
    $vehicle = $this->referencedVehicle($booking);
    $pickup = $this->bookingDate($booking, 'field_booking_start');
    $return = $this->bookingDate($booking, 'field_booking_end');
    $ptime = $this->storedTime($booking, 'field_booking_pickup_time');
    $rtime = $this->storedTime($booking, 'field_booking_dropoff_time');
    $pickup_place = $this->locationLabel($booking, 'field_booking_pickup_location');
    $dropoff_place = $this->locationLabel($booking, 'field_booking_dropoff_location') ?: $pickup_place;
    $place = $this->fieldValue($booking, 'field_booking_place');
    if ($place !== '' && $pickup_place !== '') {
      $pickup_place .= ' — ' . $place;
    }
    elseif ($pickup_place === '' && $place !== '') {
      $pickup_place = $place;
    }

    $daily = 0.0;
    $card = [];
    if ($vehicle instanceof NodeInterface) {
      $card = $this->presenter->card($vehicle);
      if ($vehicle->hasField('field_daily_price') && !$vehicle->get('field_daily_price')->isEmpty()) {
        $daily = (float) $vehicle->get('field_daily_price')->value;
      }
    }
    $days = ($pickup !== '' && $return !== '') ? $this->quoteCalculator->days($pickup, $return) : 1;
    $quote = $this->quoteCalculator->quote($daily, $days, $pickup_place, $dropoff_place)->toArray();
    $hours = $this->fleetCatalog->hourOptions();

    $status = '';
    if ($booking->hasField('field_booking_status') && !$booking->get('field_booking_status')->isEmpty()) {
      $status = (string) $booking->get('field_booking_status')->value;
    }

    return [
      'reference' => $this->reservationCode->fromBooking($booking) ?: (string) $booking->id(),
      'status' => $status,
      'status_label' => $this->statusLabel($status),
      'cancellable' => in_array($status, ['confirmed', 'pending'], TRUE),
      'guest' => [
        'name' => $this->fieldValue($booking, 'field_customer_name'),
        'email' => $this->fieldValue($booking, 'field_customer_email'),
        'phone' => $this->fieldValue($booking, 'field_customer_phone'),
        'note' => $this->fieldValue($booking, 'field_customer_note'),
      ],
      'trip' => [
        'title' => $card['display_title'] ?? ($vehicle ? $vehicle->label() : $booking->label()),
        'image' => $card['image'] ?? NULL,
        'pickup_place' => $pickup_place,
        'dropoff_place' => $dropoff_place,
        'pickup_when' => $this->whenLabel($pickup, $ptime, $hours),
        'dropoff_when' => $this->whenLabel($return, $rtime, $hours),
        'place' => $place,
      ],
      'quote' => $quote,
      'fleet_url' => Url::fromUserInput(FleetCatalog::PATH)->toString(),
      'manage_url' => Url::fromRoute('lm_booking.reservation', [], [
        'query' => array_filter(['code' => $this->reservationCode->fromBooking($booking)]),
      ])->toString(),
    ];
  }

  private function referencedVehicle(NodeInterface $booking): ?NodeInterface {
    if (!$booking->hasField('field_booking_vehicle') || $booking->get('field_booking_vehicle')->isEmpty()) {
      return NULL;
    }
    $vehicle = $booking->get('field_booking_vehicle')->entity;
    return $vehicle instanceof NodeInterface ? $vehicle : NULL;
  }

  private function locationLabel(NodeInterface $booking, string $field): string {
    if (!$booking->hasField($field) || $booking->get($field)->isEmpty()) {
      return '';
    }
    $term = $booking->get($field)->entity;
    return $term instanceof TermInterface ? (string) $term->label() : '';
  }

  private function storedTime(NodeInterface $booking, string $field): string {
    $raw = $this->fieldValue($booking, $field);
    return $raw !== '' ? $this->fleetCatalog->hourValue($raw) : '';
  }

  private function bookingDate(NodeInterface $booking, string $field): string {
    if (!$booking->hasField($field) || $booking->get($field)->isEmpty()) {
      return '';
    }
    return substr((string) $booking->get($field)->value, 0, 10);
  }

  private function fieldValue(NodeInterface $booking, string $field): string {
    if (!$booking->hasField($field) || $booking->get($field)->isEmpty()) {
      return '';
    }
    return trim((string) $booking->get($field)->value);
  }

  /**
   * @param array<string, string> $hours
   */
  private function whenLabel(string $date, string $time, array $hours): string {
    if ($date === '') {
      return '';
    }
    $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
    $day = $parsed ? $parsed->format('m/d/Y') : $date;
    if ($time === '') {
      return $day;
    }
    return $day . ' — ' . ($hours[$time] ?? $time);
  }

  private function statusLabel(string $status): string {
    return match ($status) {
      'confirmed' => (string) $this->t('Confirmed'),
      'pending' => (string) $this->t('Pending'),
      'cancelled' => (string) $this->t('Cancelled'),
      default => $status,
    };
  }

}
