<?php

declare(strict_types=1);

namespace Drupal\lm_booking\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\lm_booking\AvailabilityManager;
use Drupal\lm_booking\Form\CheckoutForm;
use Drupal\lm_vehicle\FleetCatalog;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Checkout route title, vehicle access, and trip gate.
 *
 * The route parameter is {vehicle}, not {node}, so node route context
 * and vehicle Block Layout do not treat checkout as a vehicle page.
 */
final class CheckoutController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly FleetCatalog $fleetCatalog,
    private readonly AvailabilityManager $availability,
    private readonly FormBuilderInterface $formBuilder,
    private readonly MessengerInterface $messenger,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('lm_vehicle.fleet_catalog'),
      $container->get('lm_booking.availability'),
      $container->get('form_builder'),
      $container->get('messenger'),
    );
  }

  /**
   * @return array<string, mixed>|\Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function form(NodeInterface $vehicle): array|RedirectResponse {
    $category_id = '';
    if ($vehicle->hasField('field_category') && !$vehicle->get('field_category')->isEmpty()) {
      $term = $vehicle->get('field_category')->entity;
      $category_id = $term ? (string) $term->id() : '';
    }

    if (!$this->fleetCatalog->tripIsComplete()) {
      $this->messenger->addError($this->t('Add a pickup location and search dates before reserving.'));
      return $this->backToFleet($this->fleetCatalog->incompleteTripQuery($category_id));
    }

    $window = $this->fleetCatalog->resolvedWindow();
    if (!$this->availability->isVehicleAvailable((int) $vehicle->id(), $window['pickup'], $window['return'])) {
      $this->messenger->addError($this->t('This vehicle is no longer available for the selected dates. Please choose another vehicle.'));
      return $this->backToFleet($this->fleetCatalog->currentQuery());
    }

    return $this->formBuilder->getForm(CheckoutForm::class, $vehicle);
  }

  /**
   * @param array<string, string|null> $query
   */
  private function backToFleet(array $query): RedirectResponse {
    return new RedirectResponse($this->fleetCatalog->fleetUrl($query), 302, [
      'Cache-Control' => 'no-store, no-cache, must-revalidate',
    ]);
  }

  public function title(NodeInterface $vehicle): TranslatableMarkup {
    return new TranslatableMarkup('Checkout — @title', ['@title' => $vehicle->label()]);
  }

  public function access(NodeInterface $vehicle, AccountInterface $account): AccessResultInterface {
    $allowed = $vehicle->bundle() === 'vehicle' && $vehicle->isPublished() && $vehicle->access('view', $account);
    return AccessResult::allowedIf($allowed)->addCacheableDependency($vehicle);
  }

}
