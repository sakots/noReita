<?php
declare(strict_types=1);

// PHPの内蔵サーバーは.htaccessを解釈しない。pre.shとHTTP統合テストで共用する。
// ルーターの変数を配信するPHPスクリプトのスコープへ持ち込まない。
return (static function (): bool {
  $deny = static function (int $status = 403): bool {
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $status === 403 ? "Forbidden\n" : "Service unavailable\n";
    return true;
  };
  $normalize = static function (string $path): string {
    return implode('/', array_filter(explode('/', str_replace('\\', '/', $path)),
      static fn(string $segment): bool => $segment !== '' && $segment !== '.'));
  };
  $path = rawurldecode(explode('?', (string)($_SERVER['REQUEST_URI'] ?? '/'), 2)[0]);
  if (!str_starts_with($path, '/') || str_contains($path, "\0")) return $deny();
  $relative = $normalize($path);
  $private_directories = ['session', 'cache', 'backup', 'errorlog', 'auditlog', 'tmp'];
  $is_private = static function (string $relative) use (&$private_directories): bool {
    $relative = strtolower($relative);
    foreach (explode('/', $relative) as $segment) {
      if (str_starts_with($segment, '.')) return true;
    }
    foreach ($private_directories as $directory) {
      $directory = strtolower($directory);
      if ($directory === '' || $relative === $directory || str_starts_with($relative, $directory . '/')) return true;
    }
    $basename = basename($relative);
    $extension = pathinfo($basename, PATHINFO_EXTENSION);
    return preg_match('/\A(?:config|debug)(?:\..+)?\z/i', $basename) === 1
      || preg_match('/\Atheme(?:_conf|_manifest)?\.php(?:\..+|~)?\z/i', $basename) === 1
      || in_array($extension, ['ini', 'log', 'dat', 'json', 'db'], true)
      || preg_match('/\.(?:twig|blade\.php)(?:\..+|~)?\z/i', $basename) === 1
      || preg_match('/\.db-(?:wal|shm|journal)\z/i', $basename) === 1;
  };
  if ($is_private($relative)) return $deny();

  $root = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
  if ($root === false) return $deny(503);
  try {
    require_once $root . '/config_loader.inc.php';
    Config::load($root);
    $private_directories[] = $normalize(Config::string('paths.temporary'));
  } catch (Throwable $error) {
    // 設定エラーの画面・ログは通常の起動処理に任せる。静的ファイルは拒否する。
    if ($relative === '' || $relative === 'index.php') return false;
    return $deny(503);
  }
  if ($is_private($relative)) return $deny();

  // 公開名のシンボリックリンクでも、非公開ファイルやドキュメントルート外は配信しない。
  $resolved = realpath($root . '/' . $relative);
  if ($resolved !== false) {
    $root_prefix = rtrim(str_replace('\\', '/', $root), '/') . '/';
    $resolved = str_replace('\\', '/', $resolved);
    if ($resolved !== rtrim($root_prefix, '/') && !str_starts_with($resolved, $root_prefix)) return $deny();
    if ($is_private(substr($resolved, strlen($root_prefix)))) return $deny();
    foreach ($private_directories as $directory) {
      $private_root = realpath($root . '/' . $directory);
      if ($private_root === false) continue;
      $private_root = rtrim(str_replace('\\', '/', $private_root), '/');
      if ($resolved === $private_root || str_starts_with($resolved, $private_root . '/')) return $deny();
    }
  }
  return false;
})();
