<?php

declare(strict_types=1);

namespace Drupal\lm_booking\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\node\NodeInterface;

/**
 * Fired after cron auto-cancels a booking for grace-period expiry.
 */
final class BookingGraceExpiredEvent extends Event {

  public function __construct(
    public readonly NodeInterface $booking,
  ) {}

}
