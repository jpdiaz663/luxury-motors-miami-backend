<?php

declare(strict_types=1);

namespace Drupal\lm_booking\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\node\NodeInterface;

/**
 * Fired after the guest grace-period start email is recorded as sent.
 */
final class BookingGraceStartedEvent extends Event {

  public function __construct(
    public readonly NodeInterface $booking,
  ) {}

}
