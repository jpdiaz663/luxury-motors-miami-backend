<?php

declare(strict_types=1);

namespace Drupal\lm_booking;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Calendar availability from active booking nodes.
 */
final class AvailabilityManager {

  public const ACTIVE_STATUSES = ['confirmed', 'pending', 'in_progress'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Vehicle nids that overlap the requested inclusive date window.
   *
   * Overlap: booking_start < return AND booking_end > pickup.
   * Same-day handoff is allowed (one booking can end the day another starts).
   *
   * @return list<int>
   */
  public function unavailableVehicleIds(string $pickup, string $return): array {
    if (!$this->validDate($pickup) || !$this->validDate($return) || $return < $pickup) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'booking')
      ->condition('status', 1)
      ->condition('field_booking_status', self::ACTIVE_STATUSES, 'IN')
      ->condition('field_booking_start', $return, '<')
      ->condition('field_booking_end', $pickup, '>')
      ->execute();

    $blocked = [];
    foreach ($storage->loadMultiple($ids) as $booking) {
      if (!$booking instanceof NodeInterface || !$booking->hasField('field_booking_vehicle')) {
        continue;
      }
      $nid = (int) $booking->get('field_booking_vehicle')->target_id;
      if ($nid > 0) {
        $blocked[$nid] = $nid;
      }
    }

    return array_values($blocked);
  }

  /**
   * Whether this published vehicle can be held for the inclusive window.
   *
   * Invalid dates are treated as unavailable. Published status is not
   * inventory; callers must still check the vehicle entity separately.
   */
  public function isVehicleAvailable(int $vehicle_id, string $pickup, string $return): bool {
    if ($vehicle_id < 1) {
      return FALSE;
    }
    if (!$this->validDate($pickup) || !$this->validDate($return) || $return < $pickup) {
      return FALSE;
    }

    return !in_array($vehicle_id, $this->unavailableVehicleIds($pickup, $return), TRUE);
  }

  private function validDate(string $value): bool {
    return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
  }

}
