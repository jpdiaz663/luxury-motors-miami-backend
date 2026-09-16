<?php

declare(strict_types=1);

namespace Drupal\lm_vehicle;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
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

  public const LEAD_HOURS = 24;

  public const MIN_RENTAL_HOURS = 0;

  public const DEFAULT_SPAN_DAYS = 3;

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
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TimeInterface $time,
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
    if ($values['pickup'] === '') {
      $alias = $query?->get('date_from');
      $values['pickup'] = is_scalar($alias) ? trim((string) $alias) : '';
    }
    if ($values['return'] === '') {
      $alias = $query?->get('date_to');
      $values['return'] = is_scalar($alias) ? trim((string) $alias) : '';
    }
    $values['from'] = ctype_digit($values['from']) ? $values['from'] : '';
    $values['to'] = ctype_digit($values['to']) ? $values['to'] : '';
    if ($values['to'] === '') {
      $values['to'] = $values['from'];
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
   * Dates omitted from the URL use the same banner defaults as the form.
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
    $window = $this->resolvedWindow();
    $clean['pickup'] = $window['pickup'];
    $clean['return'] = $window['return'];
    $defaults = $this->defaultWindow();
    $clean['ptime'] = ($clean['ptime'] ?? '') !== ''
      ? $this->hourValue($clean['ptime'])
      : $defaults['ptime'];
    $clean['rtime'] = ($clean['rtime'] ?? '') !== ''
      ? $this->hourValue($clean['rtime'])
      : $defaults['rtime'];

    return $clean;
  }

  /**
   * Banner default pickup/return when the query omits dates.
   *
   * @return array{pickup: string, return: string, ptime: string, rtime: string}
   */
  public function defaultWindow(): array {
    $pickup = $this->earliestPickupInstant();
    $return = $pickup->modify('+' . self::DEFAULT_SPAN_DAYS . ' days');
    $ptime = $pickup->format('H') . ':00';
    $rtime = $return->format('H') . ':00';

    return [
      'pickup' => $pickup->format('Y-m-d'),
      'return' => $return->format('Y-m-d'),
      'ptime' => $ptime,
      'rtime' => $rtime,
    ];
  }

  /**
   * Query dates, or the banner defaults when those keys are empty.
   *
   * @return array{pickup: string, return: string}
   */
  public function resolvedWindow(): array {
    $query = $this->currentQuery();
    $defaults = $this->defaultWindow();
    $pickup = $query['pickup'] !== '' ? $query['pickup'] : $defaults['pickup'];
    $return = $query['return'] !== '' ? $query['return'] : $defaults['return'];

    return [
      'pickup' => $pickup,
      'return' => $return,
    ];
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

  /**
   * Whether the request includes a pickup and return date.
   */
  public function hasRentalWindow(): bool {
    $query = $this->currentQuery();
    if ($query['pickup'] === '' || $query['return'] === '') {
      return FALSE;
    }

    return $this->validTrip(
      $query['pickup'],
      $this->hourValue($query['ptime']),
      $query['return'],
      $this->hourValue($query['rtime']),
    );
  }

  /**
   * Pickup location, committed dates in the URL, and hotel name when required.
   *
   * Dates must be present in the request. Banner defaults are not enough.
   */
  public function tripIsComplete(): bool {
    $query = $this->currentQuery();
    $from = $this->locationTerm($query['from']);
    if (!$from) {
      return FALSE;
    }
    if (!$this->hasRentalWindow()) {
      return FALSE;
    }
    $to = $this->locationTerm($query['to'] !== '' ? $query['to'] : $query['from']);
    if (!$to) {
      return FALSE;
    }
    if ($this->locationNeedsPlace((string) $from->id()) || $this->locationNeedsPlace((string) $to->id())) {
      return $query['place'] !== '';
    }

    return TRUE;
  }

  /**
   * Values the fleet banner JS uses to compare the form with the last search.
   *
   * @return array<string, mixed>
   */
  public function searchClientSettings(): array {
    $query = $this->currentQuery();
    $earliest = $this->earliestPickupInstant();

    return [
      'tripComplete' => $this->tripIsComplete(),
      'hasRentalWindow' => $this->hasRentalWindow(),
      'leadHours' => self::LEAD_HOURS,
      'minRentalHours' => self::MIN_RENTAL_HOURS,
      'earliestPickup' => $earliest->format('c'),
      'defaultWindow' => $this->defaultWindow(),
      'committed' => [
        'pickup' => $query['pickup'],
        'return' => $query['return'],
        'ptime' => $query['ptime'],
        'rtime' => $query['rtime'],
      ],
      'locations' => [
        'from' => $query['from'],
        'to' => $query['to'],
        'place' => $query['place'],
        'category' => $query['category'],
      ],
    ];
  }

  /**
   * Query for sending the guest back to fleet to finish the trip.
   *
   * @return array<string, string|null>
   */
  public function incompleteTripQuery(?string $category_id = NULL): array {
    $query = $this->currentQuery();
    if ($query['category'] === '' && $category_id) {
      $query['category'] = $category_id;
    }
    $query['need'] = 'trip';

    return $query;
  }

  public function wantsTripPrompt(): bool {
    $need = $this->requestStack->getCurrentRequest()?->query->get('need');

    return is_scalar($need) && (string) $need === 'trip';
  }

  /**
   * Show the banner hint when the guest is mid-search without a complete trip.
   */
  public function shouldPromptForTrip(): bool {
    if ($this->tripIsComplete()) {
      return FALSE;
    }
    if ($this->wantsTripPrompt()) {
      return TRUE;
    }
    $query = $this->currentQuery();

    return $query['category'] !== '' || $query['from'] !== '';
  }

  public function hasFilters(): bool {
    $query = $this->currentQuery();
    return !empty($query['from']) || !empty($query['to']) || !empty($query['place']) || !empty($query['pickup']) || !empty($query['ptime']) || !empty($query['return']) || !empty($query['rtime']) || !empty($query['category']) || !empty($query['brand']) || !empty($query['model']) || !empty($query['color']) || !empty($query['price']);
  }

  public function validWindow(string $pickup, string $return): bool {
    if ($pickup === '' || $return === '') {
      return FALSE;
    }
    $start = $this->parseDay($pickup);
    $end = $this->parseDay($return);

    return $start instanceof \DateTimeImmutable
      && $end instanceof \DateTimeImmutable
      && $end >= $start;
  }

  public function timezone(): \DateTimeZone {
    $name = (string) $this->configFactory->get('system.date')->get('timezone.default');
    return new \DateTimeZone($name !== '' ? $name : 'UTC');
  }

  public function now(): \DateTimeImmutable {
    return (new \DateTimeImmutable('@' . $this->time->getRequestTime()))->setTimezone($this->timezone());
  }

  /**
   * First selectable pickup instant (now + lead time, rounded up to an hour).
   */
  public function earliestPickupInstant(): \DateTimeImmutable {
    return $this->ceilToHour($this->now()->modify('+' . self::LEAD_HOURS . ' hours'));
  }

  /**
   * Earliest return instant for a pickup date/time.
   */
  public function minReturnInstant(string $pickup, string $ptime, ?int $min_rental_hours = NULL): ?\DateTimeImmutable {
    $start = $this->instant($pickup, $ptime);
    if (!$start) {
      return NULL;
    }
    $hours = $min_rental_hours ?? self::MIN_RENTAL_HOURS;
    if ($hours <= 0) {
      return $start;
    }

    return $this->ceilToHour($start->modify('+' . $hours . ' hours'));
  }

  public function validTrip(string $pickup, string $ptime, string $return, string $rtime, ?int $min_rental_hours = NULL): bool {
    return $this->tripIssue($pickup, $ptime, $return, $rtime, $min_rental_hours) === NULL;
  }

  /**
   * @return 'window'|'lead'|'return'|null
   */
  public function tripIssue(string $pickup, string $ptime, string $return, string $rtime, ?int $min_rental_hours = NULL): ?string {
    if (!$this->validWindow($pickup, $return)) {
      return 'window';
    }
    $start = $this->instant($pickup, $ptime);
    $end = $this->instant($return, $rtime);
    if (!$start || !$end) {
      return 'window';
    }
    if ($start < $this->earliestPickupInstant()) {
      return 'lead';
    }
    $min_return = $this->minReturnInstant($pickup, $ptime, $min_rental_hours);
    if (!$min_return || $end < $min_return) {
      return 'return';
    }

    return NULL;
  }

  public function parseDay(string $date): ?\DateTimeImmutable {
    $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $this->timezone());
    if (!$parsed instanceof \DateTimeImmutable || $parsed->format('Y-m-d') !== $date) {
      return NULL;
    }

    return $parsed;
  }

  public function instant(string $date, string $time): ?\DateTimeImmutable {
    $day = $this->parseDay($date);
    if (!$day) {
      return NULL;
    }
    $hour = $this->hourValue($time);
    $parts = explode(':', $hour);

    return $day->setTime((int) $parts[0], (int) ($parts[1] ?? 0));
  }

  public function ceilToHour(\DateTimeImmutable $instant): \DateTimeImmutable {
    $local = $instant->setTimezone($this->timezone());
    if ((int) $local->format('i') === 0 && (int) $local->format('s') === 0) {
      return $local->setTime((int) $local->format('G'), 0);
    }
    $next = $local->modify('+1 hour');

    return $next->setTime((int) $next->format('G'), 0);
  }

}
