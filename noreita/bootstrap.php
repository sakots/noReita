<?php
// Shared bootstrap for every noReita entry point.

require_once __DIR__ . '/config_loader.inc.php';

const NOREITA_MIN_PHP_VERSION = '8.1.0';
const NOREITA_MIN_PHP_VERSION_ID = 80100;

if (PHP_VERSION_ID < NOREITA_MIN_PHP_VERSION_ID) {
  if (!headers_sent()) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
  }
  exit('PHP ' . NOREITA_MIN_PHP_VERSION . ' or higher is required. Current PHP version: ' . PHP_VERSION);
}

final class ApplicationBootstrap {
  private static bool $booted = false;
  private static bool $english = false;

  public static function boot(string $root): void {
    if (self::$booted) return;

    $languages = (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    $language = $languages !== '' ? explode(',', $languages)[0] : '';
    self::$english = stripos($language, 'ja') !== 0;

    $functions = $root . '/functions.php';
    $error_handler = $root . '/error_handler.inc.php';
    if (!is_file($functions) || !is_file($error_handler)) {
      throw new RuntimeException('Required bootstrap files are missing.');
    }
    require_once $functions;
    require_once $error_handler;
    ApplicationErrorHandler::install($root . '/errorlog');

    Config::load($root);
    ApplicationErrorHandler::configure(
      Config::int('error_log.retention_days'),
      Config::int('error_log.max_bytes'),
      Config::int('error_log.max_files_per_day')
    );
    date_default_timezone_set(Config::string('site.timezone'));
    self::$booted = true;
  }

  public static function english(): bool {
    return self::$english;
  }
}
