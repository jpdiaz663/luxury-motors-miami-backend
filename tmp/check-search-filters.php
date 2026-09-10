<?php

use Drupal\taxonomy\TermInterface;
use Drupal\views\Views;

$storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$ids = $storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('vid', 'location')
  ->condition('status', 1)
  ->range(0, 1)
  ->execute();
$tid = (int) reset($ids);
$term = $storage->load($tid);
$label = $term instanceof TermInterface ? $term->label() : '';

$browse = Views::getView('vehicle_fleet');
$browse->setDisplay('page_1');
$browse->setExposedInput([]);
$browse->execute();
$browse_nids = $browse->result ? array_map(static fn($row) => (int) $row->nid, $browse->result) : [];

$request = \Drupal::request();
$request->query->set('pickup', '2026-09-12');
$request->query->set('return', '2026-09-15');
$request->query->set('from', (string) $tid);
$request->query->set('ptime', '10:00');
$request->query->set('rtime', '10:00');

$search = Views::getView('vehicle_fleet');
$search->setDisplay('page_1');
$search->setExposedInput([]);
$search->execute();
$search_nids = $search->result ? array_map(static fn($row) => (int) $row->nid, $search->result) : [];

echo json_encode([
  'location_tid' => $tid,
  'location_label' => $label,
  'browse_count' => count($browse_nids),
  'search_count' => count($search_nids),
  'browse_nids' => $browse_nids,
  'search_nids' => $search_nids,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
