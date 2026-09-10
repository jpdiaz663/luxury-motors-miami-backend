<?php

declare(strict_types=1);

namespace Drupal\lm_vehicle\Plugin\Block;

use Drupal\node\NodeInterface;

/**
 * Desk restrictions for this rental.
 *
 * @Block(
 *   id = "lm_vehicle_restrictions",
 *   admin_label = @Translation("Vehicle restrictions"),
 *   category = @Translation("Luxury Motors")
 * )
 */
final class VehicleRestrictionsBlock extends VehicleSectionBlockBase {

  protected function buildVehicle(NodeInterface $node): array {
    return [
      '#theme' => 'lm_vehicle_restrictions',
      '#restrictions' => $this->presenter->restrictions(),
      '#vehicle' => ['title' => $node->label()],
    ];
  }

}
