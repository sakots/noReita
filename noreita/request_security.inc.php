<?php
// request_security.inc.php for noReita (C) sakots 2026 MIT License

const REQUEST_SECURITY_INC_VER = 20260726;

final class RequestSecurityException extends RuntimeException {
}

final class RequestSecurity {
  public static function startSession(): void {
    if (session_status() !== PHP_SESSION_NONE) return;

    $session_directory = __DIR__ . '/session/';
    $session_file_lifetime = self::sessionFileLifetime();
    session_name(Config::string('security.session_name'));
    session_save_path($session_directory);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string)$session_file_lifetime);
    session_set_cookie_params([
      'lifetime' => 0,
      'path' => '',
      'domain' => '',
      'secure' => self::isHttps(),
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
    session_start();
    try {
      if (random_int(1, 100) === 1) {
        SessionFileCleaner::cleanup(
          $session_directory, $session_file_lifetime, session_id(), 100
        );
      }
    } catch (Throwable $e) {
      // セッション掃除の失敗で通常のリクエストを停止しない。
    }
    self::disableCacheHeaders();
  }

  public static function csrfToken(): string {
    self::startSession();
    self::disableCacheHeaders();
    if (!isset($_SESSION['token']) || !is_string($_SESSION['token']) || $_SESSION['token'] === '') {
      $_SESSION['token'] = hash('sha256', session_id(), false);
    }
    return $_SESSION['token'];
  }

  public static function assertCsrfRequest(string $usercode, bool $english): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
      throw new RequestSecurityException($english ? 'This operation has failed.' : 'この操作は失敗しました。', 400);
    }

    self::assertSameOriginRequest($usercode, $english);
    $token = (string)filter_input_data('POST', 'token');
    $session_token = isset($_SESSION['token']) ? (string)$_SESSION['token'] : '';
    if ($token === '' || $session_token === '' || !hash_equals($session_token, $token)) {
      throw new RequestSecurityException($english
        ? "CSRF token mismatch.\nPlease reload."
        : "CSRFトークンが一致しません。\nリロードしてください。", 403);
    }
  }

  public static function assertSameOriginRequest(string $usercode, bool $english): void {
    self::startSession();
    $cookie_usercode = t(filter_input_data('COOKIE', 'usercode'));
    $session_usercode = t(isset($_SESSION['usercode']) ? (string)$_SESSION['usercode'] : '');
    if ($cookie_usercode === '') {
      throw new RequestSecurityException($english ? 'Cookie check failed.' : 'Cookieが確認できません。', 403);
    }
    if ($usercode === '' || ($usercode !== $cookie_usercode && $usercode !== $session_usercode)) {
      throw new RequestSecurityException($english ? 'User code mismatch.' : 'ユーザーコードが一致しません。', 403);
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    $host = $_SERVER['HTTP_HOST'] ?? null;
    if (!is_string($origin) || !is_string($host)) {
      throw new RequestSecurityException($english ? 'Your browser is not supported.' : 'お使いのブラウザはサポートされていません。', 403);
    }
    if (parse_url($origin, PHP_URL_HOST) !== $host) {
      throw new RequestSecurityException($english ? 'The post has been rejected.' : '拒絶されました。', 403);
    }

  }

  public static function assertCurrentCsrfRequest(bool $english): void {
    self::assertCsrfRequest((string)($GLOBALS['usercode'] ?? ''), $english);
  }

  public static function assertCurrentSameOriginRequest(bool $english): void {
    self::assertSameOriginRequest((string)($GLOBALS['usercode'] ?? ''), $english);
  }

  /** @param mixed $default @return mixed */
  public static function sessionValue(string $key, $default = null) {
    self::startSession();
    return $_SESSION[$key] ?? $default;
  }

  private static function isHttps(): bool {
    return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string)$_SERVER['HTTPS']) !== 'off';
  }

  private static function sessionFileLifetime(): int {
    $lifetime = Config::int('security.session_file_lifetime');
    if (!is_int($lifetime) || $lifetime < 60 || $lifetime > 31536000) {
      throw new RuntimeException('security.session_file_lifetime must be between 60 and 31536000 seconds.');
    }
    return $lifetime;
  }

  private static function disableCacheHeaders(): void {
    if (headers_sent()) return;
    header('Expires:');
    header('Cache-Control:');
    header('Pragma:');
  }
}

final class SessionFileCleaner {
  public static function cleanup(
    string $directory,
    int $lifetime,
    string $current_session_id = '',
    int $limit = 100,
    ?int $now = null
  ): int {
    if ($lifetime < 1 || $limit < 1 || !is_dir($directory)) return 0;
    $now = $now ?? time();
    $current_file = $current_session_id !== '' ? 'sess_' . $current_session_id : '';
    $removed = 0;

    foreach (new DirectoryIterator($directory) as $file) {
      if ($removed >= $limit) break;
      if ($file->isDot() || $file->isLink() || !$file->isFile()) continue;
      $filename = $file->getFilename();
      if (!preg_match('/^sess_[A-Za-z0-9,-]+$/D', $filename)
        || ($current_file !== '' && hash_equals($current_file, $filename))) {
        continue;
      }

      $path = $file->getPathname();
      $handle = @fopen($path, 'r+');
      if ($handle === false) continue;
      try {
        if (!flock($handle, LOCK_EX | LOCK_NB)) continue;
        $stat = fstat($handle);
        if (is_array($stat) && (int)$stat['mtime'] < $now - $lifetime && @unlink($path)) {
          $removed++;
        }
        flock($handle, LOCK_UN);
      } finally {
        fclose($handle);
      }
    }
    return $removed;
  }
}

final class AdminAuth {
  private const SESSION_FINGERPRINT = 'admin_auth_fingerprint';
  private const SESSION_LAST_ACTIVITY = 'admin_auth_last_activity';

  public static function secondaryPasswordMatches(mixed $stored_password, mixed $configured_password): bool {
    return is_string($configured_password) && $configured_password !== ''
      && is_string($stored_password)
      && hash_equals($configured_password, $stored_password);
  }

  public static function login(string $provided_password, string $admin_password): bool {
    RequestSecurity::startSession();
    if ($provided_password === '' || !hash_equals($admin_password, $provided_password)) {
      self::clear();
      return false;
    }
    session_regenerate_id(true);
    unset($_SESSION['token']);
    $_SESSION[self::SESSION_FINGERPRINT] = self::fingerprint($admin_password);
    $_SESSION[self::SESSION_LAST_ACTIVITY] = time();
    return true;
  }

  public static function isAuthenticated(string $admin_password, int $lifetime): bool {
    RequestSecurity::startSession();
    if (!self::hasValidSession($_SESSION, $admin_password, $lifetime, time())) {
      self::clear();
      return false;
    }
    $_SESSION[self::SESSION_LAST_ACTIVITY] = time();
    return true;
  }

  public static function logout(): void {
    RequestSecurity::startSession();
    self::clear();
    session_regenerate_id(true);
    unset($_SESSION['token']);
  }

  public static function hasValidSession(array $session, string $admin_password, int $lifetime, int $now): bool {
    $fingerprint = $session[self::SESSION_FINGERPRINT] ?? null;
    $last_activity = $session[self::SESSION_LAST_ACTIVITY] ?? null;
    if (!is_string($fingerprint) || !is_int($last_activity) || $lifetime <= 0) return false;
    if ($last_activity > $now || ($now - $last_activity) > $lifetime) return false;
    return hash_equals(self::fingerprint($admin_password), $fingerprint);
  }

  public static function sessionFingerprint(string $admin_password): string {
    return self::fingerprint($admin_password);
  }

  private static function fingerprint(string $admin_password): string {
    return hash('sha256', "noReita-admin-session\0" . $admin_password);
  }

  private static function clear(): void {
    unset($_SESSION[self::SESSION_FINGERPRINT], $_SESSION[self::SESSION_LAST_ACTIVITY]);
  }
}

final class AdminLoginRateLimiter {
  private string $directory;
  private string $secret;
  private int $max_failures;
  private int $window_seconds;
  private int $lockout_seconds;
  private int $file_permission;

  public function __construct(
    string $directory,
    string $secret,
    int $max_failures = 5,
    int $window_seconds = 900,
    int $lockout_seconds = 900,
    int $file_permission = 0600
  ) {
    if ($secret === '' || $max_failures < 1 || $window_seconds < 1 || $lockout_seconds < 1) {
      throw new InvalidArgumentException('Invalid administrator login rate limit configuration.');
    }
    $this->directory = rtrim($directory, '/\\');
    $this->secret = $secret;
    $this->max_failures = $max_failures;
    $this->window_seconds = $window_seconds;
    $this->lockout_seconds = $lockout_seconds;
    $this->file_permission = $file_permission;
  }

  public function retryAfter(string $ip, ?int $now = null): int {
    $now = $now ?? time();
    $path = $this->recordPath($ip);
    if (!is_file($path)) return 0;
    $handle = @fopen($path, 'c+');
    if ($handle === false) throw new RuntimeException('Failed to open administrator login attempt record.');
    try {
      if (!flock($handle, LOCK_EX)) throw new RuntimeException('Failed to lock administrator login attempt record.');
      $record = $this->readRecord($handle);
      $retry_after = max(0, (int)$record['locked_until'] - $now);
      if ($retry_after === 0 && (int)$record['first_failed_at'] <= $now - $this->window_seconds) {
        flock($handle, LOCK_UN);
        fclose($handle);
        @unlink($path);
        return 0;
      }
      flock($handle, LOCK_UN);
      return $retry_after;
    } finally {
      if (is_resource($handle)) fclose($handle);
    }
  }

  public function recordFailure(string $ip, ?int $now = null): int {
    $now = $now ?? time();
    if (!is_dir($this->directory) || !is_writable($this->directory)) {
      throw new RuntimeException('Administrator login attempt directory is not writable.');
    }
    $path = $this->recordPath($ip);
    $handle = @fopen($path, 'c+');
    if ($handle === false) throw new RuntimeException('Failed to create administrator login attempt record.');
    @chmod($path, $this->file_permission);
    try {
      if (!flock($handle, LOCK_EX)) throw new RuntimeException('Failed to lock administrator login attempt record.');
      $record = $this->readRecord($handle);
      if ((int)$record['locked_until'] > $now) {
        $retry_after = (int)$record['locked_until'] - $now;
      } else {
        if ((int)$record['first_failed_at'] <= $now - $this->window_seconds) {
          $record = ['failed_count' => 0, 'first_failed_at' => $now, 'locked_until' => 0];
        }
        if ((int)$record['first_failed_at'] === 0) $record['first_failed_at'] = $now;
        $record['failed_count'] = (int)$record['failed_count'] + 1;
        if ((int)$record['failed_count'] >= $this->max_failures) {
          $record['locked_until'] = $now + $this->lockout_seconds;
        }
        $retry_after = max(0, (int)$record['locked_until'] - $now);
        $record['updated_at'] = $now;
        $this->writeRecord($handle, $record);
      }
      flock($handle, LOCK_UN);
      return $retry_after;
    } finally {
      fclose($handle);
    }
  }

  public function clear(string $ip): void {
    $path = $this->recordPath($ip);
    if (is_file($path) && !@unlink($path)) {
      throw new RuntimeException('Failed to clear administrator login attempt record.');
    }
  }

  public function cleanupExpired(?int $now = null, int $limit = 100): int {
    $now = $now ?? time();
    $files = glob($this->directory . DIRECTORY_SEPARATOR . 'admin-login-*.json') ?: [];
    $removed = 0;
    foreach (array_slice($files, 0, max(0, $limit)) as $path) {
      $modified = @filemtime($path);
      if ($modified !== false && $modified < $now - $this->window_seconds - $this->lockout_seconds
        && @unlink($path)) {
        $removed++;
      }
    }
    return $removed;
  }

  private function recordPath(string $ip): string {
    $identifier = hash_hmac('sha256', $ip !== '' ? $ip : 'unknown', $this->secret);
    return $this->directory . DIRECTORY_SEPARATOR . 'admin-login-' . $identifier . '.json';
  }

  private function readRecord($handle): array {
    rewind($handle);
    $json = stream_get_contents($handle, 4096);
    $record = is_string($json) && $json !== '' ? json_decode($json, true) : null;
    if (!is_array($record)) {
      return ['failed_count' => 0, 'first_failed_at' => 0, 'locked_until' => 0, 'updated_at' => 0];
    }
    return [
      'failed_count' => max(0, (int)($record['failed_count'] ?? 0)),
      'first_failed_at' => max(0, (int)($record['first_failed_at'] ?? 0)),
      'locked_until' => max(0, (int)($record['locked_until'] ?? 0)),
      'updated_at' => max(0, (int)($record['updated_at'] ?? 0)),
    ];
  }

  private function writeRecord($handle, array $record): void {
    $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    rewind($handle);
    if (!ftruncate($handle, 0) || fwrite($handle, $json) === false || !fflush($handle)) {
      throw new RuntimeException('Failed to write administrator login attempt record.');
    }
  }
}
