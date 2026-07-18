<?php
// request_info.inc.php for noReita (C) sakots 2026 MIT License

const REQUEST_INFO_INC_VER = 20260718;

final class RequestInfo {
  private const IP_SOURCES = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

  /**
   * リクエストから最初の有効なクライアントIPを取得する。
   *
   * @param array<string, mixed>|null $server テスト時に差し替えるサーバー変数
   */
  public static function clientIp(?array $server = null): string {
    $use_environment_fallback = $server === null;
    $server ??= $_SERVER;

    foreach (self::IP_SOURCES as $source) {
      $value = $server[$source] ?? ($use_environment_fallback ? getenv($source) : null);
      if (!is_string($value) || $value === '') continue;

      foreach (explode(',', $value) as $candidate) {
        $candidate = trim($candidate);
        if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) return $candidate;
      }
    }
    return '';
  }
}
