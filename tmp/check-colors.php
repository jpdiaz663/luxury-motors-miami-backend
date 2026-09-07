<?php

$nids = \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'vehicle')->execute();
$nodes = \Drupal\node\Entity\Node::loadMultiple($nids);
foreach ($nodes as $node) {
  $color = $node->hasField('field_color') ? (string) $node->get('field_color')->value : '';
  echo $node->id() . ' ' . $node->label() . ' color=' . $color . "\n";
}
