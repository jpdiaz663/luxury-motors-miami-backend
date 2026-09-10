<?php

declare(strict_types=1);

namespace Drupal\lm_vehicle;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
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
   * Published location term, or NULL when the query value is not a location.
   */
  public function locationTerm(string $id): ?TermInterface {
    if ($id === '' || !ctype_digit($id)) {
      return NULL;
    }
    $term = $this->entityTypeManager->getStorage('taxonomy_term')->load((int) $id);
    if (!$term instanceof TermInterface || $term->bundle() !== 'location' || !$term->isPublished()) {
      return NULL;
    }

    return $term;
  }

  /**
   * Whether the location asks for a hotel / residence property name.
   */
  public function locationNeedsPlace(string $id): bool {
    $term = $this->locationTerm($id);
    if (!$term || !$term->hasField('field_requires_place')) {
      return FALSE;
    }

    return (bool) $term->get('field_requires_place')->value;
  }

  /**
   * Location term IDs that should reveal the property-name field.
   *
   * @return list<int>
   */
  public function locationPlaceTids(): array {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    try {
      $ids = $storage->getQuery()
        ->accessCheck(TRUE)
        ->condition('vid', 'location')
        ->condition('status', 1)
        ->condition('field_requires_place', 1)
        ->execute();
    }
    catch (\Exception) {
      return [];
    }

    return array_map('intval', array_values($ids));
  }

  /**
   * Hourly pickup/return times in 12-hour labels, 24-hour values.
   *
   * @return array<string, string>
   */
  public function hourOptions(): array {
    $options = [];
    for ($hour = 0; $hour < 24; $hour++) {
      $value = sprintf('%02d:00', $hour);
      $hour12 = $hour % 12 === 0 ? 12 : $hour % 12;
      $suffix = $hour < 12 ? 'AM' : 'PM';
      $options[$value] = sprintf('%d:00 %s', $hour12, $suffix);
    }

    return $options;
  }

  /**
   * Normalize a GET time to an hour key, or the default.
   */
  public function hourValue(string $time, string $default = '10:00'): string {
    $options = $this->hourOptions();
    if (isset($options[$time])) {
      return $time;
    }
    $parsed = \DateTimeImmutable::createFromFormat('H:i', $time);
    if ($parsed) {
      $rounded = $parsed->format('H') . ':00';
      if (isset($options[$rounded])) {
        return $rounded;
      }
    }

    return $default;
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
   * Billable rental days from the current search window.
   *
   * Missing or invalid dates count as one day so the fleet card still
   * has a total.
   */
  public function rentalDays(): int {
    $query = $this->currentQuery();
    if ($query['pickup'] === '' || $query['return'] === '') {
      return 1;
    }
    $start = \DateTimeImmutable::createFromFormat('Y-m-d', $query['pickup']);
    $end = \DateTimeImmutable::createFromFormat('Y-m-d', $query['return']);
    if (!$start || !$end) {
      return 1;
    }
    $days = (int) $start->diff($end)->format('%r%a');
    return max(1, $days);
  }

  /**
   * Search query values that should follow a vehicle reserve link.
   *
   * @return array<string, string>
   */
  public function bookingQuery(): array {
    $clean = [];
    foreach ($this->currentQuery() as $key => $value) {
      if ($value !== '') {
        $clean[$key] = $value;
      }
    }

    return $clean;
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

  public function hasFilters(): bool {
    $query = $this->currentQuery();
    return !empty($query['from']) || !empty($query['to']) || !empty($query['place']) || !empty($query['pickup']) || !empty($query['ptime']) || !empty($query['return']) || !empty($query['rtime']) || !empty($query['category']) || !empty($query['brand']) || !empty($query['model']) || !empty($query['color']) || !empty($query['price']);
  }

}
