<?php

declare(strict_types=1);

namespace Drupal\lm_vehicle\Plugin\Block;

use Drupal\node\NodeInterface;

/**
 * Specifications grid.
 *
 * @Block(
 *   id = "lm_vehicle_specs",
 *   admin_label = @Translation("Vehicle specifications"),
 *   category = @Translation("Luxury Motors")
 * )
 */
final class VehicleSpecsBlock extends VehicleSectionBlockBase {

  protected function buildVehicle(NodeInterface $node): array {
    return [
      '#theme' => 'lm_vehicle_specs',
      '#vehicle' => $this->presenter->specs($node),
    ];
  }

}
