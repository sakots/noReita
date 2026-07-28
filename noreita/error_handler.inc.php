<?php
// error_handler.inc.php for noReita (C) sakots 2026 MIT License

const ERROR_HANDLER_INC_VER = 20260728;

final class ApplicationErrorHandler {
  private static string $log_directory = '';
  private static bool $installed = false;
  private static bool $rendering = false;

  public static function install(string $log_directory): void {
    if (self::$installed) return;
    self::$log_directory = rtrim($log_directory, '/\\');
    self::prepareLogDirectory();

    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '0');
    ini_set('html_errors', '0');

    set_error_handler([self::class, 'handleError']);
    set_exception_handler([self::class, 'handleException']);
    register_shutdown_function([self::class, 'handleShutdown']);
    self::$installed = true;
  }

  public static function handleError(
    int $severity,
    string $message,
    string $file,
    int $line
  ): bool {
    if (!(error_reporting() & $severity)) return false;
    self::writeRecord([
      'type' => 'php-error',
      'severity' => $severity,
      'message' => $message,
      'file' => $file,
      'line' => $line,
    ]);
    if (in_array($severity, [E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
      throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return true;
  }

  public static function handleException(Throwable $exception): void {
    if (self::$rendering) return;
    self::$rendering = true;
    $error_id = self::reportThrowable($exception, 'uncaught-exception');
    self::renderPublicError($error_id);
  }

  public static function handleShutdown(): void {
    if (self::$rendering) return;
    $last_error = error_get_last();
    if (!is_array($last_error)
      || !in_array((int)($last_error['type'] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
      return;
    }
    self::$rendering = true;
    $error_id = self::writeRecord([
      'type' => 'fatal-error',
      'severity' => (int)$last_error['type'],
      'message' => (string)$last_error['message'],
      'file' => (string)$last_error['file'],
      'line' => (int)$last_error['line'],
    ]);
    self::renderPublicError($error_id);
  }

  public static function reportThrowable(Throwable $exception, string $type = 'application-error'): string {
    return self::writeRecord([
      'type' => $type,
      'exception' => get_class($exception),
      'message' => $exception->getMessage(),
      'code' => $exception->getCode(),
      'file' => $exception->getFile(),
      'line' => $exception->getLine(),
      'trace' => $exception->getTraceAsString(),
    ]);
  }

  public static function reportMessage(string $message, string $type = 'application-error'): string {
    return self::writeRecord([
      'type' => $type,
      'message' => $message,
    ]);
  }

  public static function publicMessage(string $error_id, bool $english): string {
    $date = substr($error_id, 0, 8);
    return $english
      ? 'An internal error occurred. Date: ' . $date
        . ' / Please inform the administrator of error ID: ' . $error_id
      : '内部エラーが発生しました。発生日: ' . $date
        . ' / 設置者へ次のエラーIDをお知らせください: ' . $error_id;
  }

  private static function writeRecord(array $details): string {
    $error_id = self::newErrorId();
    $record = [
      'timestamp' => date(DATE_ATOM),
      'date' => date('Ymd'),
      'error_id' => $error_id,
      'php_version' => PHP_VERSION,
      'request_method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
      'request_path' => self::requestPath(),
    ] + $details;
    $record = self::redactRecord($record);
    $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $line = (is_string($json) ? $json : '{"date":"' . date('Ymd') . '","error_id":"' . $error_id
      . '","message":"Failed to encode error details."}')
      . PHP_EOL;

    self::prepareLogDirectory();
    $log_file = self::$log_directory . DIRECTORY_SEPARATOR . 'error-' . date('Ymd') . '.log';
    $written = self::$log_directory !== ''
      && @file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX) !== false;
    if ($written) {
      @chmod($log_file, 0600);
    } else {
      error_log('[noReita ' . $error_id . '] ' . self::redact((string)($details['message'] ?? 'Internal error')));
    }
    return $error_id;
  }

  private static function prepareLogDirectory(): void {
    if (self::$log_directory === '') return;
    if (!is_dir(self::$log_directory)) @mkdir(self::$log_directory, 0700, true);
    if (is_dir(self::$log_directory)) @chmod(self::$log_directory, 0700);
  }

  private static function renderPublicError(string $error_id): void {
    if (!headers_sent()) {
      http_response_code(500);
      header('Content-Type: text/html; charset=UTF-8');
      header('Cache-Control: no-store, private');
    }
    $english = stripos((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 'ja') !== 0;
    $title = $english ? 'Internal Server Error' : '内部エラー';
    $message = self::publicMessage($error_id, $english);
    echo '<!doctype html><html lang="' . ($english ? 'en' : 'ja') . '"><meta charset="UTF-8">'
      . '<meta name="robots" content="noindex"><title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>'
      . '<body><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1><p>'
      . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></body></html>';
  }

  private static function requestPath(): string {
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $path = parse_url($uri, PHP_URL_PATH);
    return is_string($path) ? $path : '';
  }

  private static function newErrorId(): string {
    try {
      return date('YmdHis') . '-' . bin2hex(random_bytes(4));
    } catch (Throwable $e) {
      return date('YmdHis') . '-' . substr(hash('sha256', uniqid('', true)), 0, 8);
    }
  }

  private static function redactRecord(array $record): array {
    foreach ($record as $key => $value) {
      if (is_string($value)) $record[$key] = self::redact($value);
    }
    return $record;
  }

  private static function redact(string $value): string {
    $secrets = [];
    foreach (['admin_pass', 'second_pass'] as $name) {
      if (isset($GLOBALS[$name]) && is_string($GLOBALS[$name])) $secrets[] = $GLOBALS[$name];
    }
    foreach (['CRYPT_PASS'] as $name) {
      if (defined($name) && is_string(constant($name))) $secrets[] = constant($name);
    }
    if (isset($_SESSION) && is_array($_SESSION)) {
      foreach (['accessToken', 'sns_api_session_id', 'token'] as $name) {
        if (isset($_SESSION[$name]) && is_string($_SESSION[$name])) $secrets[] = $_SESSION[$name];
      }
    }
    foreach (array_unique($secrets) as $secret) {
      if (strlen($secret) >= 4) $value = str_replace($secret, '[REDACTED]', $value);
    }
    return preg_replace(
      '/((?:pass(?:word)?|token|secret|cookie|authorization|api[_-]?key)\\s*[=:]\\s*)[^\\s,;]+/iu',
      '$1[REDACTED]',
      $value
    ) ?? $value;
  }
}
