<?php

declare(strict_types=1);

namespace Drupal\lm_vehicle\Plugin\Block;

use Drupal\node\NodeInterface;

/**
 * Editorial story and feature pills.
 *
 * @Block(
 *   id = "lm_vehicle_story",
 *   admin_label = @Translation("Vehicle story"),
 *   category = @Translation("Luxury Motors")
 * )
 */
final class VehicleStoryBlock extends VehicleSectionBlockBase {

  protected function buildVehicle(NodeInterface $node): array {
    return [
      '#theme' => 'lm_vehicle_story',
      '#vehicle' => $this->presenter->story($node),
    ];
  }

}
