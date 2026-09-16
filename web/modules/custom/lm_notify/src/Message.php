<?php

declare(strict_types=1);

namespace Drupal\lm_notify;

/**
 * Immutable notification payload for any channel.
 */
final class Message {

  /**
   * @param array<string, string> $metadata
   */
  public function __construct(
    public readonly string $channel,
    public readonly string $to,
    public readonly string $subject,
    public readonly string $bodyText,
    public readonly string $key,
    public readonly ?string $replyTo = NULL,
    public readonly array $metadata = [],
    public readonly ?string $ipHash = NULL,
    public readonly ?string $bodyHtml = NULL,
  ) {}

  public function withTo(string $to): self {
    return new self(
      $this->channel,
      $to,
      $this->subject,
      $this->bodyText,
      $this->key,
      $this->replyTo,
      $this->metadata,
      $this->ipHash,
      $this->bodyHtml,
    );
  }

}
