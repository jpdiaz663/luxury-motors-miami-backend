<?php

/**
 * @file
 * Creates taxonomies, media from private images, and one Vehicle node.
 */

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\taxonomy\Entity\Term;

function lm_term(string $vid, string $name): int {
  $ids = \Drupal::entityQuery('taxonomy_term')
    ->accessCheck(FALSE)
    ->condition('vid', $vid)
    ->condition('name', $name)
    ->range(0, 1)
    ->execute();
  if ($ids) {
    $id = (int) reset($ids);
    echo "Term exists: {$vid} / {$name} ({$id})\n";
    return $id;
  }
  $term = Term::create([
    'vid' => $vid,
    'name' => $name,
  ]);
  $term->save();
  echo "Created term: {$vid} / {$name} ({$term->id()})\n";
  return (int) $term->id();
}

function lm_media_from_disk(string $source, string $filename, string $alt): int {
  $existing = \Drupal::entityQuery('media')
    ->accessCheck(FALSE)
    ->condition('bundle', 'image')
    ->condition('name', $alt)
    ->range(0, 1)
    ->execute();
  if ($existing) {
    $id = (int) reset($existing);
    echo "Media exists: {$alt} ({$id})\n";
    return $id;
  }

  if (!is_readable($source)) {
    throw new \RuntimeException("Missing image: {$source}");
  }

  $file_system = \Drupal::service('file_system');
  $directory = 'public://vehicles';
  $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  $uri = $file_system->copy($source, $directory . '/' . $filename, FileExists::Replace);

  $file = File::create([
    'uri' => $uri,
    'status' => 1,
    'filename' => $filename,
  ]);
  $file->save();

  $media = Media::create([
    'bundle' => 'image',
    'name' => $alt,
    'uid' => 1,
    'status' => 1,
    'field_media_image' => [
      'target_id' => $file->id(),
      'alt' => $alt,
    ],
  ]);
  $media->save();
  echo "Created media: {$alt} ({$media->id()})\n";
  return (int) $media->id();
}

$brand = lm_term('brand', 'Lamborghini');
$category = lm_term('vehicle_category', 'SUV');
$features = [
  lm_term('vehicle_feature', 'Carbon ceramic brakes'),
  lm_term('vehicle_feature', 'Sport exhaust'),
  lm_term('vehicle_feature', 'Panoramic roof'),
  lm_term('vehicle_feature', 'Massage seats'),
  lm_term('vehicle_feature', 'Apple CarPlay'),
];

$images_root = DRUPAL_ROOT . '/sites/default/files/private/images';
$main = lm_media_from_disk($images_root . '/fleet/urus-front.jpg', 'urus-front.jpg', 'Lamborghini Urus Performante front');
$gallery = [
  lm_media_from_disk($images_root . '/hero-banner.jpg', 'urus-night.jpg', 'Urus night desk'),
  lm_media_from_disk($images_root . '/maintenance-bg.jpg', 'urus-hold.jpg', 'Urus after hours'),
];

$existing_node = \Drupal::entityQuery('node')
  ->accessCheck(FALSE)
  ->condition('type', 'vehicle')
  ->condition('title', 'Urus Performante')
  ->range(0, 1)
  ->execute();

if ($existing_node) {
  $nid = (int) reset($existing_node);
  echo "Vehicle exists: {$nid}\n";
}
else {
  $node = Node::create([
    'type' => 'vehicle',
    'title' => 'Urus Performante',
    'uid' => 1,
    'status' => 1,
    'field_brand' => $brand,
    'field_model' => 'Urus Performante',
    'field_year' => 2024,
    'field_category' => $category,
    'field_vehicle_status' => 'available',
    'field_short_description' => 'Brickell night desk. Twin-turbo V8, AWD, carbon ceramics.',
    'field_description' => [
      'value' => '<p>The Performante is the desk car for nights that start on Brickell and end past the causeway. Super-SUV stance, a 657-hp twin-turbo V8, and a cabin that still takes four adults without apology.</p><p>Pickup at the night desk. Delivery to South Beach, Key Biscayne, or the hangar on request.</p>',
      'format' => 'full_html',
    ],
    'field_transmission' => 'dual_clutch',
    'field_fuel_type' => 'gasoline',
    'field_drive_type' => 'awd',
    'field_engine' => '4.0L twin-turbo V8',
    'field_seats' => 5,
    'field_doors' => 5,
    'field_mileage' => 4200,
    'field_color' => 'Nero Noctis',
    'field_vehicle_features' => $features,
    'field_daily_price' => 1890,
    'field_weekly_price' => 11500,
    'field_monthly_price' => 38000,
    'field_currency' => 'USD',
    'field_main_image' => $main,
    'field_gallery' => $gallery,
  ]);
  $node->save();
  $nid = (int) $node->id();
  echo "Created vehicle node: {$nid}\n";

  $alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
  $existing_alias = $alias_storage->loadByProperties(['alias' => '/fleet/urus-performante']);
  if (!$existing_alias) {
    PathAlias::create([
      'path' => '/node/' . $nid,
      'alias' => '/fleet/urus-performante',
      'langcode' => 'en',
    ])->save();
    echo "Created alias /fleet/urus-performante\n";
  }
}

echo "Done. Open /fleet/urus-performante\n";
