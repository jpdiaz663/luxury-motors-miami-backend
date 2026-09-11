<?php

declare(strict_types=1);

namespace Drupal\lm_booking\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\lm_booking\AvailabilityManager;
use Drupal\lm_vehicle\FleetCatalog;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Read-only availability check used by Reserve before opening checkout.
 */
final class AvailabilityCheckController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly FleetCatalog $fleetCatalog,
    private readonly AvailabilityManager $availability,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('lm_vehicle.fleet_catalog'),
      $container->get('lm_booking.availability'),
    );
  }

  public function check(NodeInterface $vehicle): JsonResponse {
    $query = $this->fleetCatalog->currentQuery();
    $pickup = $query['pickup'];
    $return = $query['return'];
    $available = $vehicle->isPublished()
      && $this->availability->isVehicleAvailable((int) $vehicle->id(), $pickup, $return);

    $payload = [
      'available' => $available,
      'message' => $available
        ? ''
        : (string) $this->t('This vehicle is no longer available for the selected dates. Please choose another vehicle.'),
      'fleet' => $this->fleetCatalog->fleetUrl($query),
    ];
    if ($available) {
      $payload['checkout'] = Url::fromRoute('lm_booking.checkout', ['vehicle' => $vehicle->id()], [
        'query' => $this->fleetCatalog->bookingQuery(),
      ])->toString();
    }

    return new JsonResponse($payload, 200, [
      'Cache-Control' => 'no-store, no-cache, must-revalidate',
    ]);
  }

}
