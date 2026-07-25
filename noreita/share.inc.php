<?php
// share.inc.php for noReita (C) sakots 2026 MIT License

const SHARE_INC_VER = 20260725;

final class ShareService {
  private const DEFAULT_SERVERS = [
    ['X', 'https://x.com'],
    ['Bluesky', 'https://bsky.app'],
    ['Threads', 'https://www.threads.net'],
    ['pawoo.net', 'https://pawoo.net'],
    ['fedibird.com', 'https://fedibird.com'],
    ['misskey.io', 'https://misskey.io'],
    ['xissmie.xfolio.jp', 'https://xissmie.xfolio.jp'],
    ['misskey.design', 'https://misskey.design'],
    ['nijimiss.moe', 'https://nijimiss.moe'],
    ['sushi.ski', 'https://sushi.ski'],
  ];

  public static function servers(?array $configured = null): array {
    $servers = $configured ?? self::DEFAULT_SERVERS;
    $servers[] = ['直接入力', 'direct'];
    return $servers;
  }

  public static function buildShareUrl(
    string $selected_server,
    string $direct_server,
    string $title,
    string $url
  ): string {
    $server = $selected_server === 'direct' ? $direct_server : $selected_server;
    $server = rtrim($server, '/');
    if (!self::isValidServerUrl($server)) {
      throw new InvalidArgumentException('A valid sharing destination is required.');
    }

    switch ($server) {
      case 'https://x.com':
      case 'https://twitter.com':
        $endpoint = 'https://twitter.com/intent/tweet?text=';
        break;
      case 'https://bsky.app':
        $endpoint = 'https://bsky.app/intent/compose?text=';
        break;
      case 'https://www.threads.net':
        $endpoint = 'https://www.threads.net/intent/post?text=';
        break;
      default:
        $endpoint = $server . '/share?text=';
    }
    return $endpoint . rawurlencode($title . ' ' . $url);
  }

  private static function isValidServerUrl(string $url): bool {
    if (filter_var($url, FILTER_VALIDATE_URL) === false) return false;
    $parts = parse_url($url);
    return is_array($parts)
      && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
      && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass'])
      && !isset($parts['query']) && !isset($parts['fragment']);
  }
}
