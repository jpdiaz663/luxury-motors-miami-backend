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
    $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
    $pickup = $query['pickup'] !== '' ? $query['pickup'] : (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');
    $return = $query['return'] !== '' ? $query['return'] : (new \DateTimeImmutable('tomorrow +3 days'))->format('Y-m-d');
    $from_term = $this->fleetCatalog->locationTerm($query['from']);
    $to_term = $this->fleetCatalog->locationTerm($query['to']);

    $action = Url::fromUserInput(FleetCatalog::PATH)->toString();
    $form['#method'] = 'get';
    $form['#action'] = $action;
    $form['#theme'] = 'lm_vehicle_search_form';
    $form['#theme_wrappers'] = [];
    $form['#after_build'][] = [static::class, 'stripInternalElements'];
    $need_dates = $this->needsDateConfirmation($query);
    $form['#need_dates'] = $need_dates;
    $form['#attributes'] = [
      'class' => array_values(array_filter(['banner', $need_dates ? 'is-need-dates' : ''])),
      'data-banner' => TRUE,
      'method' => 'get',
      'action' => $action,
      'accept-charset' => 'UTF-8',
    ];
    $form['#show_place'] = $this->fleetCatalog->locationNeedsPlace($query['from'])
      || $this->fleetCatalog->locationNeedsPlace($query['to']);
    $form['#reset_url'] = $action;
    $form['#cache']['contexts'][] = 'url.query_args';
    $form['#cache']['contexts'][] = 'url.path';
    $form['#cache']['tags'][] = 'taxonomy_term_list:location';
    $form['#attached']['library'][] = 'luxury_motors/vehicle_search';
    $form['#attached']['drupalSettings']['lmVehicleSearch'] = [
      'requiresPlace' => $this->fleetCatalog->locationPlaceTids(),
      'resetUrl' => $action,
    ];

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
    $form['pickup'] = $this->dateField('pickup', $pickup, $today, 'banner-pickup', TRUE);
    $form['ptime'] = $this->hourSelect('ptime', $this->fleetCatalog->hourValue($query['ptime']), 'banner-ptime', TRUE);
    $form['return'] = $this->dateField('return', $return, $today, 'banner-return', TRUE);
    $form['rtime'] = $this->hourSelect('rtime', $this->fleetCatalog->hourValue($query['rtime']), 'banner-rtime', TRUE);

    $form['category'] = [
      '#type' => 'hidden',
      '#default_value' => $query['category'],
    ];
    foreach (['brand', 'color', 'price'] as $name) {
      if ($query[$name] !== '') {
        $form[$name] = [
          '#type' => 'hidden',
          '#default_value' => $query[$name],
        ];
      }
    }

    $form['#filtered'] = $this->fleetCatalog->hasFilters();
    
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // GET form: the browser query string is the submission.
  }

  /**
   * @param array<string, string> $query
   */
  private function needsDateConfirmation(array $query): bool {
    $path = '/' . trim((string) $this->getRequest()->getPathInfo(), '/');
    $on_fleet = $path === FleetCatalog::PATH || str_ends_with($path, FleetCatalog::PATH);

    return $on_fleet && $query['category'] !== '' && $query['pickup'] === '' && $query['return'] === '';
  }

  /**
   * @param array<string, mixed> $form
   *
   * @return array<string, mixed>
   */
  public static function stripInternalElements(array $form, FormStateInterface $form_state): array {
    unset($form['form_token'], $form['form_build_id'], $form['form_id']);
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
      '#default_value' => $term,
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
      '#type' => 'date',
      '#title' => $name,
      '#title_display' => 'invisible',
      '#default_value' => $default,
      '#required' => $required,
      '#id' => $id,
      '#attributes' => [
        'min' => $min,
      ],
      '#theme_wrappers' => [],
    ];
  }

}
