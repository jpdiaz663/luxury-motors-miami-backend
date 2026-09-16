<?php

declare(strict_types=1);

namespace Drupal\lm_booking;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\lm_vehicle\FleetCatalog;
use Drupal\node\NodeInterface;

/**
 * Server-side committed checkout trip for the current browser session.
 */
final class CheckoutSession {

  use StringTranslationTrait;

  private const COLLECTION = 'lm_booking.checkout';
  private const KEY = 'trip';

  public function __construct(
    private readonly PrivateTempStoreFactory $tempStoreFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FleetCatalog $fleetCatalog,
    private readonly AvailabilityManager $availability,
  ) {}

  /**
   * @param array<string, mixed> $input
   *
   * @return array{ok: bool, message: string}
   */
  public function commit(array $input): array {
    $trip = $this->normalize($input);
    $vehicle = $this->loadVehicle($trip['vehicle']);
    if (!$vehicle instanceof NodeInterface) {
      return $this->fail($this->t('This vehicle is no longer available for the selected dates. Please choose another vehicle.'));
    }

    $from = $this->fleetCatalog->locationTerm($trip['from']);
    if (!$from) {
      return $this->fail($this->t('Add a pickup location before reserving.'));
    }
    $to = $this->fleetCatalog->locationTerm($trip['to'] !== '' ? $trip['to'] : $trip['from']);
    if (!$to) {
      return $this->fail($this->t('Add a pickup location before reserving.'));
    }
    $trip['from'] = (string) $from->id();
    $trip['to'] = (string) $to->id();
    if ($this->fleetCatalog->locationNeedsPlace($trip['from']) || $this->fleetCatalog->locationNeedsPlace($trip['to'])) {
      if ($trip['place'] === '') {
        return $this->fail($this->t('Add a pickup location before reserving.'));
      }
    }
    else {
      $trip['place'] = '';
    }

    $trip['ptime'] = $this->fleetCatalog->hourValue($trip['ptime']);
    $trip['rtime'] = $this->fleetCatalog->hourValue($trip['rtime']);
    $issue = $this->fleetCatalog->tripIssue($trip['pickup'], $trip['ptime'], $trip['return'], $trip['rtime']);
    if ($issue === 'lead') {
      return $this->fail($this->t('Your reservation must be made at least 24 hours before the pickup date.'));
    }
    if ($issue !== NULL) {
      return $this->fail($this->t('Search dates are required before confirming a reservation.'));
    }

    if ($trip['category'] === '' && $vehicle->hasField('field_category') && !$vehicle->get('field_category')->isEmpty()) {
      $term = $vehicle->get('field_category')->entity;
      $trip['category'] = $term ? (string) $term->id() : '';
    }

    if (!$this->availability->isVehicleAvailable((int) $vehicle->id(), $trip['pickup'], $trip['return'])) {
      return $this->fail($this->t('This vehicle is no longer available for the selected dates. Please choose another vehicle.'));
    }

    $this->store()->set(self::KEY, $trip);
    return ['ok' => TRUE, 'message' => ''];
  }

  /**
   * @return array<string, string>|null
   */
  public function get(): ?array {
    $trip = $this->store()->get(self::KEY);
    return is_array($trip) ? $this->normalize($trip) : NULL;
  }

  public function vehicle(): ?NodeInterface {
    $trip = $this->get();
    return $trip ? $this->loadVehicle($trip['vehicle']) : NULL;
  }

  public function clear(): void {
    $this->store()->delete(self::KEY);
  }

  /**
   * @return array<string, string>
   */
  public function fleetQuery(): array {
    $trip = $this->get() ?? [];
    $query = [];
    foreach (['from', 'to', 'place', 'pickup', 'ptime', 'return', 'rtime', 'category'] as $key) {
      if (($trip[$key] ?? '') !== '') {
        $query[$key] = $trip[$key];
      }
    }
    return $query;
  }

  /**
   * @param array<string, mixed> $input
   *
   * @return array<string, string>
   */
  private function normalize(array $input): array {
    $trip = [];
    foreach (['vehicle', 'from', 'to', 'place', 'pickup', 'ptime', 'return', 'rtime', 'category'] as $key) {
      $value = $input[$key] ?? '';
      $trip[$key] = is_scalar($value) ? trim((string) $value) : '';
    }
    if ($trip['to'] === '') {
      $trip['to'] = $trip['from'];
    }
    return $trip;
  }

  private function loadVehicle(string $id): ?NodeInterface {
    if ($id === '' || !ctype_digit($id)) {
      return NULL;
    }
    $vehicle = $this->entityTypeManager->getStorage('node')->load((int) $id);
    if (!$vehicle instanceof NodeInterface || $vehicle->bundle() !== 'vehicle' || !$vehicle->isPublished()) {
      return NULL;
    }
    return $vehicle;
  }

  /**
   * @return array{ok: bool, message: string}
   */
  private function fail(mixed $message): array {
    return ['ok' => FALSE, 'message' => (string) $message];
  }

  private function store(): \Drupal\Core\TempStore\PrivateTempStore {
    return $this->tempStoreFactory->get(self::COLLECTION);
  }

}
