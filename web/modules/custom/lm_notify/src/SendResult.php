<?php

declare(strict_types=1);

namespace Drupal\lm_notify;

/**
 * Outcome of a notification attempt.
 */
final class SendResult {

  public const SENT = 'sent';
  public const FAILED = 'failed';
  public const BLOCKED = 'blocked';
  public const SKIPPED_UNCONFIGURED = 'skipped_unconfigured';

  public function __construct(
    public readonly string $status,
    public readonly bool $ok,
    public readonly ?string $error = NULL,
    public readonly ?string $providerMessageId = NULL,
    public readonly string $provider = 'hostinger',
  ) {}

  public static function sent(string $provider = 'hostinger', ?string $providerMessageId = NULL): self {
    return new self(self::SENT, TRUE, NULL, $providerMessageId, $provider);
  }

  public static function failed(string $error, string $provider = 'hostinger'): self {
    return new self(self::FAILED, FALSE, $error, NULL, $provider);
  }

  public static function blocked(string $error, string $provider = 'none'): self {
    return new self(self::BLOCKED, FALSE, $error, NULL, $provider);
  }

  public static function skippedUnconfigured(string $error, string $provider = 'hostinger'): self {
    return new self(self::SKIPPED_UNCONFIGURED, FALSE, $error, NULL, $provider);
  }

}
