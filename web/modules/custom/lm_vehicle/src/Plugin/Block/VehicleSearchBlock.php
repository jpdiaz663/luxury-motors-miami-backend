<?php

declare(strict_types=1);

namespace Drupal\lm_vehicle\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\lm_vehicle\Form\VehicleSearchForm;
use Drupal\lm_vehicle\VehiclePresenter;
use Drupal\media\MediaInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Fleet search hero. Editorial copy stays in the form template.
 *
 * @Block(
 *   id = "lm_vehicle_search",
 *   admin_label = @Translation("Vehicle search"),
 *   category = @Translation("Luxury Motors")
 * )
 */
final class VehicleSearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly FormBuilderInterface $formBuilder,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly VehiclePresenter $presenter,
    private readonly ThemeExtensionList $themeExtensionList,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
      $container->get('entity_type.manager'),
      $container->get('lm_vehicle.presenter'),
      $container->get('extension.list.theme'),
    );
  }

  public function defaultConfiguration(): array {
    return [
      'background_media' => NULL,
    ];
  }

  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);
    $media = NULL;
    $mid = $this->configuration['background_media'] ?? NULL;
    if ($mid) {
      $entity = $this->entityTypeManager->getStorage('media')->load((int) $mid);
      $media = $entity instanceof MediaInterface ? $entity : NULL;
    }

    $form['background_media'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'media',
      '#selection_handler' => 'default:media',
      '#selection_settings' => [
        'target_bundles' => ['image' => 'image'],
      ],
      '#title' => $this->t('Background image'),
      '#description' => $this->t('Full-bleed image behind the search banner. Leave empty to use the theme fallback.'),
      '#default_value' => $media,
    ];

    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state): void {
    parent::blockSubmit($form, $form_state);
    $value = $form_state->getValue('background_media');
    $this->configuration['background_media'] = $value ? (int) $value : NULL;
  }

  public function build(): array {
    return [
      '#theme' => 'lm_vehicle_search',
      '#form' => $this->formBuilder->getForm(VehicleSearchForm::class),
      '#background' => $this->background(),
    ];
  }

  public function getCacheContexts(): array {
    return ['url.query_args', 'url.path'];
  }

  public function getCacheTags(): array {
    $tags = [
      'taxonomy_term_list:brand',
      'taxonomy_term_list:vehicle_category',
    ];
    $mid = $this->configuration['background_media'] ?? NULL;
    if ($mid) {
      $tags[] = 'media:' . (int) $mid;
    }
    return $tags;
  }

  /**
   * @return array{url: string, alt: string}
   */
  private function background(): array {
    $mid = $this->configuration['background_media'] ?? NULL;
    if ($mid) {
      $media = $this->entityTypeManager->getStorage('media')->load((int) $mid);
      $image = $this->presenter->fromMedia($media instanceof MediaInterface ? $media : NULL);
      if ($image) {
        return $image;
      }
    }

    $path = $this->themeExtensionList->getPath('luxury_motors');
    return [
      'url' => '/' . $path . '/images/hero-banner.jpg',
      'alt' => (string) $this->t('Dark luxury coupe on empty asphalt at night'),
    ];
  }

}
