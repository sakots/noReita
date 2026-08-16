<?php
// misskey_security.inc.php for noReita (C) sakots 2026 MIT License

const MISSKEY_SECURITY_VER = 20260816;

final class MisskeyServerSecurity {
  /** @return string|false */
  public static function normalizeBaseUrl(string $url) {
    $url = trim($url);
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) return false;
    $parts = parse_url($url);
    if (!is_array($parts)
      || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
      || empty($parts['host'])
      || isset($parts['user']) || isset($parts['pass'])
      || isset($parts['query']) || isset($parts['fragment'])
      || (isset($parts['port']) && (int)$parts['port'] !== 443)
      || !in_array((string)($parts['path'] ?? ''), ['', '/'], true)) return false;

    $host = strtolower(rtrim((string)$parts['host'], '.'));
    if ($host === ''
      || filter_var($host, FILTER_VALIDATE_IP) !== false
      || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
      || self::resolvePublicIp($host) === false) return false;
    return 'https://' . $host;
  }

  /** @return string|false */
  public static function resolvePublicIp(string $host) {
    if (filter_var($host, FILTER_VALIDATE_IP)) {
      $addresses = [$host];
    } else {
      $addresses = gethostbynamel($host) ?: [];
      if (function_exists('dns_get_record') && defined('DNS_AAAA')) {
        $records = @dns_get_record($host, DNS_AAAA);
        if (is_array($records)) {
          foreach ($records as $record) {
            if (!empty($record['ipv6'])) $addresses[] = $record['ipv6'];
          }
        }
      }
    }
    $addresses = array_values(array_unique($addresses));
    if ($addresses === []) return false;
    foreach ($addresses as $address) {
      if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return false;
      }
    }
    return $addresses[0];
  }

  /** @return array<int, mixed>|false */
  public static function curlOptions(string $base_url, int $timeout = 15) {
    $normalized = self::normalizeBaseUrl($base_url);
    if ($normalized === false) return false;
    $host = (string)parse_url($normalized, PHP_URL_HOST);
    $ip = self::resolvePublicIp($host);
    if ($ip === false) return false;
    $resolve_ip = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
    return [
      CURLOPT_FOLLOWLOCATION => false,
      CURLOPT_CONNECTTIMEOUT => 5,
      CURLOPT_TIMEOUT => max(5, min(60, $timeout)),
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_SSL_VERIFYHOST => 2,
      CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
      CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
      CURLOPT_PROXY => '',
      CURLOPT_RESOLVE => [$host . ':443:' . $resolve_ip],
    ];
  }
}
