<?php

declare(strict_types=1);

$lookup = \Drupal::service('lm_booking.booking_lookup');
$found = $lookup->find('LM-SJQH1D', 'iamputorraider09@gmail.com');
$miss = $lookup->find('LM-SJQH1D', 'nobody@example.com');
$prefix = $lookup->find('SJQH1D', 'iamputorraider09@gmail.com');
echo 'match: ' . ($found ? $found->id() : 'none') . PHP_EOL;
echo 'miss: ' . ($miss ? $miss->id() : 'none') . PHP_EOL;
echo 'body-only: ' . ($prefix ? $prefix->id() : 'none') . PHP_EOL;
if ($found) {
  $view = \Drupal::service('lm_booking.reservation_presenter')->build($found);
  echo 'ref: ' . $view['reference'] . PHP_EOL;
  echo 'status: ' . $view['status'] . PHP_EOL;
  echo 'cancellable: ' . ($view['cancellable'] ? 'yes' : 'no') . PHP_EOL;
  echo 'title: ' . ($view['trip']['title'] ?? '') . PHP_EOL;
}
