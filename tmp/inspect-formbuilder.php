<?php

$t = file_get_contents('/app/web/core/lib/Drupal/Core/Render/Element.php');
$start = strpos($t, 'function setAttributes');
echo substr($t, $start, 800);

echo "\n--- FormBuilder 240-280 ---\n";
$fb = file_get_contents('/app/web/core/lib/Drupal/Core/Form/FormBuilder.php');
$lines = explode("\n", $fb);
echo implode("\n", array_slice($lines, 230, 50));

echo "\n--- FormBuilder 1288-1330 ---\n";
echo implode("\n", array_slice($lines, 1285, 50));
