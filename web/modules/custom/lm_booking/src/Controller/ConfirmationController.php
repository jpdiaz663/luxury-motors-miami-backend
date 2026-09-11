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
use Drupal\lm_booking\ReservationCode;
use Drupal\lm_vehicle\FleetCatalog;
use Drupal\lm_vehicle\VehiclePresenter;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Post-checkout itinerary. Parameter is {booking}, not {node}.
 */
final class ConfirmationController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly FleetCatalog $fleetCatalog,
    private readonly VehiclePresenter $presenter,
    private readonly QuoteCalculator $quoteCalculator,
    private readonly ReservationCode $reservationCode,
    private readonly RequestStack $requestStack,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('lm_vehicle.fleet_catalog'),
      $container->get('lm_vehicle.presenter'),
      $container->get('lm_booking.quote'),
      $container->get('lm_booking.reservation_code'),
      $container->get('request_stack'),
    );
  }

  /**
   * @return array<string, mixed>
   */
  public function view(NodeInterface $booking): array {
    $vehicle = $this->referencedVehicle($booking);
    $query = $this->fleetCatalog->currentQuery();
    $window = $this->fleetCatalog->resolvedWindow();
    $pickup = $this->bookingDate($booking, 'field_booking_start') ?: $window['pickup'];
    $return = $this->bookingDate($booking, 'field_booking_end') ?: $window['return'];
    $ptime = $this->fleetCatalog->hourValue($query['ptime']);
    $rtime = $this->fleetCatalog->hourValue($query['rtime']);

    $from = $this->fleetCatalog->locationTerm($query['from']);
    $to = $this->fleetCatalog->locationTerm($query['to']);
    $pickup_place = $from ? (string) $from->label() : '';
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
      '#reference' => $this->reservationCode->fromBooking($booking) ?: (string) $booking->id(),
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
    $confirmed = $booking->bundle() === 'booking'
      && $booking->isPublished()
      && $booking->hasField('field_booking_status')
      && $booking->get('field_booking_status')->value === 'confirmed';
    $result = AccessResult::allowedIf($confirmed)->addCacheableDependency($booking);
    $code = $this->reservationCode->fromBooking($booking);
    if ($code === '') {
      return $result;
    }
    $digest = $this->requestStack->getCurrentRequest()?->query->get(ReservationCode::QUERY_KEY);
    $digest = is_scalar($digest) ? (string) $digest : '';
    return $result->andIf(AccessResult::allowedIf($this->reservationCode->matches($code, $digest)))
      ->addCacheContexts(['url.query_args:' . ReservationCode::QUERY_KEY]);
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
