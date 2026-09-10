<?php

declare(strict_types=1);

namespace Drupal\lm_booking\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\lm_booking\Quote\QuoteCalculator;
use Drupal\lm_vehicle\FleetCatalog;
use Drupal\lm_vehicle\VehiclePresenter;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Post-checkout itinerary. Parameter is {booking}, not {node}.
 */
final class ConfirmationController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly FleetCatalog $fleetCatalog,
    private readonly VehiclePresenter $presenter,
    private readonly QuoteCalculator $quoteCalculator,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('lm_vehicle.fleet_catalog'),
      $container->get('lm_vehicle.presenter'),
      $container->get('lm_booking.quote'),
    );
  }

  /**
   * @return array<string, mixed>
   */
  public function view(NodeInterface $booking): array {
    $vehicle = $this->referencedVehicle($booking);
    $query = $this->fleetCatalog->currentQuery();
    $pickup = $this->bookingDate($booking, 'field_booking_start') ?: $query['pickup'];
    $return = $this->bookingDate($booking, 'field_booking_end') ?: $query['return'];
    $ptime = $query['ptime'] !== '' ? $this->fleetCatalog->hourValue($query['ptime']) : '07:00';
    $rtime = $query['rtime'] !== '' ? $this->fleetCatalog->hourValue($query['rtime']) : '07:00';

    $from = $this->fleetCatalog->locationTerm($query['from']);
    $to = $this->fleetCatalog->locationTerm($query['to']);
    $pickup_place = $from ? (string) $from->label() : (string) $this->t('Miami');
    $dropoff_place = $to ? (string) $to->label() : $pickup_place;

    $daily = 0.0;
    $card = [];
    if ($vehicle instanceof NodeInterface) {
      $card = $this->presenter->card($vehicle);
      if ($vehicle->hasField('field_daily_price') && !$vehicle->get('field_daily_price')->isEmpty()) {
        $daily = (float) $vehicle->get('field_daily_price')->value;
      }
    }
    $days = ($pickup !== '' && $return !== '') ? $this->quoteCalculator->days($pickup, $return) : 1;
    $quote = $this->quoteCalculator->quote($daily, $days, $pickup_place, $dropoff_place);
    $hours = $this->fleetCatalog->hourOptions();

    $name = $booking->hasField('field_customer_name') ? (string) $booking->get('field_customer_name')->value : '';
    $email = $booking->hasField('field_customer_email') ? (string) $booking->get('field_customer_email')->value : '';
    $phone = $booking->hasField('field_customer_phone') ? (string) $booking->get('field_customer_phone')->value : '';

    return [
      '#theme' => 'lm_booking_confirmation',
      '#reference' => (string) $booking->id(),
      '#guest' => [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
      ],
      '#trip' => [
        'title' => $card['display_title'] ?? ($vehicle ? $vehicle->label() : $booking->label()),
        'image' => $card['image'] ?? NULL,
        'pickup_place' => $pickup_place,
        'dropoff_place' => $dropoff_place,
        'pickup_when' => $this->whenLabel($pickup, $ptime, $hours),
        'dropoff_when' => $this->whenLabel($return, $rtime, $hours),
      ],
      '#quote' => $quote->toArray(),
      '#fleet_url' => Url::fromUserInput(FleetCatalog::PATH)->toString(),
      '#attached' => ['library' => ['luxury_motors/checkout']],
      '#cache' => [
        'max-age' => 0,
        'contexts' => ['url.query_args', 'url.path'],
        'tags' => $booking->getCacheTags(),
      ],
    ];
  }

  public function access(NodeInterface $booking, AccountInterface $account): AccessResultInterface {
    $allowed = $booking->bundle() === 'booking'
      && $booking->isPublished()
      && $booking->hasField('field_booking_status')
      && $booking->get('field_booking_status')->value === 'confirmed';
    return AccessResult::allowedIf($allowed)->addCacheableDependency($booking);
  }

  private function referencedVehicle(NodeInterface $booking): ?NodeInterface {
    if (!$booking->hasField('field_booking_vehicle') || $booking->get('field_booking_vehicle')->isEmpty()) {
      return NULL;
    }
    $vehicle = $booking->get('field_booking_vehicle')->entity;
    return $vehicle instanceof NodeInterface ? $vehicle : NULL;
  }

  private function bookingDate(NodeInterface $booking, string $field): string {
    if (!$booking->hasField($field) || $booking->get($field)->isEmpty()) {
      return '';
    }
    return substr((string) $booking->get($field)->value, 0, 10);
  }

  /**
   * @param array<string, string> $hours
   */
  private function whenLabel(string $date, string $time, array $hours): string {
    if ($date === '') {
      return '';
    }
    $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
    $day = $parsed ? $parsed->format('m/d/Y') : $date;
    return $day . ' — ' . ($hours[$time] ?? $time);
  }

}
