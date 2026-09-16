<?php

declare(strict_types=1);

namespace Drupal\lm_notify\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\lm_notify\Message;
use Drupal\lm_notify\SendResult;

/**
 * Fired after a blocked, skipped, or failed send.
 */
final class MessageFailedEvent extends Event {

  public function __construct(
    public readonly Message $message,
    public readonly SendResult $result,
  ) {}

}
