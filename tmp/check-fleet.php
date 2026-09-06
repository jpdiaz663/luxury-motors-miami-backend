<?php

use Drupal\views\Views;

$view = Views::getView('vehicle_fleet');
echo $view ? "view=ok\n" : "view=missing\n";
if ($view) {
  $view->setDisplay('page_1');
  try {
    $view->preExecute();
    $view->execute();
    echo 'results=' . count($view->result) . "\n";
  }
  catch (Throwable $e) {
    echo 'execute_error=' . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
  }
}

$theme = \Drupal::theme()->getActiveTheme()->getName();
echo "theme=$theme\n";

$registry = \Drupal::service('theme.registry')->get();
echo isset($registry['views_view']) ? "views_view=ok\n" : "views_view=missing\n";
echo isset($registry['lm_vehicle_refine_form']) ? "refine_theme=ok\n" : "refine_theme=missing\n";

$nids = \Drupal::entityQuery('node')
  ->accessCheck(FALSE)
  ->condition('type', 'vehicle')
  ->condition('status', 1)
  ->count()
  ->execute();
echo "published_vehicles=$nids\n";

$module = \Drupal::moduleHandler()->moduleExists('lm_booking');
echo $module ? "lm_booking=enabled\n" : "lm_booking=disabled\n";
