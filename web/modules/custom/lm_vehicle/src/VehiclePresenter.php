<?php

declare(strict_types=1);

namespace Drupal\lm_vehicle;

use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;

/**
 * Builds structured arrays for vehicle detail sections.
 */
final class VehiclePresenter {

  public function __construct(
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * @return array<string, mixed>
   */
  public function hero(NodeInterface $node): array {
    $image = $this->mediaImage($this->referencedMedia($node, 'field_main_image'), 'hero_banner');

    return [
      'title' => $node->label(),
      'brand' => $this->termName($node, 'field_brand'),
      'category' => $this->termName($node, 'field_category'),
      'year' => $this->plain($node, 'field_year'),
      'lead' => $this->plain($node, 'field_short_description'),
      'status' => $this->listLabel($node, 'field_vehicle_status'),
      'daily_price' => $this->money($node, 'field_daily_price'),
      'image' => $image,
    ];
  }

  /**
   * @return array<string, mixed>
   */
  public function studio(NodeInterface $node): array {
    $slides = [];
    $main = $this->mediaImage($this->referencedMedia($node, 'field_main_image'));
    if ($main) {
      $slides[] = $main;
    }
    foreach ($this->referencedMediaList($node, 'field_gallery') as $media) {
      $image = $this->mediaImage($media);
      if ($image && !in_array($image['url'], array_column($slides, 'url'), TRUE)) {
        $slides[] = $image;
      }
    }

    return [
      'title' => $node->label(),
      'slides' => $slides,
    ];
  }

  /**
   * @return array<string, mixed>
   */
  public function story(NodeInterface $node): array {
    $features = [];
    if ($node->hasField('field_vehicle_features') && !$node->get('field_vehicle_features')->isEmpty()) {
      foreach ($node->get('field_vehicle_features')->referencedEntities() as $term) {
        $features[] = $term->label();
      }
    }

    $description = [];
    if ($node->hasField('field_description') && !$node->get('field_description')->isEmpty()) {
      $description = $node->get('field_description')->view(['label' => 'hidden']);
    }

    return [
      'title' => $node->label(),
      'brand' => $this->termName($node, 'field_brand'),
      'description' => $description,
      'features' => $features,
    ];
  }

  /**
   * @return array<string, mixed>
   */
  public function specs(NodeInterface $node): array {
    $color = $this->plain($node, 'field_color');

    return [
      'title' => $node->label(),
      'rows' => array_values(array_filter([
        $this->specRow('Brand', $this->termName($node, 'field_brand')),
        $this->specRow('Model', $this->plain($node, 'field_model')),
        $this->specRow('Year', $this->plain($node, 'field_year')),
        $this->specRow('Category', $this->termName($node, 'field_category')),
        $this->specRow('Engine', $this->plain($node, 'field_engine')),
        $this->specRow('Transmission', $this->listLabel($node, 'field_transmission')),
        $this->specRow('Fuel', $this->listLabel($node, 'field_fuel_type')),
        $this->specRow('Drive', $this->listLabel($node, 'field_drive_type')),
        $this->specRow('Seats', $this->plain($node, 'field_seats')),
        $this->specRow('Doors', $this->plain($node, 'field_doors')),
        $this->specRow('Mileage', $this->mileage($node)),
        [
          'label' => 'Color',
          'value' => $color,
          'chip' => $this->colorChip($color),
        ],
      ], static fn(array $row): bool => $row['value'] !== NULL && $row['value'] !== '')),
    ];
  }

  /**
   * @return list<array{label: string, value: string}>
   */
  public function restrictions(): array {
    return [
      ['label' => 'Driver', 'value' => 'Age 25+ with a valid license held for three years.'],
      ['label' => 'Insurance', 'value' => 'Full coverage required. Desk can bind a policy at pickup.'],
      ['label' => 'Mileage', 'value' => '100 miles included per day. Excess billed at desk rate.'],
      ['label' => 'Use', 'value' => 'Street use only. No track, valet, or third-party rental.'],
      ['label' => 'Desk', 'value' => 'Brickell night desk, daily 8am – 1am.'],
    ];
  }

  /**
   * @return array<string, mixed>
   */
  public function reserve(NodeInterface $node): array {
    return [
      'title' => $node->label(),
      'daily' => $this->money($node, 'field_daily_price'),
      'weekly' => $this->money($node, 'field_weekly_price'),
      'monthly' => $this->money($node, 'field_monthly_price'),
      'status' => $this->listLabel($node, 'field_vehicle_status'),
    ];
  }

  private function referencedMedia(NodeInterface $node, string $field_name): ?MediaInterface {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return NULL;
    }
    $entity = $node->get($field_name)->entity;
    return $entity instanceof MediaInterface ? $entity : NULL;
  }

  /**
   * @return list<MediaInterface>
   */
  private function referencedMediaList(NodeInterface $node, string $field_name): array {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return [];
    }
    $items = [];
    foreach ($node->get($field_name)->referencedEntities() as $entity) {
      if ($entity instanceof MediaInterface) {
        $items[] = $entity;
      }
    }
    return $items;
  }

  /**
   * @return array{url: string, alt: string}|null
   */
  private function mediaImage(?MediaInterface $media, ?string $style_id = NULL): ?array {
    if (!$media || !$media->hasField('field_media_image') || $media->get('field_media_image')->isEmpty()) {
      return NULL;
    }
    $item = $media->get('field_media_image');
    $file = $item->entity;
    if (!$file instanceof FileInterface) {
      return NULL;
    }
    $uri = $file->getFileUri();
    $url = $this->fileUrlGenerator->generateString($uri);
    if ($style_id) {
      $style = ImageStyle::load($style_id);
      if ($style) {
        $url = $style->buildUrl($uri);
      }
    }

    return [
      'url' => $url,
      'alt' => (string) ($item->alt ?? $media->label()),
    ];
  }

  private function termName(NodeInterface $node, string $field_name): ?string {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return NULL;
    }
    $term = $node->get($field_name)->entity;
    return $term ? (string) $term->label() : NULL;
  }

  private function plain(NodeInterface $node, string $field_name): ?string {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return NULL;
    }
    $value = $node->get($field_name)->value;
    return $value === NULL || $value === '' ? NULL : (string) $value;
  }

  private function listLabel(NodeInterface $node, string $field_name): ?string {
    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return NULL;
    }
    $values = $node->get($field_name)->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getSetting('allowed_values') ?? [];
    $key = (string) $node->get($field_name)->value;
    return isset($values[$key]) ? (string) $values[$key] : $key;
  }

  private function money(NodeInterface $node, string $field_name): ?string {
    $amount = $this->plain($node, $field_name);
    if ($amount === NULL) {
      return NULL;
    }
    $currency = $this->plain($node, 'field_currency') ?? 'USD';
    return $currency . ' ' . number_format((float) $amount, 0);
  }

  private function mileage(NodeInterface $node): ?string {
    $miles = $this->plain($node, 'field_mileage');
    if ($miles === NULL) {
      return NULL;
    }
    $suffix = '';
    if ($node->hasField('field_mileage')) {
      $suffix = (string) $node->get('field_mileage')->getFieldDefinition()->getSetting('suffix');
    }
    return number_format((int) $miles) . $suffix;
  }

  /**
   * @return array{label: string, value: string|null, chip?: string|null}
   */
  private function specRow(string $label, ?string $value): array {
    return [
      'label' => $label,
      'value' => $value,
    ];
  }

  private function colorChip(?string $color): ?string {
    if (!$color) {
      return NULL;
    }
    $map = [
      'nero noctis' => '#121212',
      'black' => '#121212',
      'white' => '#f4f4f4',
      'rosso' => '#da291c',
      'giallo' => '#d4af37',
    ];
    $key = strtolower(trim($color));
    return $map[$key] ?? '#303030';
  }

}
