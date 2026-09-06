<?php

declare(strict_types=1);

namespace Drupal\lm_vehicle\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\lm_vehicle\FleetCatalog;
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

    $action = Url::fromUserInput(FleetCatalog::PATH)->toString();
    $form['#method'] = 'get';
    $form['#action'] = $action;
    $form['#theme'] = 'lm_vehicle_search_form';
    $form['#after_build'][] = [static::class, 'stripInternalElements'];
    $form['#attributes'] = [
      'class' => ['banner'],
      'data-banner' => TRUE,
      'method' => 'get',
      'action' => $action,
      'accept-charset' => 'UTF-8',
    ];
    $form['#show_place'] = $query['from'] === 'hotel' || $query['to'] === 'hotel';
    $form['#cache']['contexts'][] = 'url.query_args';
    $form['#attached']['library'][] = 'luxury_motors/vehicle_search';

    $form['from'] = $this->select('from', $this->fleetCatalog->pickupLocations(), $query['from'] !== '' ? $query['from'] : 'brickell', TRUE, 'banner-from');
    $form['to'] = $this->select('to', $this->fleetCatalog->deliveryLocations(), $query['to'] !== '' ? $query['to'] : 'same', FALSE, 'banner-to');
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
    $form['pickup'] = $this->dateTime('pickup', $pickup, $today, 'banner-pickup', 'date', TRUE);
    $form['ptime'] = $this->dateTime('ptime', $query['ptime'] !== '' ? $query['ptime'] : '10:00', NULL, 'banner-ptime', 'time', TRUE);
    $form['return'] = $this->dateTime('return', $return, $today, 'banner-return', 'date', TRUE);
    $form['rtime'] = $this->dateTime('rtime', $query['rtime'] !== '' ? $query['rtime'] : '10:00', NULL, 'banner-rtime', 'time', TRUE);

    /*$form['brand'] = $this->select('brand', $this->fleetCatalog->brandOptions(), $query['brand'], FALSE, 'banner-brand');
    $form['model'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Model'),
      '#title_display' => 'invisible',
      '#default_value' => $query['model'],
      '#attributes' => [
        'id' => 'banner-model',
        'placeholder' => 'Urus, Turbo S…',
      ],
      '#theme_wrappers' => [],
    ];
    $form['color'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Color'),
      '#title_display' => 'invisible',
      '#default_value' => $query['color'],
      '#attributes' => [
        'id' => 'banner-color',
        'placeholder' => 'Nero, white…',
      ],
      '#theme_wrappers' => [],
    ];*/
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

    $form['submit'] = [
      '#type' => 'submit',
      '#name' => '',
      '#value' => $this->t('Search'),
      '#attributes' => [
        'class' => ['btn', 'btn--primary', 'banner-submit'],
      ],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // GET form: the browser query string is the submission.
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
   * @param array<int|string, string> $options
   *
   * @return array<string, mixed>
   */
  private function select(string $name, array $options, string $default, bool $required, string $id): array {
    return [
      '#type' => 'select',
      '#title' => $name,
      '#title_display' => 'invisible',
      '#options' => $options,
      '#default_value' => $default,
      '#required' => $required,
      '#attributes' => ['id' => $id],
      '#theme_wrappers' => [],
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function dateTime(string $name, string $default, ?string $min, string $id, string $type, bool $required): array {
    $attributes = ['id' => $id];
    if ($min) {
      $attributes['min'] = $min;
    }

    $element_type = $type === 'date' ? 'date' : 'textfield';
    if ($type === 'time') {
      $attributes['type'] = 'time';
    }

    return [
      '#type' => $element_type,
      '#title' => $name,
      '#title_display' => 'invisible',
      '#default_value' => $default,
      '#required' => $required,
      '#attributes' => $attributes,
      '#theme_wrappers' => [],
    ];
  }

}
