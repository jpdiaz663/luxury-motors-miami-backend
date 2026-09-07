<?php

declare(strict_types=1);

namespace Drupal\lm_vehicle\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\lm_vehicle\VehiclePresenter;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shared vehicle-from-route block behavior.
 */
abstract class VehicleSectionBlockBase extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly VehiclePresenter $presenter,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('lm_vehicle.presenter'),
    );
  }

  public function build(): array {
    $node = $this->currentVehicle();
    return $node ? $this->buildVehicle($node) : [];
  }

  /**
   * @return array<string, mixed>
   */
  abstract protected function buildVehicle(NodeInterface $node): array;

  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($this->currentVehicle() instanceof NodeInterface)
      ->addCacheContexts(['route']);
  }

  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

  public function getCacheTags(): array {
    $node = $this->currentVehicle();
    $tags = parent::getCacheTags();
    return $node ? Cache::mergeTags($tags, $node->getCacheTags()) : $tags;
  }

  protected function currentVehicle(): ?NodeInterface {
    $node = $this->routeMatch->getParameter('node');
    if (!$node instanceof NodeInterface || $node->bundle() !== 'vehicle') {
      return NULL;
    }
    return $node;
  }

}
