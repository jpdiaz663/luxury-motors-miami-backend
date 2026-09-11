<?php

declare(strict_types=1);

namespace Drupal\lm_booking\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\lm_booking\FareInclusions;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mock "included in the rate" copy for the fleet catalog.
 *
 * @Block(
 *   id = "lm_booking_fare_inclusions",
 *   admin_label = @Translation("Fare inclusions"),
 *   category = @Translation("Luxury Motors")
 * )
 */
final class FareInclusionsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly FareInclusions $fareInclusions,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('lm_booking.fare_inclusions'),
    );
  }

  public function build(): array {
    return [
      '#theme' => 'lm_booking_fare_inclusions',
      '#heading' => $this->t('Artículos incluidos en la tarifa'),
      '#items' => $this->fareInclusions->items(),
      '#notes' => $this->fareInclusions->notes(),
      '#attached' => [
        'library' => ['luxury_motors/fare_inclusions'],
      ],
    ];
  }

}
