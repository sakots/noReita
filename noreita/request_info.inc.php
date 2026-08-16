<?php
// request_info.inc.php for noReita (C) sakots 2026 MIT License

const REQUEST_INFO_INC_VER = 20260816;

final class RequestInfo {
  /**
   * 接続元IPを取得する。転送ヘッダーは、直近の接続元が信頼済みプロキシの場合だけ使用する。
   *
   * @param array<string, mixed>|null $server テスト時に差し替えるサーバー変数
   * @param array<int, string>|null $trusted_proxies 信頼するプロキシのIPまたはCIDR
   */
  public static function clientIp(?array $server = null, ?array $trusted_proxies = null): string {
    $server ??= $_SERVER;
    $remote = $server['REMOTE_ADDR'] ?? '';
    if (!is_string($remote) || filter_var($remote, FILTER_VALIDATE_IP) === false) return '';

    if ($trusted_proxies === null) {
      $trusted_proxies = class_exists('Config', false) && Config::isLoaded()
        ? Config::array('security.trusted_proxies') : [];
    }
    if (!self::isTrustedProxy($remote, $trusted_proxies)) return $remote;

    $forwarded = $server['HTTP_X_FORWARDED_FOR'] ?? '';
    if (!is_string($forwarded) || $forwarded === '' || strlen($forwarded) > 4096) return $remote;
    $chain = explode(',', $forwarded);
    if (count($chain) > 32) return $remote;
    foreach ($chain as &$candidate) {
      $candidate = trim($candidate);
      if ($candidate === '' || filter_var($candidate, FILTER_VALIDATE_IP) === false) return $remote;
    }
    unset($candidate);

    // 右端は直近のプロキシが追加した値。信頼済みホップだけを右から除外する。
    for ($index = count($chain) - 1; $index >= 0; $index--) {
      if (!self::isTrustedProxy($chain[$index], $trusted_proxies)) return $chain[$index];
    }
    return $chain[0];
  }

  /** @param array<int, string> $trusted_proxies */
  private static function isTrustedProxy(string $ip, array $trusted_proxies): bool {
    $packed_ip = @inet_pton($ip);
    if ($packed_ip === false) return false;
    foreach ($trusted_proxies as $network) {
      if (!is_string($network)) continue;
      $parts = explode('/', $network, 2);
      $packed_network = @inet_pton($parts[0]);
      if ($packed_network === false || strlen($packed_network) !== strlen($packed_ip)) continue;
      if (!isset($parts[1])) {
        if (hash_equals($packed_network, $packed_ip)) return true;
        continue;
      }
      if (preg_match('/\A(?:0|[1-9][0-9]{0,2})\z/D', $parts[1]) !== 1) continue;
      $prefix = (int)$parts[1];
      $bits = strlen($packed_ip) * 8;
      if ($prefix < 1 || $prefix > $bits) continue;
      $whole_bytes = intdiv($prefix, 8);
      if (substr($packed_ip, 0, $whole_bytes) !== substr($packed_network, 0, $whole_bytes)) continue;
      $remaining_bits = $prefix % 8;
      if ($remaining_bits === 0) return true;
      $mask = (0xff << (8 - $remaining_bits)) & 0xff;
      if ((ord($packed_ip[$whole_bytes]) & $mask) === (ord($packed_network[$whole_bytes]) & $mask)) {
        return true;
      }
    }
    return false;
  }
}
