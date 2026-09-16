<?php

declare(strict_types=1);

namespace Drupal\lm_notify\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\lm_notify\Message;

/**
 * Fired before a message is handed to a channel adapter.
 */
final class MessageSendingEvent extends Event {

  private bool $cancelled = FALSE;

  private string $reason = '';

  public function __construct(
    public readonly Message $message,
  ) {}

  public function cancel(string $reason): void {
    $this->cancelled = TRUE;
    $this->reason = $reason;
    $this->stopPropagation();
  }

  public function isCancelled(): bool {
    return $this->cancelled;
  }

  public function reason(): string {
    return $this->reason;
  }

}
