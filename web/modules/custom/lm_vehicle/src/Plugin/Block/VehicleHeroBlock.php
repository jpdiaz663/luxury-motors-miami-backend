<?php

declare(strict_types=1);

namespace Drupal\lm_vehicle\Plugin\Block;

use Drupal\node\NodeInterface;

/**
 * Vehicle hero: media, title, lead, reserve CTA.
 *
 * @Block(
 *   id = "lm_vehicle_hero",
 *   admin_label = @Translation("Vehicle hero 2"),
 *   category = @Translation("Luxury Motors")
 * )
 */
final class VehicleHeroBlock extends VehicleSectionBlockBase {

  protected function buildVehicle(NodeInterface $node): array {
    return [
      '#theme' => 'lm_vehicle_hero',
      '#vehicle' => $this->presenter->hero($node),
    ];
  }

}
