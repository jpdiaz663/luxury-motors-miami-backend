<?php

declare(strict_types=1);

namespace Drupal\lm_booking;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\node\NodeInterface;

/**
 * Pickup and grace-window instants in the site timezone.
 */
final class BookingClock {

  public const GRACE_HOURS = 2;

  public const LOOKBACK_HOURS = 24;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TimeInterface $time,
  ) {}

  public function timezone(): \DateTimeZone {
    $id = (string) ($this->configFactory->get('system.date')->get('timezone.default') ?: 'UTC');
    try {
      return new \DateTimeZone($id);
    }
    catch (\Exception) {
      return new \DateTimeZone('UTC');
    }
  }

  public function now(): \DateTimeImmutable {
    return (new \DateTimeImmutable('@' . $this->time->getRequestTime()))
      ->setTimezone($this->timezone());
  }

  public function pickupFromBooking(NodeInterface $booking): ?\DateTimeImmutable {
    return $this->instantFromBooking($booking, 'field_booking_start', 'field_booking_pickup_time');
  }

  public function graceEnds(\DateTimeImmutable $pickup): \DateTimeImmutable {
    return $pickup->add(new \DateInterval('PT' . self::GRACE_HOURS . 'H'));
  }

  public function inStartWindow(\DateTimeImmutable $pickup): bool {
    $now = $this->now();
    return $now >= $pickup && $now < $this->graceEnds($pickup);
  }

  public function graceExpired(\DateTimeImmutable $pickup): bool {
    return $this->now() >= $this->graceEnds($pickup);
  }

  public function withinLookback(\DateTimeImmutable $pickup): bool {
    $floor = $this->now()->sub(new \DateInterval('PT' . self::LOOKBACK_HOURS . 'H'));
    return $pickup >= $floor;
  }

  public function formatInstant(\DateTimeImmutable $when): string {
    return $when->setTimezone($this->timezone())->format('m/d/Y — g:i A');
  }

  public function lookbackStartDate(): string {
    return $this->now()->sub(new \DateInterval('P2D'))->format('Y-m-d');
  }

  public function todayDate(): string {
    return $this->now()->format('Y-m-d');
  }

  private function instantFromBooking(NodeInterface $booking, string $date_field, string $time_field): ?\DateTimeImmutable {
    if (!$booking->hasField($date_field) || $booking->get($date_field)->isEmpty()) {
      return NULL;
    }
    $date = substr($this->stored($booking, $date_field), 0, 10);
    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      return NULL;
    }
    if (!$booking->hasField($time_field) || $booking->get($time_field)->isEmpty()) {
      return NULL;
    }
    $time = substr($this->stored($booking, $time_field), 0, 5);
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
      return NULL;
    }
    $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $time, $this->timezone());
    if (!$parsed instanceof \DateTimeImmutable || $parsed->format('Y-m-d H:i') !== $date . ' ' . $time) {
      return NULL;
    }
    return $parsed;
  }

  private function stored(NodeInterface $booking, string $field): string {
    $list = $booking->get($field);
    $raw = $list->value ?? NULL;
    if (!is_scalar($raw) || (string) $raw === '') {
      $raw = $list->getString();
    }
    return trim((string) $raw);
  }

}
