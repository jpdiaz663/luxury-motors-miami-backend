<?php

declare(strict_types=1);

namespace Drupal\lm_vehicle\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\lm_vehicle\FleetCatalog;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hero banner search. Submits GET params consumed by the vehicle_fleet view.
 */
final class VehicleSearchForm extends FormBase {

  public function __construct(
    private readonly FleetCatalog $fleetCatalog,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('lm_vehicle.fleet_catalog'),
    );
  }

  public function getFormId(): string {
    return 'lm_vehicle_search_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $query = $this->fleetCatalog->currentQuery();
    $min = $this->fleetCatalog->earliestPickupInstant()->format('Y-m-d');
    $defaults = $this->fleetCatalog->defaultWindow();
    $window = $this->fleetCatalog->resolvedWindow();
    $pickup = $window['pickup'];
    $return = $window['return'];
    $ptime = $query['ptime'] !== '' ? $this->fleetCatalog->hourValue($query['ptime']) : $defaults['ptime'];
    $rtime = $query['rtime'] !== '' ? $this->fleetCatalog->hourValue($query['rtime']) : $defaults['rtime'];
    $from_term = $this->fleetCatalog->locationTerm($query['from']);
    $to_term = $this->fleetCatalog->locationTerm($query['to']);

    $action = Url::fromUserInput(FleetCatalog::PATH)->toString();
    $form['#method'] = 'get';
    $form['#action'] = $action;
    $form['#theme'] = 'lm_vehicle_search_form';
    $form['#theme_wrappers'] = [];
    $form['#after_build'][] = [static::class, 'stripInternalElements'];
    $need_trip = $this->isFleetPath() && $this->fleetCatalog->shouldPromptForTrip();
    $on_fleet = $this->isFleetPath();
    $form['#need_trip'] = $need_trip;
    $form['#on_fleet'] = $on_fleet;
    $form['#attributes'] = [
      'class' => array_values(array_filter([
        'banner',
        $need_trip ? 'is-need-trip' : '',
        $on_fleet ? 'banner--fleet' : '',
      ])),
      'data-banner' => TRUE,
      'method' => 'get',
      'action' => $action,
      'accept-charset' => 'UTF-8',
    ];
    if ($on_fleet) {
      $form['#attributes']['data-banner-fleet'] = TRUE;
    }
    $form['#show_place'] = $this->fleetCatalog->locationNeedsPlace($query['from'])
      || $this->fleetCatalog->locationNeedsPlace($query['to']);
    $form['#reset_url'] = $action;
    $form['#cache']['contexts'][] = 'url.query_args';
    $form['#cache']['contexts'][] = 'url.path';
    $form['#cache']['tags'][] = 'taxonomy_term_list:location';
    $form['#cache']['max-age'] = 60;
    $form['#attached']['library'][] = 'luxury_motors/vehicle_search';
    $form['#attached']['drupalSettings']['lmVehicleSearch'] = [
      'requiresPlace' => $this->fleetCatalog->locationPlaceTids(),
      'resetUrl' => $action,
    ] + $this->fleetCatalog->searchClientSettings();
    try {
      $form['#attached']['drupalSettings']['lmVehicleSearch']['startUrl'] = Url::fromRoute('lm_booking.checkout_start')->toString();
    }
    catch (\Throwable) {
      $form['#attached']['drupalSettings']['lmVehicleSearch']['startUrl'] = '/checkout/start';
    }

    $form['from'] = $this->locationId('from', $from_term, 'banner-from-id');
    $form['from_q'] = $this->locationLookup('from_q', $this->t('Pickup location'), $from_term, TRUE, 'banner-from', 'Brickell, MIA, hotel…');
    $form['to'] = $this->locationId('to', $to_term, 'banner-to-id');
    $form['to_q'] = $this->locationLookup('to_q', $this->t('Delivery location'), $to_term, FALSE, 'banner-to', 'Same as pickup');
    $form['place'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Property name'),
      '#title_display' => 'invisible',
      '#default_value' => $query['place'],
      '#attributes' => [
        'id' => 'banner-place',
        'placeholder' => 'Faena, Four Seasons, residence…',
        'autocomplete' => 'street-address',
      ],
      '#theme_wrappers' => [],
    ];
    $form['pickup'] = $this->dateField('pickup', $pickup, $min, 'banner-pickup', TRUE);
    $form['ptime'] = $this->hourSelect('ptime', $ptime, 'banner-ptime', TRUE);
    $form['return'] = $this->dateField('return', $return, $min, 'banner-return', TRUE);
    $form['rtime'] = $this->hourSelect('rtime', $rtime, 'banner-rtime', TRUE);

    foreach (['category', 'brand', 'color', 'price'] as $name) {
      if ($query[$name] !== '') {
        $form[$name] = [
          '#type' => 'hidden',
          '#default_value' => $query[$name],
        ];
      }
    }

    $form['#filtered'] = $this->fleetCatalog->hasFilters();
    $this->hydrateGetInput($form_state, $query, $from_term, $to_term, $pickup, $return, $ptime, $rtime);

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // GET form: the browser query string is the submission.
  }

  private function isFleetPath(): bool {
    $path = '/' . trim((string) $this->getRequest()->getPathInfo(), '/');

    return $path === FleetCatalog::PATH || str_ends_with($path, FleetCatalog::PATH);
  }

  /**
   * GET landing pages treat the query string as user input, which blanks
   * fields that were omitted on submit (autocomplete twins, empty optionals).
   *
   * @param array<string, string> $query
   */
  private function hydrateGetInput(FormStateInterface $form_state, array $query, ?TermInterface $from_term, ?TermInterface $to_term, string $pickup, string $return, string $ptime, string $rtime): void {
    $input = $form_state->getUserInput();
    if (!is_array($input)) {
      $input = [];
    }

    // entity_autocomplete only paints labels when input is FALSE. A GET
    // from_q string is not a valid callback value and would blank the fields.
    unset($input['from_q'], $input['to_q']);

    $values = [
      'from' => $from_term ? (string) $from_term->id() : '',
      'to' => $to_term ? (string) $to_term->id() : '',
      'place' => $query['place'],
      'pickup' => $pickup,
      'ptime' => $ptime,
      'return' => $return,
      'rtime' => $rtime,
      'category' => $query['category'],
      'brand' => $query['brand'],
      'color' => $query['color'],
      'price' => $query['price'],
    ];
    foreach ($values as $key => $value) {
      if ($value === '' || (isset($input[$key]) && $input[$key] !== '' && $input[$key] !== [])) {
        continue;
      }
      $input[$key] = $value;
    }
    $form_state->setUserInput($input);
  }

  /**
   * @param array<string, mixed> $form
   *
   * @return array<string, mixed>
   */
  public static function stripInternalElements(array $form, FormStateInterface $form_state): array {
    unset($form['form_token'], $form['form_build_id'], $form['form_id']);
    foreach (['from', 'to'] as $name) {
      if (!empty($form[$name]['#id'])) {
        $form[$name]['#attributes']['id'] = $form[$name]['#id'];
      }
    }
    return $form;
  }

  /**
   * @return array<string, mixed>
   */
  private function locationId(string $name, ?TermInterface $term, string $id): array {
    return [
      '#type' => 'hidden',
      '#default_value' => $term ? (string) $term->id() : '',
      '#id' => $id,
      '#attributes' => [
        'id' => $id,
      ],
    ];
  }

  /**
   * Autocomplete over Location terms. The tid is submitted via the hidden twin.
   *
   * @return array<string, mixed>
   */
  private function locationLookup(string $name, mixed $title, ?TermInterface $term, bool $required, string $id, string $placeholder): array {
    return [
      '#type' => 'entity_autocomplete',
      '#title' => $title,
      '#title_display' => 'invisible',
      '#target_type' => 'taxonomy_term',
      '#selection_handler' => 'default:taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => ['location' => 'location'],
      ],
      '#default_value' => $term ? (string) $term->label() : NULL,
      '#process_default_value' => FALSE,
      '#required' => $required,
      '#id' => $id,
      '#attributes' => [
        'placeholder' => $placeholder,
        'data-banner-lookup' => $name === 'from_q' ? 'from' : 'to',
      ],
      '#theme_wrappers' => [],
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function hourSelect(string $name, string $default, string $id, bool $required): array {
    return [
      '#type' => 'select',
      '#title' => $name,
      '#title_display' => 'invisible',
      '#options' => $this->fleetCatalog->hourOptions(),
      '#default_value' => $default,
      '#required' => $required,
      '#id' => $id,
      '#theme_wrappers' => [],
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function dateField(string $name, string $default, string $min, string $id, bool $required): array {
    return [
      '#type' => 'textfield',
      '#title' => $name,
      '#title_display' => 'invisible',
      '#default_value' => $default,
      '#required' => $required,
      '#id' => $id,
      '#maxlength' => 10,
      '#size' => 12,
      '#attributes' => [
        'min' => $min,
        'autocomplete' => 'off',
        'inputmode' => 'none',
        'class' => ['form-date'],
        'data-lm-date' => $name,
      ],
      '#theme_wrappers' => [],
    ];
  }

}
