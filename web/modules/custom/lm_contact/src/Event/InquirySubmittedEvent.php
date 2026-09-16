<?php

declare(strict_types=1);

namespace Drupal\lm_contact\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Fired after a concierge inquiry passes honeypot and flood checks.
 */
final class InquirySubmittedEvent extends Event {

  public function __construct(
    public readonly string $name,
    public readonly string $email,
    public readonly string $phone,
    public readonly string $requirement,
    public readonly string $deskRecipient,
    public readonly ?string $ipHash = NULL,
  ) {}

}
