<?php

declare(strict_types=1);

namespace Drupal\lm_vehicle\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\lm_vehicle\FleetCatalog;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Results-page refine. GET params match Views identifiers.
 *
 * Not attached to the fleet page. Keep for a later refine UI.
 */
final class VehicleRefineForm extends FormBase {

  public function __construct(
    private readonly FleetCatalog $fleetCatalog,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('lm_vehicle.fleet_catalog'),
    );
  }

  public function getFormId(): string {
    return 'lm_vehicle_refine_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $query = $this->fleetCatalog->currentQuery();
    $action = Url::fromUserInput(FleetCatalog::PATH)->toString();

    $form['#method'] = 'get';
    $form['#action'] = $action;
    $form['#theme'] = 'lm_vehicle_refine_form';
    $form['#theme_wrappers'] = [];
    $form['#after_build'][] = [VehicleSearchForm::class, 'stripInternalElements'];
    $form['#attributes'] = [
      'class' => ['fleet-refine'],
      'method' => 'get',
      'action' => $action,
      'accept-charset' => 'UTF-8',
    ];
    $form['#cache']['contexts'][] = 'url.query_args';
    $form['#cache']['tags'][] = 'taxonomy_term_list:brand';
    $form['#cache']['tags'][] = 'node_list:vehicle';

    foreach (['from', 'to', 'place', 'pickup', 'ptime', 'return', 'rtime', 'category'] as $name) {
      if ($query[$name] !== '') {
        $form[$name] = [
          '#type' => 'hidden',
          '#default_value' => $query[$name],
        ];
      }
    }

    $form['brand'] = $this->select('brand', $this->fleetCatalog->brandOptions(), $query['brand'], 'refine-brand');
    $form['color'] = $this->select('color', $this->fleetCatalog->colorOptions(), $query['color'], 'refine-color');
    $form['price'] = $this->select('price', $this->fleetCatalog->priceOptions(), $query['price'], 'refine-price');

    $form['submit'] = [
      '#type' => 'submit',
      '#name' => 'apply',
      '#value' => $this->t('Apply'),
      '#attributes' => [
        'class' => ['btn', 'btn--secondary', 'btn--arrow', 'fleet-refine-submit'],
      ],
      '#theme_wrappers' => [],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // GET form: the browser query string is the submission.
  }

  /**
   * @param array<int|string, string> $options
   *
   * @return array<string, mixed>
   */
  private function select(string $name, array $options, string $default, string $id): array {
    return [
      '#type' => 'select',
      '#title' => $name,
      '#title_display' => 'invisible',
      '#options' => $options,
      '#default_value' => $default,
      '#attributes' => ['id' => $id],
      '#theme_wrappers' => [],
    ];
  }

}
