<?php

declare(strict_types=1);

namespace Drupal\lm_vehicle\Plugin\views\filter;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\lm_vehicle\FleetCategoryRepresentatives;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Limits the fleet listing to one vehicle card per category.
 */
#[ViewsFilter('lm_vehicle_one_per_category')]
final class OnePerCategory extends FilterPluginBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly FleetCategoryRepresentatives $representatives,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('lm_vehicle.fleet_category_representatives'),
    );
  }

  public function adminSummary() {
    return (string) $this->t('One vehicle per category');
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

  public function acceptExposedInput($input) {
    return TRUE;
  }

  public function query(): void {
    if (!$this->query) {
      return;
    }
    $nids = $this->representatives->nids();
    $this->query->addWhere($this->options['group'], 'node_field_data.nid', $nids === [] ? [0] : $nids, 'IN');
  }

  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), [
      'url.query_args',
    ]);
  }

  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), [
      'node_list:vehicle',
      'node_list:booking',
    ]);
  }

}