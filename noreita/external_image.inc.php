<?php
// external_image.inc.php for noReita (C) sakots 2026 MIT License

const EXTERNAL_IMAGE_INC_VER = 20260718;

final class ExternalImageService {
  public const MAX_BYTES = 1024 * 1024;
  private const MAX_REDIRECTS = 5;
  private const THUMBNAIL_EXTENSIONS = ['avif', 'webp', 'jpg', 'png', 'gif'];

  public function __construct(
    private string $thumbnail_dir,
    private string $thumbnail_url = 'thumbnail/',
    private int $thumbnail_width = 200,
    private int $file_permission = 0600,
    private int $directory_permission = 0700,
  ) {
  }

  // 本文中の外部画像URLへ、キャッシュしたサムネイルを追加する。
  public function addThumbnailLinks(string $comment): string {
    preg_match_all('/https?:\/\/[^\s<>"\'{}|\\^`[\]]+/i', $comment, $matches);
    foreach (array_unique($matches[0]) as $url) {
      if (preg_match('/\.(jpg|jpeg|png|gif|webp|avif)(\?.*)?$/i', $url) !== 1) continue;

      $thumbnail_base = rtrim($this->thumbnail_dir, '/\\') . DIRECTORY_SEPARATOR . md5($url) . '_thumb';
      $thumbnail_path = $this->findThumbnail($thumbnail_base);
      if ($thumbnail_path === null) {
        $thumbnail_path = $this->createThumbnail($url, $thumbnail_base);
      }
      if ($thumbnail_path === null) continue;

      $escaped_url = htmlspecialchars($url, ENT_QUOTES | ENT_HTML5);
      $thumbnail_url = rtrim($this->thumbnail_url, '/') . '/' . rawurlencode(basename($thumbnail_path));
      $replacement = '<a href="' . $escaped_url . '" target="_blank" rel="nofollow noopener noreferrer">'
        . $escaped_url . '</a><br><a href="' . $escaped_url
        . '" target="_blank" rel="nofollow noopener noreferrer"><img src="' . $thumbnail_url
        . '" alt="thumbnail" style="max-width:' . $this->thumbnail_width . 'px; max-height:'
        . $this->thumbnail_width . 'px;"></a>';
      $comment = str_replace($url, $replacement, $comment);
    }
    return $comment;
  }

  private function findThumbnail(string $thumbnail_base): ?string {
    foreach (self::THUMBNAIL_EXTENSIONS as $extension) {
      $path = $thumbnail_base . '.' . $extension;
      if (is_file($path)) return $path;
    }
    return null;
  }

  private function createThumbnail(string $url, string $thumbnail_base): ?string {
    $image_data = self::downloadImage($url);
    if ($image_data === false) return null;

    if (!is_dir($this->thumbnail_dir)
      && !@mkdir($this->thumbnail_dir, $this->directory_permission, true)
      && !is_dir($this->thumbnail_dir)) {
      return null;
    }
    $temporary_file = tempnam(sys_get_temp_dir(), 'noreita_thumb_');
    if ($temporary_file === false) return null;

    try {
      if (file_put_contents($temporary_file, $image_data, LOCK_EX) === false) return null;
      $thumbnail = new Thumbnail($temporary_file, $this->thumbnail_dir, $this->thumbnail_width);
      if (!$thumbnail->createThumbnail()) return null;
      $output_path = $thumbnail->getOutputPath();
      if (!$output_path || !is_file($output_path)) return null;
      @chmod($output_path, $this->file_permission);
      return $output_path;
    } finally {
      @unlink($temporary_file);
    }
  }

  // URLのホストを公開IPに解決する。全ての解決結果が安全な場合だけ返す。
  public static function resolvePublicIp(string $host): string|false {
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
    if (!$addresses) return false;
    foreach ($addresses as $address) {
      if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return false;
      }
    }
    return $addresses[0];
  }

  // HTTP Locationを現在のURLを基準に絶対URLへ変換する。
  public static function resolveRedirectUrl(string $base_url, string $location): string|false {
    $location = trim($location);
    if ($location === '' || preg_match('/[\x00-\x1F\x7F]/', $location)) return false;
    if (preg_match('|^https?://|i', $location)) return $location;

    $base = parse_url($base_url);
    if (!$base || empty($base['scheme']) || empty($base['host'])) return false;
    if (str_starts_with($location, '//')) return $base['scheme'] . ':' . $location;

    $port = isset($base['port']) ? ':' . $base['port'] : '';
    $origin = $base['scheme'] . '://' . $base['host'] . $port;
    if (str_starts_with($location, '/')) return $origin . $location;

    $path = preg_replace('|/[^/]*$|', '/', $base['path'] ?? '/');
    $normalized = [];
    foreach (explode('/', $path . $location) as $part) {
      if ($part === '' || $part === '.') continue;
      if ($part === '..') array_pop($normalized);
      else $normalized[] = $part;
    }
    return $origin . '/' . implode('/', $normalized);
  }

  // TLS、接続先IP、リダイレクト先、容量、画像内容を検証して取得する。
  public static function downloadImage(string $url): string|false {
    if (!function_exists('curl_init')) return false;

    for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
      $parts = parse_url($url);
      if (!$parts || empty($parts['scheme']) || empty($parts['host'])
        || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
        || isset($parts['user']) || isset($parts['pass'])) return false;

      $scheme = strtolower($parts['scheme']);
      $port = isset($parts['port']) ? (int)$parts['port'] : ($scheme === 'https' ? 443 : 80);
      if ($port < 1 || $port > 65535) return false;
      $ip = self::resolvePublicIp($parts['host']);
      if ($ip === false) return false;

      $data = '';
      $location = '';
      $too_large = false;
      $curl = curl_init();
      curl_setopt_array($curl, [
        CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => false, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10, CURLOPT_USERAGENT => 'noReita/1.0',
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_PROXY => '', CURLOPT_RESOLVE => [$parts['host'] . ':' . $port . ':' . $ip],
        CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$location, &$too_large): int {
          if (stripos($header, 'Location:') === 0) $location = trim(substr($header, 9));
          elseif (stripos($header, 'Content-Length:') === 0 && (int)trim(substr($header, 15)) > self::MAX_BYTES) {
            $too_large = true;
            return 0;
          }
          return strlen($header);
        },
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$data, &$too_large): int {
          if (strlen($data) + strlen($chunk) > self::MAX_BYTES) {
            $too_large = true;
            return 0;
          }
          $data .= $chunk;
          return strlen($chunk);
        },
      ]);
      $success = curl_exec($curl);
      $http_code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

      if ($success && $http_code === 200 && !$too_large && $data !== '') {
        $info = @getimagesizefromstring($data);
        if (!$info || !isset($info['mime'])
          || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'], true)) return false;
        $width = (int)$info[0];
        $height = (int)$info[1];
        if ($width < 1 || $height < 1 || $width > 16384 || $height > 16384 || $width * $height > 20000000) return false;
        return $data;
      }
      if ($too_large || !in_array($http_code, [301, 302, 303, 307, 308], true) || $location === '') return false;
      $url = self::resolveRedirectUrl($url, $location);
      if ($url === false) return false;
    }
    return false;
  }
}

function external_image_service(): ExternalImageService {
  static $service = null;
  if ($service === null) {
    $service = new ExternalImageService(
      __DIR__ . '/thumbnail',
      'thumbnail/',
      200,
      PERMISSION_FOR_DEST,
      PERMISSION_FOR_DIR,
    );
  }
  return $service;
}
