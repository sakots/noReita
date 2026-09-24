<?php
// Shared bootstrap for every noReita entry point.

require_once __DIR__ . '/config_loader.inc.php';
require_once __DIR__ . '/request_info.inc.php';

const NOREITA_MIN_PHP_VERSION = '8.1.0';
const NOREITA_MIN_PHP_VERSION_ID = 80100;
const NOREITA_VERSION = '4.12.1';

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
    ApplicationErrorHandler::install($root . '/errorlog', $root . '/auditlog');

    try {
      Config::load($root);
    } catch (Throwable $e) {
      if ($e instanceof ConfigException) throw $e;
      throw new ConfigException('Configuration could not be loaded.', 0, $e);
    }
    $debug_ip = RequestInfo::clientIp();
    $debug_enabled = Config::bool('debug.enabled') && $debug_ip !== ''
      && in_array($debug_ip, Config::array('debug.allowed_ips'), true);
    ApplicationErrorHandler::setDebug($debug_enabled);
    ApplicationErrorHandler::configure(
      Config::int('error_log.retention_days'),
      Config::int('error_log.max_bytes'),
      Config::int('error_log.max_files_per_day'),
      Config::int('audit_log.retention_days'),
      Config::int('audit_log.max_bytes'),
      Config::int('audit_log.max_files_per_day')
    );
    date_default_timezone_set(Config::string('site.timezone'));
    self::$booted = true;
  }

  /** Render a safe configuration error page before configuration is available. */
  public static function renderConfigurationError(string $root, ConfigException $error): void {
    $debug = self::configurationDiagnosticsAllowed($root);
    ApplicationErrorHandler::setDebug($debug);
    $error_id = ApplicationErrorHandler::reportHttpError(500, 'Configuration loading failed.', $error);
    $english = self::$english;
    $title = $debug ? 'Configuration error' : ($english ? 'Internal Server Error' : '内部エラー');
    $body = $debug
      ? ApplicationErrorHandler::debugMessage($error_id, 'Configuration loading failed.', $error)
      : ApplicationErrorHandler::publicMessage($error_id, $english);
    if (!headers_sent()) {
      http_response_code(500);
      header('Content-Type: text/html; charset=UTF-8');
      header('Cache-Control: no-store, private');
    }
    echo '<!doctype html><html lang="' . ($english ? 'en' : 'ja') . '"><meta charset="UTF-8">'
      . '<meta name="robots" content="noindex"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
      . '<body><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1><pre>'
      . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</pre></body></html>';
    exit;
  }

  private static function configurationDiagnosticsAllowed(string $root): bool {
    $file = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'debug.local.php';
    if (!is_file($file) || !is_readable($file)) return false;
    try {
      $settings = require $file;
    } catch (Throwable) {
      return false;
    }
    if (!is_array($settings) || ($settings['enabled'] ?? false) !== true) return false;
    $allowed_ips = $settings['allowed_ips'] ?? null;
    $trusted_proxies = $settings['trusted_proxies'] ?? [];
    if (!self::validDiagnosticIps($allowed_ips, 64) || !self::validDiagnosticIps($trusted_proxies, 256)) return false;
    $ip = RequestInfo::clientIp(null, $trusted_proxies);
    return $ip !== '' && in_array($ip, $allowed_ips, true);
  }

  /** @param mixed $ips */
  private static function validDiagnosticIps($ips, int $maximum): bool {
    if (!is_array($ips) || count($ips) > $maximum
      || array_keys($ips) !== ($ips === [] ? [] : range(0, count($ips) - 1))) return false;
    foreach ($ips as $ip) {
      if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) return false;
    }
    return true;
  }

  public static function english(): bool {
    return self::$english;
  }
}
