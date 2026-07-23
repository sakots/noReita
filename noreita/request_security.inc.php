<?php
// request_security.inc.php for noReita (C) sakots 2026 MIT License

const REQUEST_SECURITY_INC_VER = 20260723;

final class RequestSecurityException extends RuntimeException {
}

final class RequestSecurity {
  public static function startSession(): void {
    if (session_status() !== PHP_SESSION_NONE) return;

    session_name(defined('SESSION_NAME') ? SESSION_NAME : 'noreita_session');
    session_save_path(__DIR__ . '/session/');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
      'lifetime' => 0,
      'path' => '',
      'domain' => '',
      'secure' => self::isHttps(),
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
    session_start();
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

  public static function sessionValue(string $key, mixed $default = null): mixed {
    self::startSession();
    return $_SESSION[$key] ?? $default;
  }

  private static function isHttps(): bool {
    return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string)$_SERVER['HTTPS']) !== 'off';
  }

  private static function disableCacheHeaders(): void {
    if (headers_sent()) return;
    header('Expires:');
    header('Cache-Control:');
    header('Pragma:');
  }
}

final class AdminAuth {
  private const SESSION_FINGERPRINT = 'admin_auth_fingerprint';
  private const SESSION_LAST_ACTIVITY = 'admin_auth_last_activity';

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
