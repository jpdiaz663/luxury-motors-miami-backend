<?php

declare(strict_types=1);

namespace Drupal\lm_vehicle\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\lm_vehicle\PopularCollection;
use Drupal\lm_vehicle\VehiclePresenter;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Most-reserved vehicles rail. Editorial kicker and heading stay in block config.
 *
 * @Block(
 *   id = "lm_vehicle_popular_collection",
 *   admin_label = @Translation("Popular collection"),
 *   category = @Translation("Luxury Motors")
 * )
 */
final class PopularCollectionBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly PopularCollection $popularCollection,
    private readonly VehiclePresenter $presenter,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('lm_vehicle.popular_collection'),
      $container->get('lm_vehicle.presenter'),
      $container->get('entity_type.manager'),
    );
  }

  public function defaultConfiguration(): array {
    return [
      'general_title' => 'Most searched',
      'heading' => 'The cars people ask for first',
      'limit' => 8,
    ];
  }

  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);

    $form['general_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('General title'),
      '#description' => $this->t('Small kicker above the heading.'),
      '#default_value' => $this->configuration['general_title'] ?? '',
      '#maxlength' => 255,
    ];

    $form['heading'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Heading'),
      '#description' => $this->t('Main title for the popular collection.'),
      '#default_value' => $this->configuration['heading'] ?? '',
      '#maxlength' => 255,
      '#required' => TRUE,
    ];

    $form['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of vehicles'),
      '#description' => $this->t('The section is hidden unless more than 3 reserved vehicles exist in the last 90 days.'),
      '#default_value' => (int) ($this->configuration['limit'] ?? 8),
      '#min' => PopularCollection::MIN_VEHICLES,
      '#max' => 12,
    ];

    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state): void {
    parent::blockSubmit($form, $form_state);
    $this->configuration['general_title'] = trim((string) $form_state->getValue('general_title'));
    $this->configuration['heading'] = trim((string) $form_state->getValue('heading'));
    $this->configuration['limit'] = (int) $form_state->getValue('limit');
  }

  public function build(): array {
    $vehicles = $this->vehicles(debug: true);
    if (count($vehicles) < PopularCollection::MIN_VEHICLES) {
      return [];
    }

    return [
      '#theme' => 'lm_vehicle_popular_collection',
      '#general_title' => $this->configuration['general_title'] ?? '',
      '#heading' => $this->configuration['heading'] ?? '',
      '#vehicles' => $vehicles,
      '#attached' => [
        'library' => ['luxury_motors/popular_collection'],
      ],
    ];
  }

  public function getCacheMaxAge(): int {
    return 86400;
  }

  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), [
      'node_list:vehicle',
      'node_list:booking',
    ]);
  }

  /**
   * @return list<array<string, mixed>>
   */
  private function vehicles(bool $debug = false): array {

    if ($debug) {
      $ranked = [2 => 1, 3 => 1];
      $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple([2,3]);
    }else {
      $ranked = $this->popularCollection->rankedVehicleIds((int) ($this->configuration['limit'] ?? 8));
      if ($ranked === []) {
        return [];  
      }
      $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple(array_keys($ranked));
    }

  
    $vehicles = [];
    foreach ($ranked as $nid => $count) {
      $node = $nodes[$nid] ?? NULL;
      if (!$node instanceof NodeInterface || !$node->access('view')) {
        continue;
      }
      $card = $this->presenter->card($node);
      $card['reservation_count'] = $count;
      $vehicles[] = $card;
    }

    return $vehicles;
  }

}
