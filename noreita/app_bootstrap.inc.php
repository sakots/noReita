<?php
// Shared application startup helpers.

function app_bootstrap(string $root): bool {
  require_once $root . '/bootstrap.php';
  try {
    ApplicationBootstrap::boot($root);
  } catch (ConfigException $e) {
    http_response_code(500);
    die('Configuration error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
  }
  return ApplicationBootstrap::english();
}
