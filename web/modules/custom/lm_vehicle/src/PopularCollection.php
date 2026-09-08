<?php

declare(strict_types=1);

namespace Drupal\lm_vehicle;

use Drupal\Core\Database\Connection;

/**
 * Ranks published vehicles by reservations in the last 90 days.
 */
final class PopularCollection {

  public const WINDOW_DAYS = 90;

  public const MIN_VEHICLES = 0;

  public function __construct(
    private readonly Connection $database,
  ) {}

  /**
   * Published vehicle nids ordered by reservation count in the last 90 days.
   *
   * Count includes pending and confirmed bookings whose pickup date is on or
   * after today minus 90 days. Cancelled holds are ignored. No fallback cars:
   * the rail only uses vehicles that were actually reserved in the window.
   *
   * @return array<int, int>
   *   Vehicle nid keyed to reservation count, already sorted.
   */
  public function rankedVehicleIds(int $limit): array {
    $limit = max(self::MIN_VEHICLES, min(12, $limit));
    $ranked = $this->reservationCounts($limit);
    if (count($ranked) < self::MIN_VEHICLES) {
      return [];
    }

    return $ranked;
  }

  /**
   * @return array<int, int>
   */
  private function reservationCounts(int $limit): array {
    $schema = $this->database->schema();
    if (
      !$schema->tableExists('node__field_booking_vehicle')
      || !$schema->tableExists('node__field_booking_status')
      || !$schema->tableExists('node__field_booking_start')
    ) {
      return [];
    }

    $since = (new \DateTimeImmutable('today'))
      ->modify('-' . self::WINDOW_DAYS . ' days')
      ->format('Y-m-d');

    $query = $this->database->select('node_field_data', 'booking');
    $query->innerJoin('node__field_booking_vehicle', 'vehicle_ref', 'vehicle_ref.entity_id = booking.nid AND vehicle_ref.deleted = 0');
    $query->innerJoin('node__field_booking_status', 'booking_status', 'booking_status.entity_id = booking.nid AND booking_status.deleted = 0');
    $query->innerJoin('node__field_booking_start', 'booking_start', 'booking_start.entity_id = booking.nid AND booking_start.deleted = 0');
    $query->innerJoin('node_field_data', 'vehicle', 'vehicle.nid = vehicle_ref.field_booking_vehicle_target_id');
    $query->addField('vehicle_ref', 'field_booking_vehicle_target_id', 'nid');
    $query->addExpression('COUNT(booking.nid)', 'reservation_count');
    $query->condition('booking.type', 'booking');
    $query->condition('booking.status', 1);
    $query->condition('booking_status.field_booking_status_value', ['confirmed', 'pending'], 'IN');
    $query->condition('booking_start.field_booking_start_value', $since, '>=');
    $query->condition('vehicle.type', 'vehicle');
    $query->condition('vehicle.status', 1);
    $query->groupBy('vehicle_ref.field_booking_vehicle_target_id');
    $query->orderBy('reservation_count', 'DESC');
    $query->orderBy('nid', 'DESC');
    $query->range(0, $limit);

    $ranked = [];
    foreach ($query->execute() ?? [] as $row) {
      $nid = (int) $row->nid;
      if ($nid > 0) {
        $ranked[$nid] = (int) $row->reservation_count;
      }
    }

    return $ranked;
  }

}
