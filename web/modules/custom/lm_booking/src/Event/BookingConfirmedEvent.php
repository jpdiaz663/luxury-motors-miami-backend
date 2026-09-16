<?php

declare(strict_types=1);

namespace Drupal\lm_booking\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\node\NodeInterface;

/**
 * Fired after a booking node is saved as confirmed from checkout.
 */
final class BookingConfirmedEvent extends Event {

  public function __construct(
    public readonly NodeInterface $booking,
  ) {}

}
