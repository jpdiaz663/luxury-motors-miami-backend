<?php

declare(strict_types=1);

use Drupal\node\NodeInterface;

$storage = \Drupal::entityTypeManager()->getStorage('node');
$ids = $storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'booking')
  ->range(0, 5)
  ->execute();

foreach ($storage->loadMultiple($ids) as $node) {
  if (!$node instanceof NodeInterface) {
    continue;
  }
  $code = $node->hasField('field_booking_code') ? (string) $node->get('field_booking_code')->value : $node->getTitle();
  $email = $node->hasField('field_customer_email') ? (string) $node->get('field_customer_email')->value : '';
  $status = $node->hasField('field_booking_status') ? (string) $node->get('field_booking_status')->value : '';
  echo $node->id() . "\t" . $code . "\t" . $email . "\t" . $status . PHP_EOL;
}
