<?php
// Shared application startup helpers.

function app_bootstrap(string $root): bool {
  require_once $root . '/bootstrap.php';
  try {
    ApplicationBootstrap::boot($root);
  } catch (ConfigException $e) {
    ApplicationBootstrap::renderConfigurationError($root, $e);
  }
  return ApplicationBootstrap::english();
}
