<?php
// Read-only public JSON API for same-origin React clients.

require_once __DIR__ . '/app_bootstrap.inc.php';
app_bootstrap(__DIR__);

defined('DB_FILE') or define('DB_FILE', __DIR__ . '/' . Config::string('database.name') . '.db');
defined('DB_PDO') or define('DB_PDO', 'sqlite:' . DB_FILE);

require_once __DIR__ . '/database.inc.php';
require_once __DIR__ . '/initialization.inc.php';
require_once __DIR__ . '/api.inc.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

/** @param array<string,mixed> $payload */
function api_response(array $payload, int $status = 200): void {
  http_response_code($status);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
}

/** Prepare the same storage required by index.php without starting a user session. */
function initialize_public_api(): void {
  $initializer = new ApplicationInitializer(
    DB_PDO, DB_FILE, __DIR__ . '/backup', __DIR__,
    [
      __DIR__ . '/' . Config::string('paths.images') => Config::int('permissions.public_directory'),
      __DIR__ . '/' . Config::string('paths.temporary') => Config::int('permissions.public_directory'),
      __DIR__ . '/' . Config::string('paths.thumbnails') => Config::int('permissions.public_directory'),
      __DIR__ . '/thumbnail' => Config::int('permissions.public_directory'),
      __DIR__ . '/session' => Config::int('permissions.private_directory'),
      __DIR__ . '/cache' => Config::int('permissions.private_directory'),
      __DIR__ . '/backup' => Config::int('permissions.private_directory'),
      __DIR__ . '/errorlog' => Config::int('permissions.private_directory'),
      __DIR__ . '/auditlog' => Config::int('permissions.private_directory'),
    ],
    0600,
    __DIR__ . '/' . Config::string('paths.temporary'),
  );
  $initializer->prepareDirectories();
  $initializer->migrateDatabase();
  $initializer->secureDatabaseFile();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
  header('Allow: GET');
  api_response(['error' => ['code' => 'method_not_allowed', 'message' => 'Only GET is supported.']], 405);
  exit;
}

try {
  initialize_public_api();
  api_response(PublicApi::dispatch(new BoardRepository(), $_GET));
} catch (PublicApiException $e) {
  api_response(['error' => ['code' => 'invalid_request', 'message' => $e->getMessage()]], $e->status());
} catch (Throwable $e) {
  $error_id = ApplicationErrorHandler::reportThrowable($e, 'public-api');
  api_response(['error' => ['code' => 'server_error', 'id' => $error_id]], 500);
}
