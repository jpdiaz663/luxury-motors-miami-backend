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
   * @return array{url: string, alt: string}|null
   */
  public function fromMedia(?MediaInterface $media, ?string $style_id = 'hero_banner'): ?array {
    return $this->mediaImage($media, $style_id);
  }

  /**
   * @return array<string, mixed>
   */
  public function card(NodeInterface $node): array {
    $color = $this->plain($node, 'field_color');
    $chip = $this->colorChip($color) ?? '#303030';

    return [
      'title' => $node->label(),
      'url' => $node->toUrl()->toString(),
     // 'book_url' => $node->toUrl()->setOption('fragment', 'reserve')->toString(),
      'category' => $this->termName($node, 'field_category'),
      'color' => $color,
      'chip' => $chip,
      'glow' => $color ? $this->visibleOnDark($chip) : '#5c6370',
      'short_description' => $this->plain($node, 'field_short_description'),
      'daily_price' => $this->money($node, 'field_daily_price'),
      'image' => $this->mediaImage($this->referencedMedia($node, 'field_main_image')),
    ];
  }

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
    $raw = trim($color);
    if (preg_match('/#([0-9a-f]{3}|[0-9a-f]{6})\b/i', $raw, $match)) {
      $hex = $match[1];
      if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
      }
      return '#' . strtolower($hex);
    }

    $map = [
      'nero noctis' => '#121212',
      'nero' => '#1a1a1a',
      'black' => '#121212',
      'bianco' => '#f4f4f4',
      'white' => '#f4f4f4',
      'rosso corsa' => '#da291c',
      'rosso' => '#c41e3a',
      'red' => '#c41e3a',
      'giallo' => '#e3b505',
      'yellow' => '#e3b505',
      'arancio' => '#e85d04',
      'orange' => '#e85d04',
      'verde' => '#1f7a4d',
      'green' => '#1f7a4d',
      'azzurro' => '#4a90d9',
      'blu' => '#1e4d8c',
      'blue' => '#1e4d8c',
      'grigio' => '#8a8f94',
      'gray' => '#8a8f94',
      'grey' => '#8a8f94',
      'argento' => '#c0c4c8',
      'silver' => '#c0c4c8',
      'champagne' => '#e8d5a3',
      'gold' => '#d4af37',
      'oro' => '#d4af37',
      'bronze' => '#cd7f32',
      'beige' => '#d4c4a8',
      'brown' => '#6b4423',
      'viola' => '#5b2c6f',
      'purple' => '#5b2c6f',
      'pink' => '#d4789c',
    ];
    $key = strtolower($raw);
    if (isset($map[$key])) {
      return $map[$key];
    }
    uksort($map, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
    foreach ($map as $name => $hex) {
      if (str_contains($key, $name)) {
        return $hex;
      }
    }

    return '#303030';
  }

  /**
   * Keeps paint hue and lifts dark values so the hover border reads on carbone.
   */
  private function visibleOnDark(string $hex): string {
    $rgb = $this->hexToRgb($hex);
    if ($rgb === NULL) {
      return $hex;
    }
    [$hue, $sat, $light] = $this->rgbToHsl(...$rgb);
    if ($sat < 0.08) {
      return $light < 0.35 ? '#6e6e6e' : $hex;
    }
    if ($light < 0.38) {
      $light = 0.48;
    }

    return $this->hslToHex($hue, $sat, $light);
  }

  /**
   * @return array{0: int, 1: int, 2: int}|null
   */
  private function hexToRgb(string $hex): ?array {
    if (!preg_match('/^#([0-9a-f]{6})$/i', $hex, $match)) {
      return NULL;
    }
    $value = $match[1];

    return [
      (int) hexdec(substr($value, 0, 2)),
      (int) hexdec(substr($value, 2, 2)),
      (int) hexdec(substr($value, 4, 2)),
    ];
  }

  private function rgbToHex(int $red, int $green, int $blue): string {
    return sprintf('#%02x%02x%02x', $red, $green, $blue);
  }

  /**
   * @return array{0: float, 1: float, 2: float}
   */
  private function rgbToHsl(int $red, int $green, int $blue): array {
    $r = $red / 255;
    $g = $green / 255;
    $b = $blue / 255;
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $light = ($max + $min) / 2;
    $delta = $max - $min;
    if ($delta < 0.0001) {
      return [0.0, 0.0, $light];
    }
    $sat = $light > 0.5 ? $delta / (2 - $max - $min) : $delta / ($max + $min);
    $hue = match ($max) {
      $r => fmod(($g - $b) / $delta + ($g < $b ? 6 : 0), 6),
      $g => ($b - $r) / $delta + 2,
      default => ($r - $g) / $delta + 4,
    };

    return [$hue / 6, $sat, $light];
  }

  private function hslToHex(float $hue, float $sat, float $light): string {
    $hue = fmod($hue, 1);
    if ($sat <= 0) {
      $value = (int) round($light * 255);
      return $this->rgbToHex($value, $value, $value);
    }
    $q = $light < 0.5 ? $light * (1 + $sat) : $light + $sat - $light * $sat;
    $p = 2 * $light - $q;

    return $this->rgbToHex(
      $this->hueToChannel($p, $q, $hue + 1 / 3),
      $this->hueToChannel($p, $q, $hue),
      $this->hueToChannel($p, $q, $hue - 1 / 3),
    );
  }

  private function hueToChannel(float $p, float $q, float $t): int {
    $t = $t < 0 ? $t + 1 : $t;
    $t = $t > 1 ? $t - 1 : $t;
    $value = match (TRUE) {
      $t < 1 / 6 => $p + ($q - $p) * 6 * $t,
      $t < 1 / 2 => $q,
      $t < 2 / 3 => $p + ($q - $p) * (2 / 3 - $t) * 6,
      default => $p,
    };

    return (int) round($value * 255);
  }

}
