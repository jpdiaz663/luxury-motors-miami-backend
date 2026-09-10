<?php

declare(strict_types=1);

namespace Drupal\lm_booking\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\lm_booking\AvailabilityManager;
use Drupal\lm_booking\Quote\QuoteCalculator;
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
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('lm_vehicle.fleet_catalog'),
      $container->get('lm_vehicle.presenter'),
      $container->get('lm_booking.quote'),
      $container->get('lm_booking.availability'),
      $container->get('entity_type.manager'),
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
    $pickup = (string) ($form_state->getValue('pickup') ?: ($query['pickup'] !== '' ? $query['pickup'] : ''));
    $return = (string) ($form_state->getValue('return') ?: ($query['return'] !== '' ? $query['return'] : ''));
    $ptime = $query['ptime'] !== '' ? $this->fleetCatalog->hourValue($query['ptime']) : '07:00';
    $rtime = $query['rtime'] !== '' ? $this->fleetCatalog->hourValue($query['rtime']) : '07:00';

    $from = $this->fleetCatalog->locationTerm($query['from']);
    $to = $this->fleetCatalog->locationTerm($query['to']);
    $pickup_place = $from ? (string) $from->label() : (string) $this->t('Miami');
    $dropoff_place = $to ? (string) $to->label() : $pickup_place;
    if ($query['place'] !== '') {
      $pickup_place .= ' — ' . $query['place'];
    }

    $daily = 0.0;
    if ($node->hasField('field_daily_price') && !$node->get('field_daily_price')->isEmpty()) {
      $daily = (float) $node->get('field_daily_price')->value;
    }
    $days = ($pickup !== '' && $return !== '') ? $this->quoteCalculator->days($pickup, $return) : 1;
    $quote = $this->quoteCalculator->quote($daily, $days, $pickup_place, $dropoff_place);

    $hours = $this->fleetCatalog->hourOptions();
    $card = $this->presenter->card($node);

    $form['#theme'] = 'lm_booking_checkout_form';
    $form['#trip'] = [
      'title' => $card['display_title'] ?? $node->label(),
      'image' => $card['image'] ?? NULL,
      'pickup_place' => $from ? (string) $from->label() : (string) $this->t('Miami'),
      'dropoff_place' => $to ? (string) $to->label() : (string) $this->t('Miami'),
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
    $form['pickup'] = [
      '#type' => 'date',
      '#title' => $this->t('Start'),
      '#required' => TRUE,
      '#default_value' => $pickup !== '' ? $pickup : NULL,
      '#attributes' => ['min' => $today],
    ];
    $form['return'] = [
      '#type' => 'date',
      '#title' => $this->t('End'),
      '#required' => TRUE,
      '#default_value' => $return !== '' ? $return : NULL,
      '#attributes' => ['min' => $today],
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
    $pickup = (string) $form_state->getValue('pickup');
    $return = (string) $form_state->getValue('return');
    if ($pickup === '' || $return === '') {
      return;
    }
    if ($return < $pickup) {
      $form_state->setErrorByName('return', $this->t('Return must be on or after pickup.'));
      return;
    }

    $vehicle = $form_state->get('vehicle');
    if (!$vehicle instanceof NodeInterface) {
      return;
    }
    $blocked = $this->availability->unavailableVehicleIds($pickup, $return);
    if (in_array((int) $vehicle->id(), $blocked, TRUE)) {
      $form_state->setErrorByName('pickup', $this->t('This vehicle is not available for the selected dates.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $vehicle = $form_state->get('vehicle');
    if (!$vehicle instanceof NodeInterface) {
      return;
    }

    $name = trim((string) $form_state->getValue('name'));
    $storage = $this->entityTypeManager->getStorage('node');
    /** @var \Drupal\node\NodeInterface $booking */
    $booking = $storage->create([
      'type' => 'booking',
      'title' => $name . ' — ' . $vehicle->label(),
      'status' => 1,
      'uid' => $this->currentUser()->id(),
    ]);
    $booking->set('field_booking_vehicle', (int) $vehicle->id());
    $booking->set('field_booking_start', (string) $form_state->getValue('pickup'));
    $booking->set('field_booking_end', (string) $form_state->getValue('return'));
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

    $query = $this->fleetCatalog->bookingQuery();
    $query['pickup'] = (string) $form_state->getValue('pickup');
    $query['return'] = (string) $form_state->getValue('return');
    $form_state->setRedirect('lm_booking.confirmation', ['booking' => $booking->id()], [
      'query' => array_filter($query, static fn($value): bool => $value !== NULL && $value !== ''),
    ]);
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
