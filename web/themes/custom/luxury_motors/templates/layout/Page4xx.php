<?php

namespace Drupal\luxury_motors\Plugin\Preprocess\Layout;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\preprocess\PreprocessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Preprocess for 4xx pages.
 *
 * @Preprocess(
 *   id = "luxury_motors.preprocess.page.4xx",
 *   hook = "page__4xx"
 * )
 */
class Page4xx extends PreprocessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritDoc}
   */
  public function preprocess(array $variables): array {
    $variables = parent::preprocess($variables);
  
    $theme_path = \Drupal::service('extension.list.theme')->getPath('luxury_motors');
    $variables['error_background_url'] = '/' . $theme_path . '/images/bg-v1.jpg';

    return $variables;
  }

}
