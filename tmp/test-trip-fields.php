<?php

declare(strict_types=1);

use Drupal\lm_vehicle\FleetCatalog;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

$defs = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'booking');
foreach ([
  'field_booking_pickup_location',
  'field_booking_dropoff_location',
  'field_booking_pickup_time',
  'field_booking_dropoff_time',
  'field_booking_place',
] as $name) {
  echo $name . ': ' . (isset($defs[$name]) ? 'yes' : 'NO') . PHP_EOL;
}

$terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
  'vid' => 'location',
  'status' => 1,
]);
$term = $terms ? reset($terms) : NULL;
if (!$term instanceof TermInterface) {
  echo "no location term\n";
  return;
}

$vehicles = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
  'type' => 'vehicle',
  'status' => 1,
]);
$vehicle = $vehicles ? reset($vehicles) : NULL;
if (!$vehicle instanceof NodeInterface) {
  echo "no vehicle\n";
  return;
}

$storage = \Drupal::entityTypeManager()->getStorage('node');
/** @var \Drupal\node\NodeInterface $booking */
$booking = $storage->create([
  'type' => 'booking',
  'title' => 'LM-TEST01',
  'status' => 1,
  'field_booking_code' => 'LM-TEST01',
  'field_booking_vehicle' => (int) $vehicle->id(),
  'field_booking_start' => '2026-12-20',
  'field_booking_end' => '2026-12-23',
  'field_booking_status' => 'confirmed',
  'field_booking_pickup_location' => (int) $term->id(),
  'field_booking_dropoff_location' => (int) $term->id(),
  'field_booking_pickup_time' => '10:00',
  'field_booking_dropoff_time' => '14:00',
  'field_booking_place' => 'Faena Hotel',
  'field_customer_name' => 'Trip Fields',
  'field_customer_email' => 'trip-fields@example.com',
]);
$booking->save();
$view = \Drupal::service('lm_booking.reservation_presenter')->build($booking);
echo 'pickup_place: ' . $view['trip']['pickup_place'] . PHP_EOL;
echo 'dropoff_place: ' . $view['trip']['dropoff_place'] . PHP_EOL;
echo 'pickup_when: ' . $view['trip']['pickup_when'] . PHP_EOL;
echo 'dropoff_when: ' . $view['trip']['dropoff_when'] . PHP_EOL;
$booking->delete();
echo 'cleaned' . PHP_EOL;
