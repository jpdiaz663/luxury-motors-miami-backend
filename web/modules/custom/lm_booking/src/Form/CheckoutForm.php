<?php

declare(strict_types=1);

namespace Drupal\lm_booking\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Url;
use Drupal\lm_booking\AvailabilityManager;
use Drupal\lm_booking\Quote\QuoteCalculator;
use Drupal\lm_booking\ReservationCode;
use Drupal\lm_vehicle\FleetCatalog;
use Drupal\lm_vehicle\VehiclePresenter;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Customer checkout. Creates a confirmed booking hold (no payment).
 */
final class CheckoutForm extends FormBase {

  public function __construct(
    private readonly FleetCatalog $fleetCatalog,
    private readonly VehiclePresenter $presenter,
    private readonly QuoteCalculator $quoteCalculator,
    private readonly AvailabilityManager $availability,
    private readonly ReservationCode $reservationCode,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LockBackendInterface $lock,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('lm_vehicle.fleet_catalog'),
      $container->get('lm_vehicle.presenter'),
      $container->get('lm_booking.quote'),
      $container->get('lm_booking.availability'),
      $container->get('lm_booking.reservation_code'),
      $container->get('entity_type.manager'),
      $container->get('lock'),
    );
  }

  public function getFormId(): string {
    return 'lm_booking_checkout_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $vehicle = NULL): array {
    $node = $vehicle instanceof NodeInterface ? $vehicle : $form_state->get('vehicle');
    if (!$node instanceof NodeInterface || $node->bundle() !== 'vehicle') {
      return $form;
    }
    $form_state->set('vehicle', $node);

    $query = $this->fleetCatalog->currentQuery();
    $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
    $window = $this->fleetCatalog->resolvedWindow();
    $pickup = (string) ($form_state->getValue('pickup') ?: $window['pickup']);
    $return = (string) ($form_state->getValue('return') ?: $window['return']);
    $ptime = $this->fleetCatalog->hourValue($query['ptime']);
    $rtime = $this->fleetCatalog->hourValue($query['rtime']);

    $from = $this->fleetCatalog->locationTerm($query['from']);
    $to = $this->fleetCatalog->locationTerm($query['to']);
    $pickup_place = $from ? (string) $from->label() : '';
    $dropoff_place = $to ? (string) $to->label() : $pickup_place;
    if ($query['place'] !== '' && $pickup_place !== '') {
      $pickup_place .= ' — ' . $query['place'];
    }

    $daily = 0.0;
    if ($node->hasField('field_daily_price') && !$node->get('field_daily_price')->isEmpty()) {
      $daily = (float) $node->get('field_daily_price')->value;
    }
    $days = $this->quoteCalculator->days($pickup, $return);
    $quote = $this->quoteCalculator->quote($daily, $days, $pickup_place, $dropoff_place);

    $hours = $this->fleetCatalog->hourOptions();
    $card = $this->presenter->card($node);

    $form['#theme'] = 'lm_booking_checkout_form';
    $form['#trip'] = [
      'title' => $card['display_title'] ?? $node->label(),
      'image' => $card['image'] ?? NULL,
      'pickup_place' => $from ? (string) $from->label() : '',
      'dropoff_place' => $to ? (string) $to->label() : '',
      'pickup_when' => $this->whenLabel($pickup, $ptime, $hours),
      'dropoff_when' => $this->whenLabel($return, $rtime, $hours),
      'place' => $query['place'],
    ];
    $form['#quote'] = $quote->toArray();
    $form['#cache']['contexts'][] = 'url.query_args';
    $form['#cache']['contexts'][] = 'url.path';
    $form['#cache']['tags'][] = 'node:' . $node->id();
    $form['#attached']['library'][] = 'luxury_motors/checkout';
    $form['#attributes']['class'][] = 'checkout-form';

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#attributes' => ['autocomplete' => 'name'],
    ];
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#attributes' => ['autocomplete' => 'email'],
    ];
    $form['phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone'),
      '#required' => TRUE,
      '#attributes' => ['autocomplete' => 'tel'],
    ];
    
    $form['note'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Note'),
      '#required' => FALSE,
      '#attributes' => [
        'placeholder' => $this->t('Hotel, airport, or desk'),
        'autocomplete' => 'street-address',
      ],
    ];

    $terms = Url::fromUserInput('/terms-and-conditions')->toString();
    $form['terms'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I have read and agree to the <a href="@url">Terms and Conditions</a> of the website.', [
        '@url' => $terms,
      ]),
      '#required' => TRUE,
    ];
    $form['marketing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I agree to receive promotional emails from Luxury Motors.'),
      '#required' => FALSE,
      '#default_value' => 0,
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Confirm Booking'),
        '#attributes' => ['class' => ['btn', 'btn--primary', 'checkout-cta']],
      ],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $this->assertAvailable($form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $vehicle = $form_state->get('vehicle');
    if (!$vehicle instanceof NodeInterface) {
      return;
    }

    $lock_name = 'lm_booking.vehicle.' . $vehicle->id();
    if (!$this->lock->acquire($lock_name, 15.0)) {
      $form_state->setErrorByName('actions', $this->t('This vehicle is being reserved. Wait a moment and try again.'));
      $form_state->setRebuild();
      return;
    }

    try {
      $this->assertAvailable($form_state);
      if ($form_state->hasAnyErrors()) {
        $form_state->setRebuild();
        return;
      }

      $window = $this->fleetCatalog->resolvedWindow();
      $name = trim((string) $form_state->getValue('name'));
      $code = $this->reservationCode->mint();
      $storage = $this->entityTypeManager->getStorage('node');
      /** @var \Drupal\node\NodeInterface $booking */
      $booking = $storage->create([
        'type' => 'booking',
        'title' => $code,
        'status' => 1,
        'uid' => $this->currentUser()->id(),
      ]);
      if ($booking->hasField('field_booking_code')) {
        $booking->set('field_booking_code', $code);
      }
      $booking->set('field_booking_vehicle', (int) $vehicle->id());
      $booking->set('field_booking_start', $window['pickup']);
      $booking->set('field_booking_end', $window['return']);
      $booking->set('field_booking_status', 'confirmed');
      if ($booking->hasField('field_customer_name')) {
        $booking->set('field_customer_name', $name);
      }
      if ($booking->hasField('field_customer_email')) {
        $booking->set('field_customer_email', (string) $form_state->getValue('email'));
      }
      if ($booking->hasField('field_customer_phone')) {
        $booking->set('field_customer_phone', (string) $form_state->getValue('phone'));
      }
      if ($booking->hasField('field_customer_note')) {
        $booking->set('field_customer_note', trim((string) $form_state->getValue('note')));
      }
      if ($booking->hasField('field_marketing_opt_in')) {
        $booking->set('field_marketing_opt_in', (bool) $form_state->getValue('marketing'));
      }
      $booking->save();
    }
    finally {
      $this->lock->release($lock_name);
    }

    if ($form_state->hasAnyErrors() || !isset($booking)) {
      return;
    }

    $query = $this->fleetCatalog->bookingQuery();
    $query[ReservationCode::QUERY_KEY] = $this->reservationCode->digest($code);
    $form_state->setRedirect('lm_booking.confirmation', ['booking' => $booking->id()], [
      'query' => array_filter($query, static fn($value): bool => $value !== NULL && $value !== ''),
    ]);
  }

  private function assertAvailable(FormStateInterface $form_state): void {
    if (!$this->fleetCatalog->hasRentalWindow()) {
      $form_state->setErrorByName('actions', $this->t('Search dates are required before confirming a reservation.'));
      return;
    }

    $window = $this->fleetCatalog->resolvedWindow();
    $pickup = $window['pickup'];
    $return = $window['return'];
    if ($return < $pickup) {
      $form_state->setErrorByName('actions', $this->t('Return must be on or after pickup.'));
      return;
    }

    $vehicle = $form_state->get('vehicle');
    if (!$vehicle instanceof NodeInterface) {
      return;
    }
    if (!$this->availability->isVehicleAvailable((int) $vehicle->id(), $pickup, $return)) {
      $form_state->setErrorByName('actions', $this->t('This vehicle is no longer available for the selected dates. Please choose another vehicle.'));
    }
  }

  /**
   * @param array<string, string> $hours
   */
  private function whenLabel(string $date, string $time, array $hours): string {
    if ($date === '') {
      return (string) $this->t('Confirm dates');
    }
    $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
    $day = $parsed ? $parsed->format('m/d/Y') : $date;
    $label = $hours[$time] ?? $time;
    return $day . ' — ' . $label;
  }

}
