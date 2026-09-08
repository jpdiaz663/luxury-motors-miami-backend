<?php

declare(strict_types=1);

namespace Drupal\lm_vehicle\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Power by JP block.
 *
 * @Block(
 *   id = "lm_vehicle_power_by_jp",
 *   admin_label = @Translation("Power by JP"),
 *   category = @Translation("Luxury Motors")
 * )
 */
final class PowerByJpBlock extends BlockBase {
  public function build(): array {
    return [
      '#theme' => 'lm_vehicle_power_by_jp',
      '#title' => $this->t('Power by <a href="https://juanpdiaz.top/" target="_blank">JP</a>'),
    ];
  }
}
