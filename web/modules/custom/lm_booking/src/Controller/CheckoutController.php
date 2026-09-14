<?php

declare(strict_types=1);

namespace Drupal\lm_booking\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\lm_booking\AvailabilityManager;
use Drupal\lm_booking\CheckoutSession;
use Drupal\lm_booking\Form\CheckoutForm;
use Drupal\lm_vehicle\FleetCatalog;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Checkout page. Trip lives in the Drupal session, not the URL.
 */
final class CheckoutController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly CheckoutSession $checkoutSession,
    private readonly AvailabilityManager $availability,
    private readonly FleetCatalog $fleetCatalog,
    private readonly FormBuilderInterface $formBuilder,
    private readonly MessengerInterface $messenger,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('lm_booking.checkout_session'),
      $container->get('lm_booking.availability'),
      $container->get('lm_vehicle.fleet_catalog'),
      $container->get('form_builder'),
      $container->get('messenger'),
    );
  }

  /**
   * @return array<string, mixed>|\Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function form(): array|RedirectResponse {
    $vehicle = $this->checkoutSession->vehicle();
    $trip = $this->checkoutSession->get();
    if (!$vehicle instanceof NodeInterface || $trip === NULL) {
      $this->messenger->addError($this->t('Add a pickup location and search dates before reserving.'));
      return $this->backToFleet($this->fleetCatalog->currentQuery());
    }

    if (!$this->availability->isVehicleAvailable((int) $vehicle->id(), $trip['pickup'], $trip['return'])) {
      $query = $this->checkoutSession->fleetQuery();
      $this->checkoutSession->clear();
      $this->messenger->addError($this->t('This vehicle is no longer available for the selected dates. Please choose another vehicle.'));
      return $this->backToFleet($query ?: $this->fleetCatalog->currentQuery());
    }

    return $this->formBuilder->getForm(CheckoutForm::class, $vehicle);
  }

  /**
   * Old /reserve/{id} links land on the session checkout.
   */
  public function legacy(): RedirectResponse {
    return new RedirectResponse(Url::fromRoute('lm_booking.checkout')->toString(), 302, [
      'Cache-Control' => 'no-store, no-cache, must-revalidate',
    ]);
  }

  /**
   * @param array<string, string|null> $query
   */
  private function backToFleet(array $query): RedirectResponse {
    return new RedirectResponse($this->fleetCatalog->fleetUrl($query), 302, [
      'Cache-Control' => 'no-store, no-cache, must-revalidate',
    ]);
  }

  public function title(): TranslatableMarkup {
    $vehicle = $this->checkoutSession->vehicle();
    if ($vehicle instanceof NodeInterface) {
      return new TranslatableMarkup('Checkout — @title', ['@title' => $vehicle->label()]);
    }
    return new TranslatableMarkup('Checkout');
  }

}
