<?php

declare(strict_types=1);

namespace Drupal\lm_booking\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\node\NodeInterface;

/**
 * Fired after a booking is marked cancelled.
 */
final class BookingCancelledEvent extends Event {

  public function __construct(
    public readonly NodeInterface $booking,
  ) {}

}
