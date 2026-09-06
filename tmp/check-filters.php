<?php

use Drupal\Core\Form\FormState;
use Drupal\views\Views;

$view = Views::getView('vehicle_fleet');
$view->setDisplay('page_1');
$view->initHandlers();

foreach ($view->filter as $id => $filter) {
  echo $id . ' class=' . get_class($filter) . ' exposed=' . (!empty($filter->options['exposed']) ? '1' : '0') . ' grouped=' . (!empty($filter->options['is_grouped']) ? '1' : '0') . "\n";
  if (empty($filter->options['exposed'])) {
    continue;
  }
  $form = [];
  $form_state = new FormState();
  try {
    if (!empty($filter->options['is_grouped'])) {
      $filter->groupForm($form, $form_state);
    }
    else {
      $filter->buildExposedForm($form, $form_state);
    }
    echo "  ok keys=" . implode(',', array_keys($form)) . "\n";
  }
  catch (Throwable $e) {
    echo '  FAIL ' . $e->getMessage() . "\n";
  }
}
