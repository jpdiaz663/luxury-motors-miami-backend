<?php

use Drupal\node\Entity\Node;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\views\Views;

$storage = \Drupal::entityTypeManager()->getStorage('path_alias');
$aliases = $storage->loadByProperties(['alias' => '/fleet']);
foreach ($aliases as $alias) {
  assert($alias instanceof PathAlias);
  echo 'alias_from=' . $alias->getPath() . ' -> /fleet-page' . "\n";
  $alias->setAlias('/fleet-page');
  $alias->save();
}

$node = Node::load(4);
if ($node && $node->bundle() === 'page' && $node->isPublished()) {
  $node->setUnpublished();
  $node->save();
  echo "node4=unpublished\n";
}

\Drupal::service('router.builder')->rebuild();

$view = Views::getView('vehicle_fleet');
$view->setDisplay('page_1');
try {
  $view->preExecute();
  $view->execute();
  echo 'results=' . count($view->result) . "\n";
}
catch (Throwable $e) {
  echo 'execute_error=' . $e->getMessage() . "\n";
}
