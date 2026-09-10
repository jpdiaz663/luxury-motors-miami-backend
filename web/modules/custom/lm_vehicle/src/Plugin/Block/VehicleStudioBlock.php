<?php

declare(strict_types=1);

namespace Drupal\lm_vehicle\Plugin\Block;

use Drupal\node\NodeInterface;

/**
 * Vehicle photo studio / gallery.
 *
 * @Block(
 *   id = "lm_vehicle_studio",
 *   admin_label = @Translation("Vehicle studio"),
 *   category = @Translation("Luxury Motors")
 * )
 */
final class VehicleStudioBlock extends VehicleSectionBlockBase {

  protected function buildVehicle(NodeInterface $node): array {
    return [
      '#theme' => 'lm_vehicle_studio',
      '#vehicle' => $this->presenter->studio($node),
    ];
  }

}
