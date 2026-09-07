<?php

$node = \Drupal\node\Entity\Node::load(4);
if (!$node) {
  echo "node4=missing\n";
  return;
}
echo 'type=' . $node->bundle() . "\n";
echo 'title=' . $node->label() . "\n";
echo 'status=' . ($node->isPublished() ? 'published' : 'unpublished') . "\n";
$alias = \Drupal::service('path_alias.manager')->getAliasByPath('/node/4');
echo "alias=$alias\n";

$router = \Drupal::service('router.route_provider');
foreach (['view.vehicle_fleet.page_1'] as $name) {
  try {
    $route = $router->getRouteByName($name);
    echo "route=$name path=" . $route->getPath() . "\n";
  }
  catch (Throwable $e) {
    echo "route=$name missing: " . $e->getMessage() . "\n";
  }
}
