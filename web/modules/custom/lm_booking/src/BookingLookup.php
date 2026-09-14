<?php

declare(strict_types=1);

namespace Drupal\lm_booking;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Loads a booking when the public code and customer email both match.
 */
final class BookingLookup {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly ReservationCode $reservationCode,
  ) {}

  public function find(string $code, string $email): ?NodeInterface {
    $code = $this->reservationCode->normalize($code);
    $email = strtolower(trim($email));
    if ($code === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return NULL;
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'booking')
      ->range(0, 1);
    $group = $query->orConditionGroup()
      ->condition('title', $code);
    $definitions = $this->entityFieldManager->getFieldDefinitions('node', 'booking');
    if (isset($definitions['field_booking_code'])) {
      $group->condition('field_booking_code', $code);
    }
    $ids = $query->condition($group)->execute();
    if ($ids === []) {
      return NULL;
    }

    $booking = $storage->load(reset($ids));
    if (!$booking instanceof NodeInterface) {
      return NULL;
    }

    $stored = '';
    if ($booking->hasField('field_customer_email') && !$booking->get('field_customer_email')->isEmpty()) {
      $stored = strtolower(trim((string) $booking->get('field_customer_email')->value));
    }
    if ($stored === '' || !hash_equals($stored, $email)) {
      return NULL;
    }

    return $booking;
  }

  public function load(int $id): ?NodeInterface {
    if ($id < 1) {
      return NULL;
    }
    $booking = $this->entityTypeManager->getStorage('node')->load($id);
    return $booking instanceof NodeInterface && $booking->bundle() === 'booking' ? $booking : NULL;
  }

}
