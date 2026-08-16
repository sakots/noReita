<?php
declare(strict_types=1);

// PHPの開発サーバーは.htaccessを解釈しないため、HTTP結合テストで
// Apacheと同じ非公開領域を再現する。
$path = rawurldecode((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/'));
$segments = array_values(array_filter(explode('/', str_replace('\\', '/', $path)), 'strlen'));
$top_level = strtolower((string)($segments[0] ?? ''));
$basename = strtolower(basename($path));
$extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));

$protected_directory = in_array($top_level, ['session', 'cache', 'backup', 'errorlog', 'auditlog', 'tmp'], true);
$protected_file = preg_match('/\Aconfig(?:\..+)?\z/i', $basename) === 1
  || in_array($extension, ['ini', 'log', 'dat', 'json', 'db'], true)
  || preg_match('/\.db-(?:wal|shm|journal)\z/i', $basename) === 1;

if ($protected_directory || $protected_file) {
  http_response_code(403);
  header('Content-Type: text/plain; charset=UTF-8');
  echo "Forbidden\n";
  return true;
}

return false;
