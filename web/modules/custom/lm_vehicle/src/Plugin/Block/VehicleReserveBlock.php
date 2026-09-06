<?php

declare(strict_types=1);

namespace Drupal\lm_vehicle\Plugin\Block;

use Drupal\node\NodeInterface;

/**
 * Reserve desk form and rates.
 *
 * @Block(
 *   id = "lm_vehicle_reserve",
 *   admin_label = @Translation("Vehicle reserve"),
 *   category = @Translation("Luxury Motors")
 * )
 */
final class VehicleReserveBlock extends VehicleSectionBlockBase {

  protected function buildVehicle(NodeInterface $node): array {
    return [
      '#theme' => 'lm_vehicle_reserve',
      '#vehicle' => $this->presenter->reserve($node),
    ];
  }

}
