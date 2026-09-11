<?php

$node = \Drupal\node\Entity\Node::load(2);
if (!$node) {
  echo "Node 2 missing\n";
  return;
}
echo "Title: " . $node->label() . "\n";
echo "Alias: " . \Drupal::service('path_alias.manager')->getAliasByPath('/node/2') . "\n";
$presenter = \Drupal::service('lm_vehicle.presenter');
$hero = $presenter->hero($node);
echo "Hero image: " . ($hero['image']['url'] ?? 'NONE') . "\n";
echo "Daily: " . ($hero['daily_price'] ?? 'NONE') . "\n";
$specs = $presenter->specs($node);
echo "Spec rows: " . count($specs['rows']) . "\n";
$studio = $presenter->studio($node);
echo "Slides: " . count($studio['slides']) . "\n";
echo "Brand: " . ($hero['brand'] ?? 'NONE') . "\n";
