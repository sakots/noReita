<?php
// error_handler.inc.php for noReita (C) sakots 2026 MIT License

const ERROR_HANDLER_INC_VER = 20260805;

final class ErrorLogStorage {
  public static function append(
    string $directory,
    string $date,
    string $line,
    int $max_bytes,
    int $max_files
  ): bool {
    if ($directory === '' || $max_bytes < 1 || $max_files < 1 || strlen($line) > $max_bytes) return false;

    for ($index = 0; $index < $max_files; $index++) {
      $suffix = $index === 0 ? '' : '.' . $index;
      $path = $directory . DIRECTORY_SEPARATOR . 'error-' . $date . $suffix . '.log';
      $handle = @fopen($path, 'c+b');
      if ($handle === false) continue;
      try {
        if (!@flock($handle, LOCK_EX)) continue;
        $stat = @fstat($handle);
        $size = is_array($stat) ? (int)($stat['size'] ?? 0) : $max_bytes;
        if ($size + strlen($line) > $max_bytes) continue;
        if (@fseek($handle, 0, SEEK_END) !== 0) continue;
        $remaining = $line;
        while ($remaining !== '') {
          $written = @fwrite($handle, $remaining);
          if ($written === false || $written === 0) return false;
          $remaining = substr($remaining, $written);
        }
        @fflush($handle);
        @chmod($path, 0600);
        return true;
      } finally {
        @flock($handle, LOCK_UN);
        @fclose($handle);
      }
    }
    return false;
  }

  public static function cleanup(
    string $directory,
    int $retention_days,
    int $limit = 20,
    ?int $now = null
  ): int {
    if ($directory === '' || $retention_days < 1 || $limit < 1 || !is_dir($directory)) return 0;
    $now = $now ?? time();
    $cutoff = $now - ($retention_days * 86400);
    $today = date('Ymd', $now);
    $removed = 0;
    $files = glob($directory . DIRECTORY_SEPARATOR . 'error-*.log') ?: [];
    sort($files, SORT_STRING);
    foreach ($files as $path) {
      $name = basename($path);
      if (!preg_match('/^error-(\d{8})(?:\.\d+)?\.log$/D', $name, $matches)
        || $matches[1] === $today
        || (int)(@filemtime($path) ?: $now) >= $cutoff) {
        continue;
      }
      $handle = @fopen($path, 'r');
      if ($handle === false) continue;
      $locked = @flock($handle, LOCK_EX | LOCK_NB);
      if ($locked && @unlink($path)) $removed++;
      if ($locked) @flock($handle, LOCK_UN);
      @fclose($handle);
      if ($removed >= $limit) break;
    }
    return $removed;
  }
}

final class ApplicationErrorHandler {
  private const DEFAULT_RETENTION_DAYS = 30;
  private const DEFAULT_MAX_BYTES = 5242880;
  private const DEFAULT_MAX_FILES_PER_DAY = 5;
  private static string $log_directory = '';
  private static bool $installed = false;
  private static bool $rendering = false;
  private static int $retention_days = self::DEFAULT_RETENTION_DAYS;
  private static int $max_bytes = self::DEFAULT_MAX_BYTES;
  private static int $max_files_per_day = self::DEFAULT_MAX_FILES_PER_DAY;

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

  public static function configure(int $retention_days, int $max_bytes, int $max_files_per_day): void {
    self::$retention_days = max(1, min(3650, $retention_days));
    self::$max_bytes = max(65536, min(104857600, $max_bytes));
    self::$max_files_per_day = max(1, min(100, $max_files_per_day));
    try {
      if (random_int(1, 100) === 1) self::cleanupLogs();
    } catch (Throwable $e) {
      // ログ整理に失敗してもアプリケーション処理は継続する。
    }
  }

  public static function cleanupLogs(?int $now = null, int $limit = 20): int {
    return ErrorLogStorage::cleanup(self::$log_directory, self::$retention_days, $limit, $now);
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
    $written = ErrorLogStorage::append(
      self::$log_directory,
      date('Ymd'),
      $line,
      self::$max_bytes,
      self::$max_files_per_day
    );
    if (!$written) {
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
