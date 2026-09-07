<?php

declare(strict_types=1);

namespace Drupal\luxury_motors\Plugin\preprocessors;

use Drupal\block_content\BlockContentInterface;
use Drupal\lm_vehicle\VehiclePresenter;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\preprocessors\PreprocessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Prepares featured-vehicle slider variables for the custom block type.
 */
final class FeaturedVehiculesPreprocessor extends PreprocessorPluginBase {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly VehiclePresenter $presenter,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('lm_vehicle.presenter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(array &$variables, string $hook, array $info): void {
    $block = $this->blockContent($variables);
    
    if (!$block instanceof BlockContentInterface || $block->bundle() !== 'hero_slider_featured_cars') {
      return;
    }

    $variables['slides'] = $this->slides($block);
  }

  /**
   * @param array<string, mixed> $variables
   */
  private function blockContent(array $variables): ?BlockContentInterface {
    $candidates = [
      $variables['content']['#block_content'] ?? NULL,
      $variables['elements']['content']['#block_content'] ?? NULL,
    ];
    foreach ($candidates as $candidate) {
      if ($candidate instanceof BlockContentInterface) {
        return $candidate;
      }
    }
    return NULL;
  }

  /**
   * @return list<array<string, mixed>>
   */
  private function slides(BlockContentInterface $block): array {
    if (!$block->hasField('field_featured_vehicule_sliders') || $block->get('field_featured_vehicule_sliders')->isEmpty()) {
      return [];
    }

    $slides = [];
    foreach ($block->get('field_featured_vehicule_sliders')->referencedEntities() as $paragraph) {
      if (!$paragraph instanceof ParagraphInterface) {
        continue;
      }
      $vehicle = $paragraph->get('field_featured_vehicule')->entity ?? NULL;
      if (!$vehicle instanceof NodeInterface) {
        continue;
      }

      $hero = $this->presenter->hero($vehicle);
      $slides[] = [
        'headline' => (string) ($paragraph->get('field_headline')->value ?? ''),
        'description' => (string) ($paragraph->get('field_description')->value ?? ''),
        'cta_label' => (string) ($paragraph->get('field_cta_label')->value ?? ''),
        'image' => $hero['image'] ?? NULL,
        'vehicle' => $this->presenter->card($vehicle),
      ];
    }

    return $slides;
  }

}
