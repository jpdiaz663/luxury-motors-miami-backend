<?php

declare(strict_types=1);

namespace Drupal\lm_booking;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\PrivateKey;
use Drupal\node\NodeInterface;

/**
 * Public booking references: LM- plus six CSPRNG alphanumeric characters.
 *
 * The HMAC digest is not part of the displayed code. It authenticates the
 * confirmation URL so a numeric node id cannot be enumerated.
 */
final class ReservationCode {

  public const PREFIX = 'LM-';
  public const BODY_LENGTH = 6;
  public const QUERY_KEY = 'h';

  private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  private const DIGEST_LENGTH = 8;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly PrivateKey $privateKey,
  ) {}

  public function mint(): string {
    for ($attempt = 0; $attempt < 16; $attempt++) {
      $code = self::PREFIX . $this->randomBody();
      if (!$this->exists($code)) {
        return $code;
      }
    }

    throw new \RuntimeException('Could not mint a unique reservation code.');
  }

  public function digest(string $code): string {
    return substr(hash_hmac('sha256', strtoupper($code), $this->privateKey->get()), 0, self::DIGEST_LENGTH);
  }

  public function matches(string $code, string $digest): bool {
    $digest = strtolower(preg_replace('/[^0-9a-f]/', '', $digest) ?? '');
    if (strlen($digest) !== self::DIGEST_LENGTH) {
      return FALSE;
    }

    return hash_equals($this->digest($code), $digest);
  }

  public function normalize(string $code): string {
    $code = strtoupper(trim($code));
    $code = preg_replace('/[^A-Z0-9\-]/', '', $code) ?? '';
    if ($code !== '' && !str_starts_with($code, self::PREFIX) && strlen($code) === self::BODY_LENGTH) {
      $code = self::PREFIX . $code;
    }
    $pattern = '/^' . preg_quote(self::PREFIX, '/') . '[A-Z0-9]{' . self::BODY_LENGTH . '}$/';
    if (!preg_match($pattern, $code)) {
      return '';
    }

    return $code;
  }

  public function fromBooking(NodeInterface $booking): string {
    if ($booking->hasField('field_booking_code') && !$booking->get('field_booking_code')->isEmpty()) {
      return $this->normalize((string) $booking->get('field_booking_code')->value);
    }
    return $this->normalize((string) $booking->getTitle());
  }

  public function exists(string $code): bool {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'booking')
      ->range(0, 1);
    $group = $query->orConditionGroup()
      ->condition('title', $code);
    if (isset($this->entityFieldManager->getFieldDefinitions('node', 'booking')['field_booking_code'])) {
      $group->condition('field_booking_code', $code);
    }
    $ids = $query->condition($group)->execute();

    return $ids !== [];
  }

  private function randomBody(): string {
    $max = strlen(self::ALPHABET) - 1;
    $body = '';
    for ($i = 0; $i < self::BODY_LENGTH; $i++) {
      $body .= self::ALPHABET[random_int(0, $max)];
    }

    return $body;
  }

}
