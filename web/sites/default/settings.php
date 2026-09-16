<?php

/**
 * Load services definition file.
 */
$settings['container_yamls'][] = __DIR__ . '/services.yml';

/**
 * Include the Pantheon-specific settings file.
 *
 * n.b. The settings.pantheon.php file makes some changes
 *      that affect all environments that this site
 *      exists in. Always include this file, even in
 *      a local development environment, to ensure that
 *      the site settings remain consistent.
 */
include __DIR__ . "/settings.pantheon.php";

/**
 * Skipping permissions hardening will make scaffolding
 * work better, but will also raise a warning when you
 * install Drupal.
 *
 * https://www.drupal.org/project/drupal/issues/3091285
 */
// $settings['skip_permissions_hardening'] = TRUE;

/**
 * Optional project-root .env (KEY=VALUE). Does not override existing env.
 */
$lm_env_file = dirname(__DIR__, 3) . '/.env';
if (is_readable($lm_env_file)) {
  $lm_env_lines = file($lm_env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if (is_array($lm_env_lines)) {
    foreach ($lm_env_lines as $lm_env_line) {
      $lm_env_line = trim($lm_env_line);
      if ($lm_env_line === '' || str_starts_with($lm_env_line, '#') || !str_contains($lm_env_line, '=')) {
        continue;
      }
      [$lm_env_name, $lm_env_value] = explode('=', $lm_env_line, 2);
      $lm_env_name = trim($lm_env_name);
      $lm_env_value = trim($lm_env_value);
      if (
        (str_starts_with($lm_env_value, '"') && str_ends_with($lm_env_value, '"'))
        || (str_starts_with($lm_env_value, "'") && str_ends_with($lm_env_value, "'"))
      ) {
        $lm_env_value = substr($lm_env_value, 1, -1);
      }
      if ($lm_env_name !== '' && getenv($lm_env_name) === FALSE) {
        putenv($lm_env_name . '=' . $lm_env_value);
        $_ENV[$lm_env_name] = $lm_env_value;
      }
    }
  }
}

$settings['lm_notify'] = [
  'smtp_username' => getenv('LM_SMTP_USERNAME') ?: '',
  'smtp_password' => getenv('LM_SMTP_PASSWORD') ?: '',
];

$lm_notify_transport = getenv('LM_NOTIFY_TRANSPORT') ?: '';
if ($lm_notify_transport !== '') {
  $config['lm_notify.settings']['email']['transport'] = $lm_notify_transport;
}
$lm_notify_from = getenv('LM_NOTIFY_FROM') ?: '';
if ($lm_notify_from !== '') {
  $config['lm_notify.settings']['email']['from_address'] = $lm_notify_from;
}
$lm_smtp_host = getenv('LM_SMTP_HOST') ?: '';
if ($lm_smtp_host !== '') {
  $config['lm_notify.settings']['email']['host'] = $lm_smtp_host;
}
$lm_smtp_port = getenv('LM_SMTP_PORT') ?: '';
if ($lm_smtp_port !== '') {
  $config['lm_notify.settings']['email']['port'] = (int) $lm_smtp_port;
}
$lm_smtp_encryption = getenv('LM_SMTP_ENCRYPTION') ?: '';
if ($lm_smtp_encryption !== '') {
  $config['lm_notify.settings']['email']['encryption'] = $lm_smtp_encryption;
}

/**
 * If there is a local settings file, then include it
 */
$local_settings = __DIR__ . "/settings.local.php";
if (file_exists($local_settings)) {
  include $local_settings;
}
