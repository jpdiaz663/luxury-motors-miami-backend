<?php

declare(strict_types=1);

namespace Drupal\lm_vehicle;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Shared catalog options and query helpers for the fleet search.
 */
final class FleetCatalog {

  public const PATH = '/fleet';

  /**
   * Views grouped price keys (must match views.view.vehicle_fleet).
   */
  public const PRICE_BANDS = [
    '1' => 'Under 1,500',
    '2' => '1,500–3,000',
    '3' => '3,000+',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * Pickup desks from the rental mockup.
   *
   * @return array<string, string>
   */
  public function pickupLocations(): array {
    return [
      'brickell' => 'Brickell desk',
      'miami-beach' => 'Miami Beach',
      'mia' => 'MIA arrivals',
      'hotel' => 'Hotel / residence',
      'hangar' => 'Private hangar',
    ];
  }

  /**
   * Delivery desks from the rental mockup.
   *
   * @return array<string, string>
   */
  public function deliveryLocations(): array {
    return [
      'same' => 'Same as pickup',
    ] + $this->pickupLocations();
  }

  /**
   * Current GET values used by the banner, refine form, and Views identifiers.
   *
   * @return array<string, string>
   */
  public function currentQuery(): array {
    $query = $this->requestStack->getCurrentRequest()?->query ?? NULL;
    $keys = ['from', 'to', 'place', 'pickup', 'ptime', 'return', 'rtime', 'category', 'brand', 'model', 'color', 'price'];
    $values = [];
    foreach ($keys as $key) {
      $value = $query?->get($key);
      $values[$key] = is_scalar($value) ? trim((string) $value) : '';
    }

    return $values;
  }

  /**
   * Category pills that preserve the booking window and refine filters.
   *
   * @return list<array{label: string, url: string, active: bool}>
   */
  public function categoryPills(): array {
    $query = $this->currentQuery();
    $current = $query['category'];
    $pills = [
      $this->pill('All', $this->fleetUrl(['category' => NULL] + $query), $current === ''),
    ];

    foreach ($this->publishedTerms('vehicle_category') as $tid => $label) {
      $pills[] = $this->pill($label, $this->fleetUrl(['category' => (string) $tid] + $query), $current === (string) $tid);
    }

    return $pills;
  }

  /**
   * @return array<int|string, string>
   */
  public function brandOptions(): array {
    return ['' => 'Any brand'] + $this->publishedTerms('brand');
  }

  /**
   * Distinct editorial colors from published vehicles.
   *
   * @return array<string, string>
   */
  public function colorOptions(): array {
    $options = ['' => 'Any color'];
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'vehicle')
      ->condition('status', 1)
      ->exists('field_color')
      ->execute();

    $colors = [];
    foreach ($storage->loadMultiple($ids) as $node) {
      if (!$node instanceof NodeInterface) {
        continue;
      }
      $color = $node->get('field_color')->value;
      if ($color !== NULL && $color !== '') {
        $colors[(string) $color] = (string) $color;
      }
    }
    natcasesort($colors);

    return $options + $colors;
  }

  /**
   * @return array<string, string>
   */
  public function priceOptions(): array {
    return ['' => 'Any price'] + self::PRICE_BANDS;
  }

  /**
   * Human window for the fleet heading, or NULL if dates are missing.
   */
  public function windowLabel(): ?string {
    $query = $this->currentQuery();
    if ($query['pickup'] === '' || $query['return'] === '') {
      return NULL;
    }
    $start = \DateTimeImmutable::createFromFormat('Y-m-d', $query['pickup']);
    $end = \DateTimeImmutable::createFromFormat('Y-m-d', $query['return']);
    if (!$start || !$end) {
      return NULL;
    }

    return $start->format('M j') . ' – ' . $end->format('M j');
  }

  public function fleetUrl(array $query): string {
    $clean = [];
    foreach ($query as $key => $value) {
      if ($value !== NULL && $value !== '') {
        $clean[$key] = $value;
      }
    }

    return Url::fromUserInput(self::PATH, ['query' => $clean])->toString();
  }

  /**
   * @return array<int|string, string>
   */
  private function publishedTerms(string $vid): array {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('vid', $vid)
      ->condition('status', 1)
      ->sort('name', 'ASC')
      ->execute();

    $options = [];
    foreach ($storage->loadMultiple($ids) as $term) {
      $options[(int) $term->id()] = (string) $term->label();
    }
    return $options;
  }

  /**
   * @return array{label: string, url: string, active: bool}
   */
  private function pill(string $label, string $url, bool $active): array {
    return [
      'label' => $label,
      'url' => $url,
      'active' => $active,
    ];
  }

}
