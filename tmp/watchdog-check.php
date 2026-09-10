<?php

$records = \Drupal::database()->queryRange(
  'SELECT wid, type, message, variables, severity FROM {watchdog} ORDER BY wid DESC',
  0,
  8
);
foreach ($records as $row) {
  $vars = unserialize($row->variables);
  $msg = $vars ? strtr($row->message, $vars) : $row->message;
  echo $row->wid . ' [' . $row->type . '] ' . $msg . "\n";
}
