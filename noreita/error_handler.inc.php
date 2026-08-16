<?php
// error_handler.inc.php for noReita (C) sakots 2026 MIT License

const ERROR_HANDLER_INC_VER = 20260817;

final class ErrorLogStorage {
  public static function append(
    string $directory,
    string $date,
    string $line,
    int $max_bytes,
    int $max_files,
    string $prefix = 'error'
  ): bool {
    if ($directory === '' || $max_bytes < 1 || $max_files < 1 || strlen($line) > $max_bytes
      || preg_match('/\A[a-z][a-z0-9-]{0,31}\z/D', $prefix) !== 1) return false;

    for ($index = 0; $index < $max_files; $index++) {
      $suffix = $index === 0 ? '' : '.' . $index;
      $path = $directory . DIRECTORY_SEPARATOR . $prefix . '-' . $date . $suffix . '.log';
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
    ?int $now = null,
    string $prefix = 'error'
  ): int {
    if ($directory === '' || $retention_days < 1 || $limit < 1 || !is_dir($directory)
      || preg_match('/\A[a-z][a-z0-9-]{0,31}\z/D', $prefix) !== 1) return 0;
    $now = $now ?? time();
    $cutoff = $now - ($retention_days * 86400);
    $today = date('Ymd', $now);
    $removed = 0;
    $files = glob($directory . DIRECTORY_SEPARATOR . $prefix . '-*.log') ?: [];
    sort($files, SORT_STRING);
    foreach ($files as $path) {
      $name = basename($path);
      if (!preg_match('/^' . preg_quote($prefix, '/') . '-(\d{8})(?:\.\d+)?\.log$/D', $name, $matches)
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

/** Read the application's JSON Lines logs without exposing their filesystem paths. */
final class ErrorLogReader {
  private const MAX_RECORDS = 100;
  private const MAX_FIELD_LENGTH = 4000;

  /** @return array<int,string> */
  public static function availableDates(string $directory, string $prefix = 'error'): array {
    if (!is_dir($directory) || !self::isValidPrefix($prefix)) return [];
    $dates = [];
    $entries = scandir($directory);
    if ($entries === false) return [];
    foreach ($entries as $name) {
      if (!preg_match('/^' . preg_quote($prefix, '/') . '-(\d{8})(?:\.\d+)?\.log$/D', $name, $matches)
        || !self::isValidDate($matches[1])) {
        continue;
      }
      $path = $directory . DIRECTORY_SEPARATOR . $name;
      if (is_link($path) || !is_file($path) || !is_readable($path)) continue;
      $dates[$matches[1]] = true;
    }
    $dates = array_map(static fn($date): string => (string)$date, array_keys($dates));
    rsort($dates, SORT_STRING);
    return $dates;
  }

  /**
   * @return array{records:array<int,array<string,int|string>>,total:int,types:array<int,string>}
   */
  public static function read(
    string $directory,
    string $date,
    string $type = 'all',
    string $status_group = 'all',
    int $limit = self::MAX_RECORDS,
    string $prefix = 'error'
  ): array {
    if (!self::isValidDate($date) || !in_array($status_group, ['all', '4xx', '5xx'], true)
      || ($type !== 'all' && !self::isValidType($type)) || !self::isValidPrefix($prefix)) {
      return ['records' => [], 'total' => 0, 'types' => []];
    }
    $limit = max(1, min(self::MAX_RECORDS, $limit));
    $records = [];
    $types = [];
    $total = 0;

    foreach (self::filesForDate($directory, $date, $prefix) as $path) {
      $handle = @fopen($path, 'rb');
      if ($handle === false) continue;
      try {
        if (!@flock($handle, LOCK_SH)) continue;
        while (($line = fgets($handle)) !== false) {
          $decoded = json_decode($line, true);
          if (!is_array($decoded)) continue;
          $record = self::displayRecord($decoded);
          if ($record === null) continue;
          $types[(string)$record['type']] = true;
          if (($type !== 'all' && $record['type'] !== $type)
            || !self::matchesStatusGroup($record, $status_group)) {
            continue;
          }
          $total++;
          $records[] = $record;
          if (count($records) > $limit) array_shift($records);
        }
      } finally {
        @flock($handle, LOCK_UN);
        @fclose($handle);
      }
    }
    $records = array_reverse($records);
    $types = array_keys($types);
    sort($types, SORT_STRING);
    return ['records' => $records, 'total' => $total, 'types' => $types];
  }

  private static function isValidDate(string $date): bool {
    if (preg_match('/\A(\d{4})(\d{2})(\d{2})\z/D', $date, $matches) !== 1) return false;
    return checkdate((int)$matches[2], (int)$matches[3], (int)$matches[1]);
  }

  private static function isValidType(string $type): bool {
    return preg_match('/\A[a-z][a-z0-9-]{0,63}\z/D', $type) === 1;
  }

  private static function isValidPrefix(string $prefix): bool {
    return preg_match('/\A[a-z][a-z0-9-]{0,31}\z/D', $prefix) === 1;
  }

  /** @return array<int,string> */
  private static function filesForDate(string $directory, string $date, string $prefix): array {
    if (!is_dir($directory)) return [];
    $entries = scandir($directory);
    if ($entries === false) return [];
    $files = [];
    $pattern = '/^' . preg_quote($prefix, '/') . '-' . preg_quote($date, '/') . '(?:\.(\d+))?\.log$/D';
    foreach ($entries as $name) {
      if (preg_match($pattern, $name, $matches) !== 1) continue;
      $path = $directory . DIRECTORY_SEPARATOR . $name;
      if (is_link($path) || !is_file($path) || !is_readable($path)) continue;
      $files[] = ['path' => $path, 'index' => isset($matches[1]) ? (int)$matches[1] : 0];
    }
    usort($files, static fn(array $left, array $right): int => $left['index'] <=> $right['index']);
    return array_column($files, 'path');
  }

  /** @param array<string,mixed> $record
   * @return array<string,int|string>|null */
  private static function displayRecord(array $record): ?array {
    $type = isset($record['type']) && is_string($record['type']) ? $record['type'] : '';
    if (!self::isValidType($type)) return null;
    $status = isset($record['http_status']) ? filter_var($record['http_status'], FILTER_VALIDATE_INT) : false;
    if ($status === false || $status < 100 || $status > 599) $status = 0;
    return [
      'timestamp' => self::field($record, 'timestamp', 64),
      'error_id' => self::field($record, 'error_id', 64),
      'type' => $type,
      'http_status' => $status,
      'request_method' => self::field($record, 'request_method', 16),
      'request_path' => self::field($record, 'request_path', 1024),
      'message' => self::field($record, 'message', self::MAX_FIELD_LENGTH),
    ];
  }

  /** @param array<string,mixed> $record */
  private static function field(array $record, string $key, int $limit): string {
    if (!isset($record[$key]) || !is_scalar($record[$key])) return '';
    return mb_substr((string)$record[$key], 0, $limit);
  }

  /** @param array<string,int|string> $record */
  private static function matchesStatusGroup(array $record, string $status_group): bool {
    $status = (int)$record['http_status'];
    return $status_group === 'all'
      || ($status_group === '4xx' && $status >= 400 && $status < 500)
      || ($status_group === '5xx' && $status >= 500 && $status < 600);
  }
}

/** Read administrator audit records from their independent storage. */
final class AuditLogReader {
  /** @return array<int,string> */
  public static function availableDates(string $directory): array {
    return ErrorLogReader::availableDates($directory, 'audit');
  }

  /** @return array{records:array<int,array<string,int|string>>,total:int,types:array<int,string>} */
  public static function read(string $directory, string $date, int $limit = 100): array {
    return ErrorLogReader::read($directory, $date, 'all', 'all', $limit, 'audit');
  }
}

final class ApplicationErrorHandler {
  private const DEFAULT_RETENTION_DAYS = 30;
  private const DEFAULT_MAX_BYTES = 5242880;
  private const DEFAULT_MAX_FILES_PER_DAY = 5;
  private static string $log_directory = '';
  private static string $audit_directory = '';
  private static bool $installed = false;
  private static bool $rendering = false;
  private static int $retention_days = self::DEFAULT_RETENTION_DAYS;
  private static int $max_bytes = self::DEFAULT_MAX_BYTES;
  private static int $max_files_per_day = self::DEFAULT_MAX_FILES_PER_DAY;
  private static int $audit_retention_days = 365;
  private static int $audit_max_bytes = self::DEFAULT_MAX_BYTES;
  private static int $audit_max_files_per_day = self::DEFAULT_MAX_FILES_PER_DAY;

  public static function install(string $log_directory, ?string $audit_directory = null): void {
    if (self::$installed) return;
    self::$log_directory = rtrim($log_directory, '/\\');
    self::$audit_directory = rtrim($audit_directory ?? dirname(self::$log_directory) . '/auditlog', '/\\');
    self::prepareLogDirectory(self::$log_directory);
    self::prepareLogDirectory(self::$audit_directory);

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

  public static function configure(
    int $retention_days,
    int $max_bytes,
    int $max_files_per_day,
    int $audit_retention_days = 365,
    int $audit_max_bytes = self::DEFAULT_MAX_BYTES,
    int $audit_max_files_per_day = self::DEFAULT_MAX_FILES_PER_DAY
  ): void {
    self::$retention_days = max(1, min(3650, $retention_days));
    self::$max_bytes = max(65536, min(104857600, $max_bytes));
    self::$max_files_per_day = max(1, min(100, $max_files_per_day));
    self::$audit_retention_days = max(1, min(3650, $audit_retention_days));
    self::$audit_max_bytes = max(65536, min(104857600, $audit_max_bytes));
    self::$audit_max_files_per_day = max(1, min(100, $audit_max_files_per_day));
    try {
      if (random_int(1, 100) === 1) {
        self::cleanupLogs();
        self::cleanupAuditLogs();
      }
    } catch (Throwable $e) {
      // ログ整理に失敗してもアプリケーション処理は継続する。
    }
  }

  public static function cleanupLogs(?int $now = null, int $limit = 20): int {
    return ErrorLogStorage::cleanup(self::$log_directory, self::$retention_days, $limit, $now);
  }

  public static function cleanupAuditLogs(?int $now = null, int $limit = 20): int {
    return ErrorLogStorage::cleanup(
      self::$audit_directory, self::$audit_retention_days, $limit, $now, 'audit'
    );
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

  /**
   * Record a successful state-changing administrator action without retaining its target data.
   *
   * @param array<string,int> $counts
   */
  public static function reportAdminAudit(string $action, array $counts = []): string {
    if (preg_match('/\A[a-z][a-z0-9-]{0,63}\z/D', $action) !== 1) $action = 'invalid-action';
    $normalized = [];
    foreach ($counts as $name => $count) {
      if (!is_string($name) || preg_match('/\A[a-z][a-z0-9-]{0,31}\z/D', $name) !== 1) continue;
      $normalized[$name] = max(0, min(1000000, (int)$count));
    }
    ksort($normalized, SORT_STRING);
    $message = 'Administrator action: ' . $action;
    foreach ($normalized as $name => $count) $message .= ' ' . $name . '=' . $count;
    return self::writeAuditRecord([
      'type' => 'admin-audit',
      'audit_action' => $action,
      'message' => $message,
    ] + $normalized);
  }

  public static function reportHttpError(int $status, string $message, ?Throwable $cause = null): string {
    $record = [
      'type' => $status >= 500 ? 'http-server-error' : 'http-client-error',
      'http_status' => $status,
      'message' => $message,
    ];
    if ($cause !== null) {
      $record += [
        'exception' => get_class($cause),
        'code' => $cause->getCode(),
        'file' => $cause->getFile(),
        'line' => $cause->getLine(),
        'trace' => $cause->getTraceAsString(),
      ];
    }
    return self::writeRecord($record);
  }

  /**
   * HTMLテンプレートを使わないAPI向けに、記録と安全な本文生成を一括で行う。
   * 5xxの詳細は公開せず、通常画面と同じ照合用エラーIDへ置き換える。
   */
  public static function respondPlainError(
    int $status,
    string $public_message,
    bool $english = false,
    string $prefix = '',
    ?string $diagnostic = null,
    ?Throwable $cause = null
  ): void {
    if ($status < 400 || $status > 599) $status = 500;
    $log_message = trim(strip_tags($diagnostic ?? $public_message));
    $log_message = mb_substr($log_message !== '' ? $log_message : 'API request failed.', 0, 4000);
    $error_id = self::reportHttpError($status, $log_message, $cause);
    $response_message = $status >= 500 ? self::publicMessage($error_id, $english) : $public_message;
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $prefix . $response_message;
    exit;
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

    self::prepareLogDirectory(self::$log_directory);
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

  private static function writeAuditRecord(array $details): string {
    $audit_id = self::newErrorId();
    $record = [
      'timestamp' => date(DATE_ATOM),
      'date' => date('Ymd'),
      'error_id' => $audit_id,
      'php_version' => PHP_VERSION,
      'request_method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
      'request_path' => self::requestPath(),
    ] + $details;
    $record = self::redactRecord($record);
    $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $line = (is_string($json) ? $json : '{"date":"' . date('Ymd') . '","error_id":"' . $audit_id
      . '","type":"admin-audit","message":"Failed to encode audit details."}') . PHP_EOL;

    self::prepareLogDirectory(self::$audit_directory);
    $written = ErrorLogStorage::append(
      self::$audit_directory,
      date('Ymd'),
      $line,
      self::$audit_max_bytes,
      self::$audit_max_files_per_day,
      'audit'
    );
    if (!$written) {
      self::writeRecord([
        'type' => 'audit-log-write-error',
        'message' => 'Failed to write an administrator audit record.',
      ]);
    }
    return $audit_id;
  }

  private static function prepareLogDirectory(string $directory): void {
    if ($directory === '') return;
    if (!is_dir($directory)) @mkdir($directory, 0700, true);
    if (is_dir($directory)) @chmod($directory, 0700);
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
    foreach (['second_pass'] as $name) {
      if (isset($GLOBALS[$name]) && is_string($GLOBALS[$name])) $secrets[] = $GLOBALS[$name];
    }
    if (class_exists('Config', false) && Config::isLoaded()) {
      $secrets[] = Config::string('admin.password');
      $secrets[] = Config::string('security.paint_password');
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
