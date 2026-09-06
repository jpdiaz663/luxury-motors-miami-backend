<?php

/**
 * @file
 * Creates the Vehicle node type, taxonomies, fields, and displays.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\Role;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;

$entity_type_manager = \Drupal::entityTypeManager();

function lm_ensure_vocabulary(string $vid, string $name, string $description): void {
  if (!Vocabulary::load($vid)) {
    Vocabulary::create([
      'vid' => $vid,
      'name' => $name,
      'description' => $description,
      'new_revision' => FALSE,
    ])->save();
    echo "Created vocabulary: {$vid}\n";
  }
  else {
    echo "Vocabulary exists: {$vid}\n";
  }
}

function lm_ensure_storage(array $definition): void {
  $id = $definition['entity_type'] . '.' . $definition['field_name'];
  if (!FieldStorageConfig::load($id)) {
    FieldStorageConfig::create($definition)->save();
    echo "Created field storage: {$id}\n";
  }
  else {
    echo "Field storage exists: {$id}\n";
  }
}

function lm_ensure_field(array $definition): void {
  $id = $definition['entity_type'] . '.' . $definition['bundle'] . '.' . $definition['field_name'];
  if (!FieldConfig::load($id)) {
    FieldConfig::create($definition)->save();
    echo "Created field: {$id}\n";
  }
  else {
    echo "Field exists: {$id}\n";
  }
}

lm_ensure_vocabulary('brand', 'Brand', 'Vehicle manufacturers used for catalog filters and relationships.');
lm_ensure_vocabulary('vehicle_category', 'Vehicle category', 'Vehicle categories used for catalog filters and relationships.');
lm_ensure_vocabulary('vehicle_feature', 'Vehicle feature', 'Vehicle features and amenities used for catalog filters.');

if (!NodeType::load('vehicle')) {
  $type = NodeType::create([
    'type' => 'vehicle',
    'name' => 'Vehicle',
    'description' => 'Primary catalog entity for luxury vehicles available to rent.',
    'help' => 'Use taxonomies for brand, category, and features. Do not enter those values as free text.',
    'new_revision' => TRUE,
    'preview_mode' => 1,
    'display_submitted' => FALSE,
  ]);
  $type->setThirdPartySetting('menu_ui', 'available_menus', []);
  $type->setThirdPartySetting('menu_ui', 'parent', '');
  $type->save();
  echo "Created node type: vehicle\n";
}
else {
  echo "Node type exists: vehicle\n";
}

lm_ensure_storage([
  'field_name' => 'field_brand',
  'entity_type' => 'node',
  'type' => 'entity_reference',
  'settings' => ['target_type' => 'taxonomy_term'],
  'cardinality' => 1,
  'translatable' => TRUE,
]);
lm_ensure_field([
  'field_name' => 'field_brand',
  'entity_type' => 'node',
  'bundle' => 'vehicle',
  'label' => 'Brand',
  'description' => 'Manufacturer of the vehicle. Managed as taxonomy terms.',
  'required' => TRUE,
  'translatable' => FALSE,
  'settings' => [
    'handler' => 'default:taxonomy_term',
    'handler_settings' => [
      'target_bundles' => ['brand' => 'brand'],
      'sort' => ['field' => 'name', 'direction' => 'asc'],
      'auto_create' => FALSE,
      'auto_create_bundle' => '',
    ],
  ],
]);

lm_ensure_storage([
  'field_name' => 'field_model',
  'entity_type' => 'node',
  'type' => 'string',
  'settings' => [
    'max_length' => 255,
    'is_ascii' => FALSE,
    'case_sensitive' => FALSE,
  ],
  'cardinality' => 1,
  'translatable' => TRUE,
]);
lm_ensure_field([
  'field_name' => 'field_model',
  'entity_type' => 'node',
  'bundle' => 'vehicle',
  'label' => 'Model',
  'description' => 'Commercial model name (for example S-Class or Cullinan).',
  'required' => TRUE,
  'translatable' => FALSE,
]);

lm_ensure_storage([
  'field_name' => 'field_year',
  'entity_type' => 'node',
  'type' => 'integer',
  'settings' => [
    'unsigned' => FALSE,
    'size' => 'normal',
  ],
  'cardinality' => 1,
  'translatable' => TRUE,
]);
lm_ensure_field([
  'field_name' => 'field_year',
  'entity_type' => 'node',
  'bundle' => 'vehicle',
  'label' => 'Year',
  'description' => 'Model year of the vehicle.',
  'required' => TRUE,
  'translatable' => FALSE,
  'settings' => [
    'min' => 1990,
    'max' => 2040,
    'prefix' => '',
    'suffix' => '',
  ],
]);

lm_ensure_storage([
  'field_name' => 'field_category',
  'entity_type' => 'node',
  'type' => 'entity_reference',
  'settings' => ['target_type' => 'taxonomy_term'],
  'cardinality' => 1,
  'translatable' => TRUE,
]);
lm_ensure_field([
  'field_name' => 'field_category',
  'entity_type' => 'node',
  'bundle' => 'vehicle',
  'label' => 'Category',
  'description' => 'Catalog category (SUV, Sedan, Convertible, etc.). Managed as taxonomy terms.',
  'required' => TRUE,
  'translatable' => FALSE,
  'settings' => [
    'handler' => 'default:taxonomy_term',
    'handler_settings' => [
      'target_bundles' => ['vehicle_category' => 'vehicle_category'],
      'sort' => ['field' => 'name', 'direction' => 'asc'],
      'auto_create' => FALSE,
      'auto_create_bundle' => '',
    ],
  ],
]);

lm_ensure_storage([
  'field_name' => 'field_description',
  'entity_type' => 'node',
  'type' => 'text_long',
  'cardinality' => 1,
  'translatable' => TRUE,
]);
lm_ensure_field([
  'field_name' => 'field_description',
  'entity_type' => 'node',
  'bundle' => 'vehicle',
  'label' => 'Description',
  'description' => 'Full editorial description of the vehicle.',
  'required' => FALSE,
  'translatable' => TRUE,
  'settings' => [
    'allowed_formats' => ['full_html'],
  ],
]);

lm_ensure_storage([
  'field_name' => 'field_short_description',
  'entity_type' => 'node',
  'type' => 'string_long',
  'settings' => ['case_sensitive' => FALSE],
  'cardinality' => 1,
  'translatable' => TRUE,
]);
lm_ensure_field([
  'field_name' => 'field_short_description',
  'entity_type' => 'node',
  'bundle' => 'vehicle',
  'label' => 'Short description',
  'description' => 'Plain-text summary for cards, listings, and search snippets.',
  'required' => FALSE,
  'translatable' => TRUE,
]);

lm_ensure_storage([
  'field_name' => 'field_vehicle_features',
  'entity_type' => 'node',
  'type' => 'entity_reference',
  'settings' => ['target_type' => 'taxonomy_term'],
  'cardinality' => -1,
  'translatable' => TRUE,
]);
lm_ensure_field([
  'field_name' => 'field_vehicle_features',
  'entity_type' => 'node',
  'bundle' => 'vehicle',
  'label' => 'Vehicle features',
  'description' => 'Amenities and equipment used for filters. Managed as taxonomy terms.',
  'required' => FALSE,
  'translatable' => FALSE,
  'settings' => [
    'handler' => 'default:taxonomy_term',
    'handler_settings' => [
      'target_bundles' => ['vehicle_feature' => 'vehicle_feature'],
      'sort' => ['field' => 'name', 'direction' => 'asc'],
      'auto_create' => FALSE,
      'auto_create_bundle' => '',
    ],
  ],
]);

lm_ensure_storage([
  'field_name' => 'field_transmission',
  'entity_type' => 'node',
  'type' => 'list_string',
  'settings' => [
    'allowed_values' => [
      'automatic' => 'Automatic',
      'manual' => 'Manual',
      'dual_clutch' => 'Dual clutch',
      'cvt' => 'CVT',
    ],
    'allowed_values_function' => '',
  ],
  'cardinality' => 1,
  'translatable' => TRUE,
]);
lm_ensure_field([
  'field_name' => 'field_transmission',
  'entity_type' => 'node',
  'bundle' => 'vehicle',
  'label' => 'Transmission',
  'description' => 'Controlled list for catalog filters.',
  'required' => FALSE,
  'translatable' => FALSE,
]);

lm_ensure_storage([
  'field_name' => 'field_fuel_type',
  'entity_type' => 'node',
  'type' => 'list_string',
  'settings' => [
    'allowed_values' => [
      'gasoline' => 'Gasoline',
      'diesel' => 'Diesel',
      'hybrid' => 'Hybrid',
      'plug_in_hybrid' => 'Plug-in hybrid',
      'electric' => 'Electric',
    ],
    'allowed_values_function' => '',
  ],
  'cardinality' => 1,
  'translatable' => TRUE,
]);
lm_ensure_field([
  'field_name' => 'field_fuel_type',
  'entity_type' => 'node',
  'bundle' => 'vehicle',
  'label' => 'Fuel type',
  'description' => 'Controlled list for catalog filters.',
  'required' => FALSE,
  'translatable' => FALSE,
]);

lm_ensure_storage([
  'field_name' => 'field_seats',
  'entity_type' => 'node',
  'type' => 'integer',
  'settings' => [
    'unsigned' => TRUE,
    'size' => 'normal',
  ],
  'cardinality' => 1,
  'translatable' => TRUE,
]);
lm_ensure_field([
  'field_name' => 'field_seats',
  'entity_type' => 'node',
  'bundle' => 'vehicle',
  'label' => 'Seats',
  'description' => 'Number of passenger seats.',
  'required' => FALSE,
  'translatable' => FALSE,
  'settings' => [
    'min' => 1,
    'max' => 20,
    'prefix' => '',
    'suffix' => '',
  ],
]);

lm_ensure_storage([
  'field_name' => 'field_doors',
  'entity_type' => 'node',
  'type' => 'integer',
  'settings' => [
    'unsigned' => TRUE,
    'size' => 'normal',
  ],
  'cardinality' => 1,
  'translatable' => TRUE,
]);
lm_ensure_field([
  'field_name' => 'field_doors',
  'entity_type' => 'node',
  'bundle' => 'vehicle',
  'label' => 'Doors',
  'description' => 'Number of doors.',
  'required' => FALSE,
  'translatable' => FALSE,
  'settings' => [
    'min' => 2,
    'max' => 6,
    'prefix' => '',
    'suffix' => '',
  ],
]);

lm_ensure_storage([
  'field_name' => 'field_engine',
  'entity_type' => 'node',
  'type' => 'string',
  'settings' => [
    'max_length' => 255,
    'is_ascii' => FALSE,
    'case_sensitive' => FALSE,
  ],
  'cardinality' => 1,
  'translatable' => TRUE,
]);
lm_ensure_field([
  'field_name' => 'field_engine',
  'entity_type' => 'node',
  'bundle' => 'vehicle',
  'label' => 'Engine',
  'description' => 'Engine specification (for example 4.0L V8 twin-turbo).',
  'required' => FALSE,
  'translatable' => FALSE,
]);

lm_ensure_storage([
  'field_name' => 'field_drive_type',
  'entity_type' => 'node',
  'type' => 'list_string',
  'settings' => [
    'allowed_values' => [
      'fwd' => 'FWD',
      'rwd' => 'RWD',
      'awd' => 'AWD',
      '4wd' => '4WD',
    ],
    'allowed_values_function' => '',
  ],
  'cardinality' => 1,
  'translatable' => TRUE,
]);
lm_ensure_field([
  'field_name' => 'field_drive_type',
  'entity_type' => 'node',
  'bundle' => 'vehicle',
  'label' => 'Drive type',
  'description' => 'Controlled list for catalog filters.',
  'required' => FALSE,
  'translatable' => FALSE,
]);

lm_ensure_storage([
  'field_name' => 'field_mileage',
  'entity_type' => 'node',
  'type' => 'integer',
  'settings' => [
    'unsigned' => TRUE,
    'size' => 'normal',
  ],
  'cardinality' => 1,
  'translatable' => TRUE,
]);
lm_ensure_field([
  'field_name' => 'field_mileage',
  'entity_type' => 'node',
  'bundle' => 'vehicle',
  'label' => 'Mileage',
  'description' => 'Current odometer reading.',
  'required' => FALSE,
  'translatable' => FALSE,
  'settings' => [
    'min' => 0,
    'max' => NULL,
    'prefix' => '',
    'suffix' => ' km',
  ],
]);

lm_ensure_storage([
  'field_name' => 'field_color',
  'entity_type' => 'node',
  'type' => 'string',
  'settings' => [
    'max_length' => 128,
    'is_ascii' => FALSE,
    'case_sensitive' => FALSE,
  ],
  'cardinality' => 1,
  'translatable' => TRUE,
]);
lm_ensure_field([
  'field_name' => 'field_color',
  'entity_type' => 'node',
  'bundle' => 'vehicle',
  'label' => 'Color',
  'description' => 'Exterior color of this specific vehicle.',
  'required' => FALSE,
  'translatable' => FALSE,
]);

foreach ([
  'field_daily_price' => ['Daily price', 'Rental price for one day.'],
  'field_weekly_price' => ['Weekly price', 'Rental price for one week.'],
  'field_monthly_price' => ['Monthly price', 'Rental price for one month.'],
] as $price_field => [$label, $description]) {
  lm_ensure_storage([
    'field_name' => $price_field,
    'entity_type' => 'node',
    'type' => 'decimal',
    'settings' => [
      'precision' => 12,
      'scale' => 2,
    ],
    'cardinality' => 1,
    'translatable' => TRUE,
  ]);
  lm_ensure_field([
    'field_name' => $price_field,
    'entity_type' => 'node',
    'bundle' => 'vehicle',
    'label' => $label,
    'description' => $description,
    'required' => $price_field === 'field_daily_price',
    'translatable' => FALSE,
    'settings' => [
      'min' => 0,
      'max' => NULL,
      'prefix' => '',
      'suffix' => '',
    ],
  ]);
}

lm_ensure_storage([
  'field_name' => 'field_currency',
  'entity_type' => 'node',
  'type' => 'list_string',
  'settings' => [
    'allowed_values' => [
      'USD' => 'USD',
      'COP' => 'COP',
      'EUR' => 'EUR',
    ],
    'allowed_values_function' => '',
  ],
  'cardinality' => 1,
  'translatable' => TRUE,
]);
lm_ensure_field([
  'field_name' => 'field_currency',
  'entity_type' => 'node',
  'bundle' => 'vehicle',
  'label' => 'Currency',
  'description' => 'Currency for all rental prices on this vehicle.',
  'required' => TRUE,
  'translatable' => FALSE,
  'default_value' => [
    ['value' => 'USD'],
  ],
]);

$media_settings = [
  'handler' => 'default:media',
  'handler_settings' => [
    'target_bundles' => ['image' => 'image'],
    'sort' => ['field' => '_none', 'direction' => 'ASC'],
    'auto_create' => FALSE,
    'auto_create_bundle' => '',
  ],
];

lm_ensure_storage([
  'field_name' => 'field_main_image',
  'entity_type' => 'node',
  'type' => 'entity_reference',
  'settings' => ['target_type' => 'media'],
  'cardinality' => 1,
  'translatable' => TRUE,
]);
lm_ensure_field([
  'field_name' => 'field_main_image',
  'entity_type' => 'node',
  'bundle' => 'vehicle',
  'label' => 'Main image',
  'description' => 'Primary catalog image. Use a Media Image item.',
  'required' => TRUE,
  'translatable' => FALSE,
  'settings' => $media_settings,
]);

lm_ensure_storage([
  'field_name' => 'field_gallery',
  'entity_type' => 'node',
  'type' => 'entity_reference',
  'settings' => ['target_type' => 'media'],
  'cardinality' => -1,
  'translatable' => TRUE,
]);
lm_ensure_field([
  'field_name' => 'field_gallery',
  'entity_type' => 'node',
  'bundle' => 'vehicle',
  'label' => 'Gallery',
  'description' => 'Additional vehicle photos. Use Media Image items.',
  'required' => FALSE,
  'translatable' => FALSE,
  'settings' => $media_settings,
]);

lm_ensure_storage([
  'field_name' => 'field_vehicle_status',
  'entity_type' => 'node',
  'type' => 'list_string',
  'settings' => [
    'allowed_values' => [
      'available' => 'Available',
      'reserved' => 'Reserved',
      'rented' => 'Rented',
      'maintenance' => 'Maintenance',
      'unavailable' => 'Unavailable',
    ],
    'allowed_values_function' => '',
  ],
  'cardinality' => 1,
  'translatable' => TRUE,
]);
lm_ensure_field([
  'field_name' => 'field_vehicle_status',
  'entity_type' => 'node',
  'bundle' => 'vehicle',
  'label' => 'Vehicle status',
  'description' => 'Operational status used for availability filters.',
  'required' => TRUE,
  'translatable' => FALSE,
  'default_value' => [
    ['value' => 'available'],
  ],
]);

$form_display = EntityFormDisplay::load('node.vehicle.default');
if (!$form_display) {
  $form_display = EntityFormDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'vehicle',
    'mode' => 'default',
    'status' => TRUE,
  ]);
}

$form_components = [
  'title' => [
    'type' => 'string_textfield',
    'weight' => 0,
    'settings' => ['size' => 60, 'placeholder' => ''],
  ],
  'field_brand' => [
    'type' => 'entity_reference_autocomplete',
    'weight' => 1,
    'settings' => [
      'match_operator' => 'CONTAINS',
      'match_limit' => 10,
      'size' => 60,
      'placeholder' => '',
    ],
  ],
  'field_model' => [
    'type' => 'string_textfield',
    'weight' => 2,
    'settings' => ['size' => 60, 'placeholder' => ''],
  ],
  'field_year' => [
    'type' => 'number',
    'weight' => 3,
    'settings' => ['placeholder' => ''],
  ],
  'field_category' => [
    'type' => 'options_select',
    'weight' => 4,
    'settings' => [],
  ],
  'field_vehicle_status' => [
    'type' => 'options_select',
    'weight' => 5,
    'settings' => [],
  ],
  'field_short_description' => [
    'type' => 'string_textarea',
    'weight' => 6,
    'settings' => ['rows' => 3, 'placeholder' => ''],
  ],
  'field_description' => [
    'type' => 'text_textarea',
    'weight' => 7,
    'settings' => ['rows' => 8, 'placeholder' => ''],
  ],
  'field_vehicle_features' => [
    'type' => 'entity_reference_autocomplete',
    'weight' => 8,
    'settings' => [
      'match_operator' => 'CONTAINS',
      'match_limit' => 10,
      'size' => 60,
      'placeholder' => '',
    ],
  ],
  'field_transmission' => [
    'type' => 'options_select',
    'weight' => 9,
    'settings' => [],
  ],
  'field_fuel_type' => [
    'type' => 'options_select',
    'weight' => 10,
    'settings' => [],
  ],
  'field_drive_type' => [
    'type' => 'options_select',
    'weight' => 11,
    'settings' => [],
  ],
  'field_engine' => [
    'type' => 'string_textfield',
    'weight' => 12,
    'settings' => ['size' => 60, 'placeholder' => ''],
  ],
  'field_seats' => [
    'type' => 'number',
    'weight' => 13,
    'settings' => ['placeholder' => ''],
  ],
  'field_doors' => [
    'type' => 'number',
    'weight' => 14,
    'settings' => ['placeholder' => ''],
  ],
  'field_mileage' => [
    'type' => 'number',
    'weight' => 15,
    'settings' => ['placeholder' => ''],
  ],
  'field_color' => [
    'type' => 'string_textfield',
    'weight' => 16,
    'settings' => ['size' => 60, 'placeholder' => ''],
  ],
  'field_daily_price' => [
    'type' => 'number',
    'weight' => 17,
    'settings' => ['placeholder' => ''],
  ],
  'field_weekly_price' => [
    'type' => 'number',
    'weight' => 18,
    'settings' => ['placeholder' => ''],
  ],
  'field_monthly_price' => [
    'type' => 'number',
    'weight' => 19,
    'settings' => ['placeholder' => ''],
  ],
  'field_currency' => [
    'type' => 'options_select',
    'weight' => 20,
    'settings' => [],
  ],
  'field_main_image' => [
    'type' => 'media_library_widget',
    'weight' => 21,
    'settings' => ['media_types' => []],
  ],
  'field_gallery' => [
    'type' => 'media_library_widget',
    'weight' => 22,
    'settings' => ['media_types' => []],
  ],
  'uid' => [
    'type' => 'entity_reference_autocomplete',
    'weight' => 90,
    'settings' => [
      'match_operator' => 'CONTAINS',
      'match_limit' => 10,
      'size' => 60,
      'placeholder' => '',
    ],
  ],
  'created' => [
    'type' => 'datetime_timestamp',
    'weight' => 91,
    'settings' => [],
  ],
  'path' => [
    'type' => 'path',
    'weight' => 92,
    'settings' => [],
  ],
  'status' => [
    'type' => 'boolean_checkbox',
    'weight' => 93,
    'settings' => ['display_label' => TRUE],
  ],
];

foreach ($form_components as $name => $component) {
  $form_display->setComponent($name, $component + [
    'region' => 'content',
    'third_party_settings' => [],
  ]);
}
$form_display->removeComponent('promote');
$form_display->removeComponent('sticky');
$form_display->save();
echo "Saved form display: node.vehicle.default\n";

$view_display = EntityViewDisplay::load('node.vehicle.default');
if (!$view_display) {
  $view_display = EntityViewDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'vehicle',
    'mode' => 'default',
    'status' => TRUE,
  ]);
}

$view_order = [
  'field_main_image' => ['type' => 'entity_reference_entity_view', 'weight' => 0, 'label' => 'hidden', 'settings' => ['view_mode' => 'default', 'link' => FALSE]],
  'field_short_description' => ['type' => 'basic_string', 'weight' => 1, 'label' => 'hidden', 'settings' => []],
  'field_description' => ['type' => 'text_default', 'weight' => 2, 'label' => 'hidden', 'settings' => []],
  'field_brand' => ['type' => 'entity_reference_label', 'weight' => 3, 'label' => 'above', 'settings' => ['link' => TRUE]],
  'field_model' => ['type' => 'string', 'weight' => 4, 'label' => 'inline', 'settings' => ['link_to_entity' => FALSE]],
  'field_year' => ['type' => 'number_integer', 'weight' => 5, 'label' => 'inline', 'settings' => ['thousand_separator' => '', 'prefix_suffix' => FALSE]],
  'field_category' => ['type' => 'entity_reference_label', 'weight' => 6, 'label' => 'inline', 'settings' => ['link' => TRUE]],
  'field_vehicle_status' => ['type' => 'list_default', 'weight' => 7, 'label' => 'inline', 'settings' => []],
  'field_transmission' => ['type' => 'list_default', 'weight' => 8, 'label' => 'inline', 'settings' => []],
  'field_fuel_type' => ['type' => 'list_default', 'weight' => 9, 'label' => 'inline', 'settings' => []],
  'field_drive_type' => ['type' => 'list_default', 'weight' => 10, 'label' => 'inline', 'settings' => []],
  'field_engine' => ['type' => 'string', 'weight' => 11, 'label' => 'inline', 'settings' => ['link_to_entity' => FALSE]],
  'field_seats' => ['type' => 'number_integer', 'weight' => 12, 'label' => 'inline', 'settings' => ['thousand_separator' => '', 'prefix_suffix' => TRUE]],
  'field_doors' => ['type' => 'number_integer', 'weight' => 13, 'label' => 'inline', 'settings' => ['thousand_separator' => '', 'prefix_suffix' => TRUE]],
  'field_mileage' => ['type' => 'number_integer', 'weight' => 14, 'label' => 'inline', 'settings' => ['thousand_separator' => ',', 'prefix_suffix' => TRUE]],
  'field_color' => ['type' => 'string', 'weight' => 15, 'label' => 'inline', 'settings' => ['link_to_entity' => FALSE]],
  'field_vehicle_features' => ['type' => 'entity_reference_label', 'weight' => 16, 'label' => 'above', 'settings' => ['link' => TRUE]],
  'field_daily_price' => ['type' => 'number_decimal', 'weight' => 17, 'label' => 'inline', 'settings' => ['thousand_separator' => ',', 'decimal_separator' => '.', 'scale' => 2, 'prefix_suffix' => TRUE]],
  'field_weekly_price' => ['type' => 'number_decimal', 'weight' => 18, 'label' => 'inline', 'settings' => ['thousand_separator' => ',', 'decimal_separator' => '.', 'scale' => 2, 'prefix_suffix' => TRUE]],
  'field_monthly_price' => ['type' => 'number_decimal', 'weight' => 19, 'label' => 'inline', 'settings' => ['thousand_separator' => ',', 'decimal_separator' => '.', 'scale' => 2, 'prefix_suffix' => TRUE]],
  'field_currency' => ['type' => 'list_default', 'weight' => 20, 'label' => 'inline', 'settings' => []],
  'field_gallery' => ['type' => 'entity_reference_entity_view', 'weight' => 21, 'label' => 'hidden', 'settings' => ['view_mode' => 'default', 'link' => FALSE]],
];

foreach ($view_order as $name => $component) {
  $view_display->setComponent($name, $component + [
    'region' => 'content',
    'third_party_settings' => [],
  ]);
}
$view_display->removeComponent('links');
$view_display->save();
echo "Saved view display: node.vehicle.default\n";

$teaser = EntityViewDisplay::load('node.vehicle.teaser');
if (!$teaser) {
  $teaser = EntityViewDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'vehicle',
    'mode' => 'teaser',
    'status' => TRUE,
  ]);
}
$teaser_components = [
  'field_main_image' => ['type' => 'entity_reference_entity_view', 'weight' => 0, 'label' => 'hidden', 'settings' => ['view_mode' => 'media_library', 'link' => FALSE]],
  'field_short_description' => ['type' => 'basic_string', 'weight' => 1, 'label' => 'hidden', 'settings' => []],
  'field_daily_price' => ['type' => 'number_decimal', 'weight' => 2, 'label' => 'inline', 'settings' => ['thousand_separator' => ',', 'decimal_separator' => '.', 'scale' => 2, 'prefix_suffix' => TRUE]],
  'field_currency' => ['type' => 'list_default', 'weight' => 3, 'label' => 'hidden', 'settings' => []],
  'field_category' => ['type' => 'entity_reference_label', 'weight' => 4, 'label' => 'hidden', 'settings' => ['link' => FALSE]],
  'field_vehicle_status' => ['type' => 'list_default', 'weight' => 5, 'label' => 'hidden', 'settings' => []],
];
foreach ($teaser_components as $name => $component) {
  $teaser->setComponent($name, $component + [
    'region' => 'content',
    'third_party_settings' => [],
  ]);
}
foreach ([
  'field_brand', 'field_model', 'field_year', 'field_description',
  'field_vehicle_features', 'field_transmission', 'field_fuel_type',
  'field_seats', 'field_doors', 'field_engine', 'field_drive_type',
  'field_mileage', 'field_color', 'field_weekly_price', 'field_monthly_price',
  'field_gallery', 'links',
] as $hidden) {
  $teaser->removeComponent($hidden);
}
$teaser->save();
echo "Saved view display: node.vehicle.teaser\n";

$role = Role::load('content_editor');
if ($role) {
  $permissions = [
    'create vehicle content',
    'edit any vehicle content',
    'delete any vehicle content',
    'create terms in brand',
    'edit terms in brand',
    'delete terms in brand',
    'create terms in vehicle_category',
    'edit terms in vehicle_category',
    'delete terms in vehicle_category',
    'create terms in vehicle_feature',
    'edit terms in vehicle_feature',
    'delete terms in vehicle_feature',
  ];
  foreach ($permissions as $permission) {
    $role->grantPermission($permission);
  }
  $role->save();
  echo "Updated content_editor permissions\n";
}

echo "Vehicle catalog setup complete.\n";
