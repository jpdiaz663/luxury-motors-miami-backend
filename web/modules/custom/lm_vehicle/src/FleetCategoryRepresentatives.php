<?php

declare(strict_types=1);

namespace Drupal\lm_vehicle;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Picks one published vehicle nid per category for the fleet catalog.
 */
final class FleetCategoryRepresentatives {

  /**
   * Vehicle statuses included in views.view.vehicle_fleet.
   */
  private const FLEET_STATUSES = ['available', 'reserved', 'rented'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FleetCatalog $catalog,
    private readonly mixed $availability = NULL,
  ) {}

  /**
   * Vehicle nids to keep: one per category, plus every uncategorized vehicle.
   *
   * The representative is the lowest daily price, then title, then nid, among
   * vehicles that still match the current fleet filters and rental window.
   *
   * @return list<int>
   */
  public function nids(): array {
    $query = $this->catalog->currentQuery();
    $storage = $this->entityTypeManager->getStorage('node');
    $entityQuery = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'vehicle')
      ->condition('status', 1)
      ->condition('field_vehicle_status', self::FLEET_STATUSES, 'IN');

    $blocked = $this->blockedIds($query['pickup'], $query['return']);
    if ($blocked !== []) {
      $entityQuery->condition('nid', $blocked, 'NOT IN');
    }
    if ($query['category'] !== '' && ctype_digit($query['category'])) {
      $entityQuery->condition('field_category', (int) $query['category']);
    }
    if ($query['brand'] !== '' && ctype_digit($query['brand'])) {
      $entityQuery->condition('field_brand', (int) $query['brand']);
    }
    if ($query['model'] !== '') {
      $entityQuery->condition('field_model', $query['model'], 'CONTAINS');
    }
    if ($query['color'] !== '') {
      $entityQuery->condition('field_color', $query['color']);
    }
    $this->applyPriceBand($entityQuery, $query['price']);

    $ids = $entityQuery->execute();
    if ($ids === []) {
      return [];
    }

    return $this->pickRepresentatives($storage->loadMultiple($ids));
  }

  /**
   * @param array<int|string, \Drupal\Core\Entity\EntityInterface> $entities
   *
   * @return list<int>
   */
  private function pickRepresentatives(array $entities): array {
    $uncategorized = [];
    $byCategory = [];
    foreach ($entities as $entity) {
      if (!$entity instanceof NodeInterface) {
        continue;
      }
      $tid = $entity->hasField('field_category')
        ? (int) $entity->get('field_category')->target_id
        : 0;
      if ($tid === 0) {
        $uncategorized[] = (int) $entity->id();
        continue;
      }
      $byCategory[$tid][] = $entity;
    }

    $nids = $uncategorized;
    foreach ($byCategory as $group) {
      usort($group, $this->compareVehicles(...));
      $nids[] = (int) $group[0]->id();
    }

    return $nids;
  }

  private function compareVehicles(NodeInterface $a, NodeInterface $b): int {
    $price = $this->dailyPrice($a) <=> $this->dailyPrice($b);
    if ($price !== 0) {
      return $price;
    }
    $title = strnatcasecmp((string) $a->label(), (string) $b->label());
    if ($title !== 0) {
      return $title;
    }

    return (int) $a->id() <=> (int) $b->id();
  }

  private function dailyPrice(NodeInterface $node): float {
    if (!$node->hasField('field_daily_price') || $node->get('field_daily_price')->isEmpty()) {
      return PHP_FLOAT_MAX;
    }

    return (float) $node->get('field_daily_price')->value;
  }

  /**
   * @param \Drupal\Core\Entity\Query\QueryInterface $entityQuery
   */
  private function applyPriceBand(object $entityQuery, string $band): void {
    match ($band) {
      '1' => $entityQuery->condition('field_daily_price', 1500, '<'),
      '2' => $entityQuery->condition('field_daily_price', 1500, '>=')
        ->condition('field_daily_price', 3000, '<='),
      '3' => $entityQuery->condition('field_daily_price', 3000, '>='),
      default => NULL,
    };
  }

  /**
   * @return list<int>
   */
  private function blockedIds(string $pickup, string $return): array {
    if (!is_object($this->availability) || !method_exists($this->availability, 'unavailableVehicleIds')) {
      return [];
    }

    return $this->availability->unavailableVehicleIds($pickup, $return);
  }

}