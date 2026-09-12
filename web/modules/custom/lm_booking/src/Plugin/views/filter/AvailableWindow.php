<?php

declare(strict_types=1);

namespace Drupal\lm_booking\Plugin\views\filter;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\lm_booking\AvailabilityManager;
use Drupal\lm_vehicle\FleetCatalog;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Excludes vehicles booked across the Search pickup/return dates.
 */
#[ViewsFilter('lm_booking_available_window')]
final class AvailableWindow extends FilterPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly AvailabilityManager $availability,
    private readonly FleetCatalog $catalog,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('lm_booking.availability'),
      $container->get('lm_vehicle.fleet_catalog'),
    );
  }

  public function adminSummary() {
    return (string) $this->t('Available in pickup/return window');
  }

  public function canExpose() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['value']['default'] = [];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state): void {
    $form['value'] = [];
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    return TRUE;
  }

  public function query(): void {
    if (!$this->query) {
      return;
    }
    $query = $this->catalog->currentQuery();
    $blocked = $this->availability->unavailableVehicleIds($query['pickup'], $query['return']);
    if ($blocked === []) {
      return;
    }
    $this->query->addWhere($this->options['group'], 'node_field_data.nid', $blocked, 'NOT IN');
  }

  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), [
      'url.query_args:pickup',
      'url.query_args:return',
      'url.query_args:date_from',
      'url.query_args:date_to',
    ]);
  }

  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), ['node_list:booking']);
  }

}
