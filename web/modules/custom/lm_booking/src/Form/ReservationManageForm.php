<?php

declare(strict_types=1);

namespace Drupal\lm_booking\Form;

use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\lm_booking\BookingLookup;
use Drupal\lm_booking\ReservationPresenter;
use Drupal\lm_vehicle\FleetCatalog;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Public reservation lookup by code and email, with cancel.
 */
final class ReservationManageForm extends FormBase {

  private const FLOOD_NAME = 'lm_booking.reservation_lookup';
  private const FLOOD_WINDOW = 3600;
  private const FLOOD_LIMIT = 8;

  public function __construct(
    protected BookingLookup $lookup,
    protected ReservationPresenter $presenter,
    protected FloodInterface $flood,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('lm_booking.booking_lookup'),
      $container->get('lm_booking.reservation_presenter'),
      $container->get('flood'),
    );
  }

  public function getFormId(): string {
    return 'lm_booking_reservation_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $booking = $this->bookingFromState($form_state);
    $form['#theme'] = 'lm_booking_reservation_form';
    $form['#theme_wrappers'] = [];
    $form['#method'] = 'post';
    $form['#attributes']['class'][] = 'reservation-form';
    $form['#attributes']['method'] = 'post';
    $form['#attributes']['accept-charset'] = 'UTF-8';
    $form['#attached']['library'][] = 'luxury_motors/checkout';
    $form['#cache']['max-age'] = 0;
    $form['#fleet_url'] = Url::fromUserInput(FleetCatalog::PATH)->toString();
    $form['#disclaimer'] = (string) $this->t('No es posible modificar una reserva existente. Si cambia la fecha, la hora, el lugar, el nombre, el tipo de vehículo o el plan de alquiler, deberá hacer una nueva reserva (sujeta a disponibilidad y a las tarifas vigentes). Le recomendamos que haga una nueva reserva y, una vez confirmada, cancele la reserva anterior.');
    $form['#guest'] = [];
    $form['#trip'] = [];
    $form['#quote'] = [];
    $form['#reference'] = '';
    $form['#status'] = '';
    $form['#status_label'] = '';
    $form['#cancellable'] = FALSE;

    $form['lookup'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['reservation-lookup']],
      '#access' => $booking === NULL,
    ];
    $request = $this->getRequest();
    $code_default = is_scalar($request->query->get('code')) ? (string) $request->query->get('code') : '';
    $form['lookup']['code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Confirmation code'),
      '#required' => $booking === NULL,
      '#maxlength' => 16,
      '#default_value' => $code_default,
      '#attributes' => [
        'autocomplete' => 'off',
        'placeholder' => 'LM-XXXXXX',
        'spellcheck' => 'false',
      ],
    ];
    $form['lookup']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => $booking === NULL,
      '#attributes' => ['autocomplete' => 'email'],
    ];

    if ($booking instanceof NodeInterface) {
      $view = $this->presenter->build($booking);
      $form['#guest'] = $view['guest'];
      $form['#trip'] = $view['trip'];
      $form['#quote'] = $view['quote'];
      $form['#reference'] = $view['reference'];
      $form['#status'] = $view['status'];
      $form['#status_label'] = $view['status_label'];
      $form['#cancellable'] = $view['cancellable'];

      $form['confirm_cancel'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('I understand this reservation will be cancelled and cannot be restored from this page.'),
        '#access' => (bool) $view['cancellable'],
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['lookup'] = [
      '#type' => 'submit',
      '#value' => $this->t('View reservation'),
      '#submit' => ['::submitLookup'],
      '#validate' => ['::validateLookup'],
      '#access' => $booking === NULL,
      '#attributes' => ['class' => ['btn', 'btn--primary']],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel reservation'),
      '#submit' => ['::submitCancel'],
      '#validate' => ['::validateCancel'],
      '#limit_validation_errors' => [['confirm_cancel']],
      '#access' => $booking instanceof NodeInterface && (bool) ($form['#cancellable'] ?? FALSE),
      '#attributes' => ['class' => ['btn', 'btn--secondary', 'reservation-cancel']],
    ];
    $form['actions']['another'] = [
      '#type' => 'submit',
      '#value' => $this->t('Look up another reservation'),
      '#submit' => ['::submitAnother'],
      '#limit_validation_errors' => [],
      '#access' => $booking instanceof NodeInterface,
      '#attributes' => ['class' => ['btn', 'btn--secondary']],
    ];

    return $form;
  }

  public function validateLookup(array &$form, FormStateInterface $form_state): void {
    if (!$this->flood->isAllowed(self::FLOOD_NAME, self::FLOOD_LIMIT, self::FLOOD_WINDOW)) {
      $form_state->setErrorByName('code', $this->t('Too many attempts. Try again later.'));
      return;
    }
    $this->flood->register(self::FLOOD_NAME, self::FLOOD_WINDOW);

    $booking = $this->lookup->find(
      (string) $form_state->getValue('code'),
      (string) $form_state->getValue('email'),
    );
    if (!$booking instanceof NodeInterface) {
      $form_state->setErrorByName('code', $this->t('We could not find a reservation with that confirmation code and email.'));
      return;
    }
    $form_state->set('booking_id', (int) $booking->id());
  }

  public function submitLookup(array &$form, FormStateInterface $form_state): void {
    $this->flood->clear(self::FLOOD_NAME, (string) $this->getRequest()->getClientIp());
    $form_state->setRebuild();
  }

  public function validateCancel(array &$form, FormStateInterface $form_state): void {
    $booking = $this->bookingFromState($form_state);
    if (!$booking instanceof NodeInterface) {
      $form_state->setErrorByName('confirm_cancel', $this->t('Look up your reservation before cancelling.'));
      return;
    }
    $view = $this->presenter->build($booking);
    if (!$view['cancellable']) {
      $form_state->setErrorByName('confirm_cancel', $this->t('This reservation cannot be cancelled.'));
      return;
    }
    if (!(bool) $form_state->getValue('confirm_cancel')) {
      $form_state->setErrorByName('confirm_cancel', $this->t('Confirm that you want to cancel this reservation.'));
    }
  }

  public function submitCancel(array &$form, FormStateInterface $form_state): void {
    $booking = $this->bookingFromState($form_state);
    if (!$booking instanceof NodeInterface || !$booking->hasField('field_booking_status')) {
      return;
    }
    $booking->set('field_booking_status', 'cancelled');
    $booking->save();
    $this->messenger()->addStatus($this->t('Your reservation has been cancelled.'));
    $this->logger('lm_booking')->notice('Reservation @code cancelled.', [
      '@code' => $this->presenter->build($booking)['reference'],
    ]);
    $form_state->setRebuild();
  }

  public function submitAnother(array &$form, FormStateInterface $form_state): void {
    $form_state->set('booking_id', NULL);
    $form_state->setRebuild();
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  private function bookingFromState(FormStateInterface $form_state): ?NodeInterface {
    $id = (int) $form_state->get('booking_id');
    return $id > 0 ? $this->lookup->load($id) : NULL;
  }

}
