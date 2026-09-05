<?php
declare(strict_types=1);

if (!function_exists('str_contains')) {
  function str_contains(string $haystack, string $needle): bool {
    return $needle === '' || strpos($haystack, $needle) !== false;
  }
}
if (!function_exists('str_starts_with')) {
  function str_starts_with(string $haystack, string $needle): bool {
    return $needle === '' || strpos($haystack, $needle) === 0;
  }
}

if (!extension_loaded('curl') || !extension_loaded('pdo_sqlite')) {
  fwrite(STDERR, "curl and pdo_sqlite extensions are required.\n");
  exit(1);
}

$source = dirname(__DIR__) . '/noreita';
if (!is_file($source . '/vendor/autoload.php')) {
  fwrite(STDERR, "Composer dependencies are not installed. Run composer install --working-dir=noreita.\n");
  exit(1);
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_integration_' . bin2hex(random_bytes(8));
$webroot = $root . DIRECTORY_SEPARATOR . 'noreita';
$cookie_jar = $root . DIRECTORY_SEPARATOR . 'cookies.txt';
$server_log = $root . DIRECTORY_SEPARATOR . 'server.log';
$process = null;
$passed = 0;
$failed = 0;

function integration_test(string $name, callable $test): void {
  global $passed, $failed;
  try {
    if ($test() !== true) {
      throw new RuntimeException('test returned false');
    }
    echo "PASS: {$name}\n";
    $passed++;
  } catch (Throwable $e) {
    echo "FAIL: {$name} ({$e->getMessage()})\n";
    $failed++;
  }
}

function copy_tree(string $source, string $destination): void {
  if (!is_dir($destination) && !mkdir($destination, 0700, true) && !is_dir($destination)) {
    throw new RuntimeException("Could not create {$destination}");
  }
  $skip = ['auditlog', 'backup', 'cache', 'errorlog', 'img', 'session', 'temp', 'thumb', 'thumbnail', 'tmp'];
  foreach (new DirectoryIterator($source) as $item) {
    if ($item->isDot() || in_array($item->getFilename(), $skip, true) || $item->getFilename() === 'config.local.php') {
      continue;
    }
    $target = $destination . DIRECTORY_SEPARATOR . $item->getFilename();
    if ($item->isDir()) {
      copy_tree($item->getPathname(), $target);
    } elseif (!copy($item->getPathname(), $target)) {
      throw new RuntimeException("Could not copy {$item->getPathname()}");
    }
  }
}

function remove_tree(string $path): void {
  if (!is_dir($path)) return;
  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
  );
  foreach ($iterator as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
  }
  rmdir($path);
}

function http_request(string $url, string $cookie_jar, ?array $post = null, string $forwarded_for = '127.0.0.1'): array {
  $curl = curl_init($url);
  $response_headers = [];
  curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_COOKIEJAR => $cookie_jar,
    CURLOPT_COOKIEFILE => $cookie_jar,
    CURLOPT_HTTPHEADER => [
      'Host: localhost', 'Origin: http://localhost',
      'X-Forwarded-For: ' . $forwarded_for,
    ],
    CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$response_headers): int {
      $length = strlen($header);
      $separator = strpos($header, ':');
      if ($separator !== false) {
        $name = strtolower(trim(substr($header, 0, $separator)));
        $response_headers[$name] = trim(substr($header, $separator + 1));
      }
      return $length;
    },
  ]);
  if ($post !== null) {
    curl_setopt($curl, CURLOPT_POST, true);
    $is_multipart = false;
    foreach ($post as $value) {
      if ($value instanceof CURLFile) {
        $is_multipart = true;
        break;
      }
    }
    curl_setopt($curl, CURLOPT_POSTFIELDS, $is_multipart ? $post : http_build_query($post));
  }
  $body = curl_exec($curl);
  $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
  $redirect_url = (string)curl_getinfo($curl, CURLINFO_REDIRECT_URL);
  $error = curl_error($curl);
  if ($body === false) {
    throw new RuntimeException("HTTP request failed: {$error}");
  }
  return [$status, $body, $redirect_url, $response_headers];
}

function cookie_value(string $cookie_jar, string $name): ?string {
  foreach (file($cookie_jar, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (str_starts_with($line, '#HttpOnly_')) {
      $line = substr($line, strlen('#HttpOnly_'));
    } elseif ($line[0] === '#') {
      continue;
    }
    $fields = explode("\t", $line);
    if (count($fields) >= 7 && $fields[5] === $name) return $fields[6];
  }
  return null;
}

function replace_cookie_value(string $cookie_jar, string $name, string $value): bool {
  $lines = file($cookie_jar, FILE_IGNORE_NEW_LINES) ?: [];
  $replaced = false;
  foreach ($lines as &$line) {
    $prefix = str_starts_with($line, '#HttpOnly_') ? '#HttpOnly_' : '';
    $record = $prefix !== '' ? substr($line, strlen($prefix)) : $line;
    if ($record === '' || ($record[0] ?? '') === '#') continue;
    $fields = explode("\t", $record);
    if (count($fields) < 7 || $fields[5] !== $name) continue;
    $fields[6] = $value;
    $line = $prefix . implode("\t", $fields);
    $replaced = true;
  }
  unset($line);
  return $replaced && file_put_contents($cookie_jar, implode(PHP_EOL, $lines) . PHP_EOL) !== false;
}

try {
  copy_tree($source, $webroot);
  $config_local = <<<'PHP'
<?php
return [
  'admin' => [
    'password' => 'integration-admin-pass',
    'threads_per_page' => 1,
    'temporary_images_per_page' => 1,
    'login' => ['max_failures' => 3],
  ],
  'site' => ['base_url' => 'http://localhost/'],
  'paths' => ['theme' => 'starter'],
  'features' => [
    'image_upload' => true,
    'external_image_thumbnail' => false,
    'misskey_note' => false,
  ],
  // The local HTTP server is the explicitly trusted reverse proxy for forwarded-IP tests.
  'security' => ['trusted_proxies' => ['127.0.0.1']],
  'limits' => [
    'paint_image_kb' => 1,
    'paint_work_kb' => 1,
    'paint_request_kb' => 2,
  ],
];
PHP;
  if (file_put_contents($webroot . '/config.local.php', $config_local) === false) {
    throw new RuntimeException('Could not create test config.local.php');
  }
  $theme_config_file = $webroot . '/theme/eda/theme_conf.php';
  $theme_config = file_get_contents($theme_config_file);
  if (!is_string($theme_config) || !str_contains($theme_config, "const THEME_TEMPLATE_ENGINE = 'twig';")) {
    throw new RuntimeException('The integration-test theme must select Twig.');
  }
  $error_probe = <<<'PHP'
<?php
require_once __DIR__ . '/error_handler.inc.php';
$admin_pass = 'error-probe-secret';
ApplicationErrorHandler::install(__DIR__ . '/errorlog');
trigger_error('Warning password=error-probe-secret at ' . __FILE__, E_USER_WARNING);
throw new RuntimeException('Failure token=error-probe-secret at ' . __FILE__);
PHP;
  if (file_put_contents($webroot . '/error-probe.php', $error_probe) === false) {
    throw new RuntimeException('Could not create error handling probe.');
  }
  $plain_error_probe = <<<'PHP'
<?php
require_once __DIR__ . '/bootstrap.php';
ApplicationBootstrap::boot(__DIR__);
ApplicationErrorHandler::respondPlainError(
  502,
  'public-detail-must-not-appear',
  true,
  'Error: ',
  'Misskey API: upstream failed token=plain-api-secret'
);
PHP;
  if (file_put_contents($webroot . '/plain-error-probe.php', $plain_error_probe) === false) {
    throw new RuntimeException('Could not create plain error response probe.');
  }
  $misskey_missing_image_probe = <<<'PHP'
<?php
require_once __DIR__ . '/connect_misskey_api.php';
RequestSecurity::startSession();
$_SESSION['accessToken'] = 'misskey-probe-token';
$_SESSION['sns_api_val'] = ['', 'missing-probe.png', '', 0, false, 1, false, ''];
$context = new MisskeyApiContext(true, 'https://misskey.io');
connect_misskey_api::create_misskey_note($context);
PHP;
  if (file_put_contents($webroot . '/misskey-missing-image-probe.php', $misskey_missing_image_probe) === false) {
    throw new RuntimeException('Could not create Misskey missing-image probe.');
  }

  $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error_message);
  if ($socket === false) throw new RuntimeException("Could not reserve port: {$error_message}");
  $address = stream_socket_get_name($socket, false);
  fclose($socket);
  $port = (int)substr(strrchr((string)$address, ':'), 1);
  $origin_url = "http://127.0.0.1:{$port}";
  $base_url = "http://127.0.0.1:{$port}/index.php";

  $log = fopen($server_log, 'ab');
  if ($log === false) throw new RuntimeException('Could not create server log');
  $process = proc_open(
    [PHP_BINARY, '-d', 'opcache.enable_cli=0', '-d', 'opcache.file_cache_only=0',
      '-S', "127.0.0.1:{$port}", '-t', $webroot, __DIR__ . '/http-router.php'],
    [STDIN, $log, $log],
    $pipes,
    $webroot
  );
  if (!is_resource($process)) throw new RuntimeException('Could not start PHP server');

  $ready = false;
  $startup_body = '';
  for ($attempt = 0; $attempt < 50; $attempt++) {
    usleep(100000);
    try {
      [$status, $startup_body] = http_request($base_url, $cookie_jar);
      if ($status === 200) {
        $ready = true;
        break;
      }
    } catch (Throwable $ignored) {
    }
  }
  if (!$ready) throw new RuntimeException('PHP server did not become ready');
  if (str_contains($startup_body, 'Please update') || str_contains($startup_body, '最新版に更新してください')) {
    throw new RuntimeException('Application startup failed: ' . trim(strip_tags($startup_body)));
  }

  $protected_probes = [
    'config.php' => 'admin_pass',
    'config.local.php' => 'integration-admin-pass',
    'config.local.php.bak' => 'config-backup-secret',
    'config.php.old' => 'old-config-secret',
    'config.local.php~' => 'editor-config-backup-secret',
    'reita.db' => 'SQLite format',
    'http-access-probe.db-wal' => 'sqlite-wal-secret',
    'http-access-probe.db-shm' => 'sqlite-shm-secret',
    'http-access-probe.db-journal' => 'sqlite-journal-secret',
    'theme/starter/theme_settings.db' => 'SQLite format',
    'theme/starter/theme.php' => 'extends',
    'theme/starter/theme.php.bak' => 'theme-backup-secret',
    'theme/eda/theme_conf.php.old' => 'theme-config-backup-secret',
    'theme/eda/theme_manifest.php~' => 'theme-manifest-backup-secret',
    'theme/eda/eda_main.twig' => 'DOCTYPE',
    'theme/eda/eda_main.twig.bak' => 'twig-backup-secret',
    'theme/eda/eda_main.twig~' => 'twig-editor-backup-secret',
    'theme/monoreita/monoreita_main.blade.php' => 'DOCTYPE',
    'theme/monoreita/monoreita_main.blade.php.bak' => 'blade-backup-secret',
    'thumbnail/.external-image-failures/http-access-probe.failure.dat' => 'external-failure-secret',
    'session/http-access-probe' => 'session-secret',
    'backup/http-access-probe.db' => 'backup-secret',
    'cache/http-access-probe.bladec' => 'blade-cache-secret',
    'errorlog/http-access-probe.log' => 'error-log-secret',
    'auditlog/http-access-probe.log' => 'audit-log-secret',
  ];
  foreach ($protected_probes as $relative_path => $secret) {
    $probe_path = $webroot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative_path);
    $probe_directory = dirname($probe_path);
    if (!is_dir($probe_directory)
      && !mkdir($probe_directory, 0700, true)
      && !is_dir($probe_directory)) {
      throw new RuntimeException("Could not create protected HTTP probe directory: {$relative_path}");
    }
    if (!is_file($probe_path) && file_put_contents($probe_path, $secret) === false) {
      throw new RuntimeException("Could not create protected HTTP probe: {$relative_path}");
    }
  }
  $protected_results = [];
  foreach ($protected_probes as $relative_path => $secret) {
    $encoded_path = implode('/', array_map('rawurlencode', explode('/', $relative_path)));
    [$probe_status, $probe_body] = http_request($origin_url . '/' . $encoded_path, $cookie_jar);
    $protected_results[$relative_path] = $probe_status === 403 && !str_contains($probe_body, $secret);
  }
  integration_test('private files and runtime directories reject HTTP access', static function () use ($protected_results): bool {
    return count($protected_results) === 25 && !in_array(false, $protected_results, true);
  });

  integration_test('new board creates versioned database', static function () use ($webroot): bool {
    $db = new PDO('sqlite:' . $webroot . '/reita.db');
    return (int)$db->query('PRAGMA user_version')->fetchColumn() === 1
      && (int)$db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='board_log'")->fetchColumn() === 1;
  });

  integration_test('application startup does not redefine database constants', static function () use ($startup_body): bool {
    return !str_contains($startup_body, 'Constant DB_FILE already defined')
      && !str_contains($startup_body, 'Constant DB_PDO already defined');
  });

  [$error_probe_status, $error_probe_body] = http_request($origin_url . '/error-probe.php', $cookie_jar);
  preg_match('/\\b\\d{14}-[a-f0-9]{8}\\b/', $error_probe_body, $error_id_match);
  $error_probe_id = (string)($error_id_match[0] ?? '');
  $error_log_contents = '';
  foreach (glob($webroot . '/errorlog/error-*.log') ?: [] as $error_log_file) {
    $error_log_contents .= (string)file_get_contents($error_log_file);
  }
  integration_test('PHP errors expose only an ID while private logs retain redacted diagnostics', static function () use (
    $error_probe_status, $error_probe_body, $error_probe_id, $error_log_contents, $webroot
  ): bool {
    return $error_probe_status === 500
      && $error_probe_id !== ''
      && str_contains($error_probe_body, $error_probe_id)
      && str_contains($error_probe_body, 'Date: ' . substr($error_probe_id, 0, 8))
      && !str_contains($error_probe_body, 'error-probe-secret')
      && !str_contains($error_probe_body, $webroot)
      && !str_contains($error_probe_body, 'RuntimeException')
      && str_contains($error_log_contents, $error_probe_id)
      && str_contains($error_log_contents, '"date":"' . substr($error_probe_id, 0, 8) . '"')
      && str_contains($error_log_contents, '[REDACTED]')
      && str_contains($error_log_contents, 'RuntimeException')
      && str_contains($error_log_contents, 'error-probe.php')
      && !str_contains($error_log_contents, 'error-probe-secret');
  });

  [$plain_error_status, $plain_error_body] = http_request($origin_url . '/plain-error-probe.php', $cookie_jar);
  preg_match('/\\b\\d{14}-[a-f0-9]{8}\\b/', $plain_error_body, $plain_error_id_match);
  $plain_error_id = (string)($plain_error_id_match[0] ?? '');
  $plain_error_log_contents = '';
  foreach (glob($webroot . '/errorlog/error-*.log') ?: [] as $error_log_file) {
    $plain_error_log_contents .= (string)file_get_contents($error_log_file);
  }
  integration_test('plain API 5xx responses hide details and log redacted diagnostics', static function () use (
    $plain_error_status, $plain_error_body, $plain_error_id, $plain_error_log_contents
  ): bool {
    return $plain_error_status === 502
      && $plain_error_id !== ''
      && str_contains($plain_error_body, $plain_error_id)
      && str_contains($plain_error_body, 'Date: ' . substr($plain_error_id, 0, 8))
      && !str_contains($plain_error_body, 'public-detail-must-not-appear')
      && !str_contains($plain_error_body, 'plain-api-secret')
      && !str_contains($plain_error_body, 'Misskey API')
      && str_contains($plain_error_log_contents, $plain_error_id)
      && str_contains($plain_error_log_contents, 'Misskey API: upstream failed token=[REDACTED]')
      && !str_contains($plain_error_log_contents, 'plain-api-secret');
  });

  $misskey_probe_cookie_jar = $root . DIRECTORY_SEPARATOR . 'misskey-probe-cookies.txt';
  [$misskey_image_status, $misskey_image_body] = http_request(
    $origin_url . '/misskey-missing-image-probe.php', $misskey_probe_cookie_jar
  );
  $misskey_image_log_safe = false;
  foreach (glob($webroot . '/errorlog/error-*.log') ?: [] as $error_log_file) {
    foreach (file($error_log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $log_line) {
      if (!str_contains($log_line, 'Misskey upload source image was missing.')) continue;
      $misskey_image_log_safe = !str_contains($log_line, $webroot)
        && !str_contains($log_line, 'missing-probe.png');
    }
  }
  integration_test('Misskey missing-image errors do not expose an absolute server path', static function () use (
    $misskey_image_status, $misskey_image_body, $misskey_image_log_safe, $webroot
  ): bool {
    return $misskey_image_status === 404
      && $misskey_image_body === 'Error: Image does not exist.'
      && !str_contains($misskey_image_body, $webroot)
      && !str_contains($misskey_image_body, 'missing-probe.png')
      && $misskey_image_log_safe;
  });

  [$misskey_callback_status, $misskey_callback_body] = http_request(
    $origin_url . '/connect_misskey_api.php', $cookie_jar
  );
  $misskey_callback_logged = false;
  foreach (glob($webroot . '/errorlog/error-*.log') ?: [] as $error_log_file) {
    $contents = (string)file_get_contents($error_log_file);
    if (str_contains($contents, '"http_status":400')
      && str_contains($contents, 'Misskey API: Misskey callback session was missing.')) {
      $misskey_callback_logged = true;
      break;
    }
  }
  integration_test('Misskey callback records standalone failures without exposing internals', static function () use (
    $misskey_callback_status, $misskey_callback_body, $misskey_callback_logged
  ): bool {
    return $misskey_callback_status === 400
      && str_contains($misskey_callback_body, 'Misskey posting session is missing')
      && $misskey_callback_logged
      && !str_contains($misskey_callback_body, 'Fatal error')
      && !str_contains($misskey_callback_body, 'Class &quot;Database&quot; not found')
      && !str_contains($misskey_callback_body, 'Class &quot;RequestSecurity&quot; not found');
  });

  [$missing_continue_status, $missing_continue_body] = http_request($base_url . '?mode=continue&no=1784', $cookie_jar);
  integration_test('missing continuation image shows a normal error page', static function () use ($missing_continue_status, $missing_continue_body): bool {
    return $missing_continue_status === 404
      && str_contains($missing_continue_body, 'The image does not exist.')
      && !str_contains($missing_continue_body, 'Undefined variable')
      && !str_contains($missing_continue_body, 'foreach() argument must be of type');
  });

  // pictmp initializes the CSRF token in the same session used for posting.
  [$status, $pictmp_body] = http_request($base_url . '?mode=pictmp', $cookie_jar);
  $session_id = cookie_value($cookie_jar, 'noreita_session');
  $token = $session_id === null ? '' : hash('sha256', $session_id);
  $upload_mimes = [];
  $upload_labels = [];
  foreach ([
    'image/png' => ['PNG', 'imagecreatefrompng'],
    'image/jpeg' => ['JPEG', 'imagecreatefromjpeg'],
    'image/gif' => ['GIF', 'imagecreatefromgif'],
    'image/webp' => ['WebP', 'imagecreatefromwebp'],
    'image/avif' => ['AVIF', 'imagecreatefromavif'],
  ] as $mime => [$label, $decoder]) {
    if (!function_exists($decoder)) continue;
    $upload_mimes[] = $mime;
    $upload_labels[] = $label;
  }
  integration_test('image upload form lists only formats supported by GD', static function () use (
    $status, $pictmp_body, $upload_mimes, $upload_labels
  ): bool {
    return $status === 200
      && str_contains($pictmp_body, 'name="image_upload"')
      && str_contains($pictmp_body, 'accept="' . implode(',', $upload_mimes) . '"')
      && str_contains($pictmp_body, implode(' / ', $upload_labels));
  });

  integration_test('animation upload uses the normal post submit action', static function () use (
    $status, $pictmp_body
  ): bool {
    return $status === 200
      && str_contains($pictmp_body, 'data-animation-upload-file')
      && str_contains($pictmp_body, 'name="animation_upload"')
      && str_contains($pictmp_body, 'accept=".pch,.tgkr"')
      && str_contains($pictmp_body, '選択するとプレビューを生成します')
      && !str_contains($pictmp_body, 'data-animation-upload-button')
      && str_contains($pictmp_body, 'animation-upload.js?');
  });

  $oversized_paint = $root . DIRECTORY_SEPARATOR . 'oversized-paint.png';
  if (file_put_contents($oversized_paint, str_repeat('x', 1536)) !== 1536) {
    throw new RuntimeException('Could not create oversized drawing upload probe.');
  }
  $temporary_before_capacity_test = glob($webroot . '/tmp/*') ?: [];
  [$paint_capacity_status, $paint_capacity_body] = http_request(
    $base_url . '?mode=saveimage&tool=neo',
    $cookie_jar,
    [
      'header' => 'stime=1',
      'picture' => new CURLFile($oversized_paint, 'image/png', 'oversized-paint.png'),
    ]
  );
  $temporary_after_capacity_test = glob($webroot . '/tmp/*') ?: [];
  $paint_capacity_logged = false;
  foreach (glob($webroot . '/errorlog/error-*.log') ?: [] as $error_log_file) {
    $contents = (string)file_get_contents($error_log_file);
    if (str_contains($contents, '"http_status":413')
      && str_contains($contents, 'drawing upload is too large')) {
      $paint_capacity_logged = true;
      break;
    }
  }
  integration_test('drawing save API rejects oversized uploads without creating temporary files', static function () use (
    $paint_capacity_status, $paint_capacity_body, $temporary_before_capacity_test,
    $temporary_after_capacity_test, $paint_capacity_logged
  ): bool {
    return $paint_capacity_status === 413
      && str_contains($paint_capacity_body, 'drawing upload is too large')
      && $temporary_after_capacity_test === $temporary_before_capacity_test
      && $paint_capacity_logged;
  });

  $invalid_paint = $root . DIRECTORY_SEPARATOR . 'invalid-paint.png';
  if (file_put_contents($invalid_paint, 'not-a-png') !== 9) {
    throw new RuntimeException('Could not create invalid drawing upload probe.');
  }
  $temporary_before_invalid_paint = glob($webroot . '/tmp/*') ?: [];
  [$invalid_paint_status, $invalid_paint_body] = http_request(
    $base_url . '?mode=saveimage&tool=neo',
    $cookie_jar,
    [
      'header' => 'stime=1',
      'picture' => new CURLFile($invalid_paint, 'image/png', 'invalid-paint.png'),
    ]
  );
  $temporary_after_invalid_paint = glob($webroot . '/tmp/*') ?: [];
  $invalid_paint_logged = false;
  foreach (glob($webroot . '/errorlog/error-*.log') ?: [] as $error_log_file) {
    $contents = (string)file_get_contents($error_log_file);
    if (str_contains($contents, '"http_status":415')
      && str_contains($contents, 'Drawing save API: Your picture upload failed!')) {
      $invalid_paint_logged = true;
      break;
    }
  }
  integration_test('drawing save API records non-capacity errors and preserves its response protocol', static function () use (
    $invalid_paint_status, $invalid_paint_body, $temporary_before_invalid_paint,
    $temporary_after_invalid_paint, $invalid_paint_logged
  ): bool {
    return $invalid_paint_status === 415
      && str_starts_with($invalid_paint_body, "error\nYour picture upload failed!")
      && $temporary_after_invalid_paint === $temporary_before_invalid_paint
      && $invalid_paint_logged;
  });

  [$litachix_error_status, $litachix_error_body, , $litachix_error_headers] = http_request(
    $base_url . '?mode=saveimage&tool=chi&stime=1', $cookie_jar, ['header' => 'stime=1']
  );
  preg_match('/\b\d{14}-[a-f0-9]{8}\b/', $litachix_error_body, $litachix_error_id_match);
  $litachix_error_id = (string)($litachix_error_id_match[0] ?? '');
  $litachix_error_logged = false;
  foreach (glob($webroot . '/errorlog/error-*.log') ?: [] as $error_log_file) {
    $contents = (string)file_get_contents($error_log_file);
    if (str_contains($contents, $litachix_error_id) && str_contains($contents, '"http_status":400')) {
      $litachix_error_logged = true;
      break;
    }
  }
  integration_test('LitaChix displays a logged drawing error reference despite its non-2xx response limitation', static function () use (
    $litachix_error_status, $litachix_error_body, $litachix_error_headers,
    $litachix_error_id, $litachix_error_logged
  ): bool {
    return $litachix_error_status === 200
      && ($litachix_error_headers['x-noreita-error-status'] ?? '') === '400'
      && str_starts_with($litachix_error_body, 'CHIBIERROR ')
      && $litachix_error_id !== ''
      && str_contains($litachix_error_body, $litachix_error_id)
      && $litachix_error_logged;
  });

  $animation_png = $root . DIRECTORY_SEPARATOR . 'animation-upload.png';
  $animation_png_data = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
  if ($animation_png_data === false || file_put_contents($animation_png, $animation_png_data) === false) {
    throw new RuntimeException('Could not create animation upload PNG.');
  }
  $litachix_chi = $root . DIRECTORY_SEPARATOR . 'litachix-work.chi';
  $litachix_swatches = $root . DIRECTORY_SEPARATOR . 'litachix-swatches.aco';
  if (file_put_contents($litachix_chi, "CHIBI\0") === false
    || file_put_contents($litachix_swatches, "ACO\0") === false) {
    throw new RuntimeException('Could not create LitaChix upload probes.');
  }
  $litachix_temporary_before = glob($webroot . '/tmp/*') ?: [];
  [$litachix_three_file_status, $litachix_three_file_body] = http_request(
    $base_url . '?mode=saveimage&tool=chi&stime=1',
    $cookie_jar,
    [
      'picture' => new CURLFile($animation_png, 'image/png', 'drawing.png'),
      'chibifile' => new CURLFile($litachix_chi, 'application/octet-stream', 'drawing.chi'),
      'swatches' => new CURLFile($litachix_swatches, 'application/octet-stream', 'palette.aco'),
    ]
  );
  $litachix_temporary_after = glob($webroot . '/tmp/*') ?: [];
  $litachix_created_files = array_values(array_diff($litachix_temporary_after, $litachix_temporary_before));
  foreach ($litachix_created_files as $created_file) @unlink($created_file);
  integration_test('LitaChix accepts its PNG, CHI, and swatches upload fields', static function () use (
    $litachix_three_file_status, $litachix_three_file_body, $litachix_created_files
  ): bool {
    return $litachix_three_file_status === 200
      && $litachix_three_file_body === "CHIBIOK\n"
      && count($litachix_created_files) >= 2;
  });
  $mismatched_pch = $root . DIRECTORY_SEPARATOR . 'mismatched.pch';
  $valid_pch = $root . DIRECTORY_SEPARATOR . 'valid.pch';
  file_put_contents($mismatched_pch, "NEO\0" . pack('v', 2) . pack('v', 1) . "\0\0\0\0x");
  file_put_contents($valid_pch, "NEO\0" . pack('v', 1) . pack('v', 1) . "\0\0\0\0x");
  [$unconverted_animation_status, $unconverted_animation_body] = http_request(
    $base_url . '?mode=regist',
    $cookie_jar,
    [
      'mode' => 'regist', 'send' => '1', 'name' => 'Animation fallback', 'mail' => '', 'url' => '',
      'sub' => 'Unconverted animation', 'com' => '未変換動画の結合テストです。', 'pwd' => 'animation-pass',
      'invz' => '0', 'sodane' => '0', 'nsfw' => '0', 'token' => $token,
      'animation_upload' => new CURLFile($valid_pch, 'application/octet-stream', 'valid.pch'),
    ]
  );
  integration_test('normal posting rejects an animation when browser conversion did not run', static function () use (
    $unconverted_animation_status, $unconverted_animation_body
  ): bool {
    return $unconverted_animation_status === 422
      && str_contains($unconverted_animation_body, 'animation could not be checked');
  });
  [$mixed_upload_status, $mixed_upload_body] = http_request(
    $base_url . '?mode=regist',
    $cookie_jar,
    [
      'mode' => 'regist', 'send' => '1', 'name' => 'Mixed upload', 'mail' => '', 'url' => '',
      'sub' => 'Mixed upload', 'com' => '画像と動画を同時に選んだ結合テストです。', 'pwd' => 'mixed-upload-pass',
      'invz' => '0', 'sodane' => '0', 'nsfw' => '0', 'token' => $token,
      'image_upload' => new CURLFile($animation_png, 'image/png', 'image.png'),
      'animation_upload' => new CURLFile($valid_pch, 'application/octet-stream', 'animation.pch'),
    ]
  );
  integration_test('normal posting explains that image and animation uploads are exclusive', static function () use (
    $mixed_upload_status, $mixed_upload_body
  ): bool {
    return $mixed_upload_status === 400
      && str_contains($mixed_upload_body, 'Choose either an image or an animation file.');
  });
  $temporary_before_mismatch = glob($webroot . '/tmp/*') ?: [];
  [$animation_mismatch_status] = http_request($base_url . '?mode=animation_upload', $cookie_jar, [
    'token' => $token,
    'picture' => new CURLFile($animation_png, 'image/png', 'animation.png'),
    'animation' => new CURLFile($mismatched_pch, 'application/octet-stream', 'mismatched.pch'),
  ]);
  $temporary_after_mismatch = glob($webroot . '/tmp/*') ?: [];
  integration_test('animation upload rejects a PNG whose dimensions do not match its PCH', static function () use (
    $animation_mismatch_status, $temporary_before_mismatch, $temporary_after_mismatch
  ): bool {
    return $animation_mismatch_status === 422
      && $temporary_after_mismatch === $temporary_before_mismatch;
  });

  [$animation_upload_status, $animation_upload_body] = http_request(
    $base_url . '?mode=animation_upload',
    $cookie_jar,
    [
      'token' => $token,
      'picture' => new CURLFile($animation_png, 'image/png', 'animation.png'),
      'animation' => new CURLFile($valid_pch, 'application/octet-stream', 'valid.pch'),
    ]
  );
  $animation_upload_lines = preg_split('/\r?\n/', trim($animation_upload_body)) ?: [];
  $uploaded_animation_image = (string)($animation_upload_lines[1] ?? '');
  $uploaded_animation_base = pathinfo($uploaded_animation_image, PATHINFO_FILENAME);
  integration_test('animation upload stores a generated PNG and NEO replay as one pending image', static function () use (
    $animation_upload_status, $animation_upload_lines, $uploaded_animation_image,
    $uploaded_animation_base, $webroot
  ): bool {
    return $animation_upload_status === 200
      && ($animation_upload_lines[0] ?? '') === 'ok'
      && preg_match('/^\d{16}\.png$/D', $uploaded_animation_image) === 1
      && is_file($webroot . '/tmp/' . $uploaded_animation_image)
      && is_file($webroot . '/tmp/' . $uploaded_animation_base . '.pch')
      && is_file($webroot . '/tmp/' . $uploaded_animation_base . '.dat');
  });

  [$animation_post_status] = http_request($base_url . '?mode=regist', $cookie_jar, [
    'mode' => 'regist', 'send' => '1', 'name' => 'Animation upload', 'mail' => '', 'url' => '',
    'sub' => 'Animation upload', 'com' => '動画アップロードの結合テストです。', 'pwd' => 'animation-pass',
    'picfile' => $uploaded_animation_image, 'ctype' => 'new', 'invz' => '0', 'sodane' => '0',
    'nsfw' => '0', 'token' => $token,
  ]);
  $animation_db = new PDO('sqlite:' . $webroot . '/reita.db');
  $animation_row = $animation_db->query(
    "SELECT picfile, pchfile, tool FROM board_log WHERE sub = 'Animation upload' ORDER BY tid DESC LIMIT 1"
  )->fetch(PDO::FETCH_ASSOC);
  integration_test('uploaded animation remains playable after posting', static function () use (
    $animation_post_status, $animation_row, $uploaded_animation_image, $uploaded_animation_base, $webroot
  ): bool {
    return $animation_post_status === 200 && is_array($animation_row)
      && $animation_row['picfile'] === $uploaded_animation_image
      && $animation_row['pchfile'] === $uploaded_animation_base . '.pch'
      && $animation_row['tool'] === 'PaintBBS NEO'
      && is_file($webroot . '/img/' . $uploaded_animation_image)
      && is_file($webroot . '/img/' . $uploaded_animation_base . '.pch');
  });

  $valid_tgkr = $root . DIRECTORY_SEPARATOR . 'valid.tgkr';
  file_put_contents($valid_tgkr, 'TGK' . chr(1) . pack('N', 1) . "\1\0\0\1x");
  [$tgkr_upload_status, $tgkr_upload_body] = http_request(
    $base_url . '?mode=animation_upload',
    $cookie_jar,
    [
      'token' => $token,
      'picture' => new CURLFile($animation_png, 'image/png', 'animation.png'),
      'animation' => new CURLFile($valid_tgkr, 'application/octet-stream', 'valid.tgkr'),
    ]
  );
  $tgkr_upload_lines = preg_split('/\r?\n/', trim($tgkr_upload_body)) ?: [];
  $tgkr_image = (string)($tgkr_upload_lines[1] ?? '');
  $tgkr_base = pathinfo($tgkr_image, PATHINFO_FILENAME);
  [$tgkr_post_status] = http_request($base_url . '?mode=regist', $cookie_jar, [
    'mode' => 'regist', 'send' => '1', 'name' => 'TGKR upload', 'mail' => '', 'url' => '',
    'sub' => 'TGKR upload', 'com' => 'TGKRアップロードの結合テストです。', 'pwd' => 'tgkr-pass',
    'picfile' => $tgkr_image, 'ctype' => 'new', 'invz' => '0', 'sodane' => '0',
    'nsfw' => '0', 'token' => $token,
  ]);
  $tgkr_row = $animation_db->query(
    "SELECT picfile, pchfile, tool FROM board_log WHERE sub = 'TGKR upload' ORDER BY tid DESC LIMIT 1"
  )->fetch(PDO::FETCH_ASSOC);
  integration_test('Tegaki-enabled boards retain an uploaded TGKR after posting', static function () use (
    $tgkr_upload_status, $tgkr_post_status, $tgkr_row, $tgkr_image, $tgkr_base, $webroot
  ): bool {
    return $tgkr_upload_status === 200 && $tgkr_post_status === 200 && is_array($tgkr_row)
      && $tgkr_row['picfile'] === $tgkr_image && $tgkr_row['pchfile'] === $tgkr_base . '.tgkr'
      && $tgkr_row['tool'] === 'Tegaki.js'
      && is_file($webroot . '/img/' . $tgkr_image)
      && is_file($webroot . '/img/' . $tgkr_base . '.tgkr');
  });

  [$misskey_loopback_status] = http_request($base_url . '?mode=create_misskey_authrequesturl', $cookie_jar, [
    'mode' => 'create_misskey_authrequesturl', 'misskey_server_radio' => 'direct',
    'misskey_server_direct_input' => 'https://127.0.0.1',
  ]);
  [$misskey_metadata_status] = http_request($base_url . '?mode=create_misskey_authrequesturl', $cookie_jar, [
    'mode' => 'create_misskey_authrequesturl', 'misskey_server_radio' => 'direct',
    'misskey_server_direct_input' => 'https://169.254.169.254',
  ]);
  [$misskey_port_status] = http_request($base_url . '?mode=create_misskey_authrequesturl', $cookie_jar, [
    'mode' => 'create_misskey_authrequesturl', 'misskey_server_radio' => 'direct',
    'misskey_server_direct_input' => 'https://misskey.io:8443',
  ]);
  integration_test('Misskey direct server input rejects SSRF destinations', static function () use (
    $misskey_loopback_status, $misskey_metadata_status, $misskey_port_status
  ): bool {
    return $misskey_loopback_status === 400
      && $misskey_metadata_status === 400
      && $misskey_port_status === 400;
  });

  [$admin_unauthorized_status] = http_request($base_url . '?mode=admin', $cookie_jar);
  [$admin_errorlog_unauthorized_status] = http_request($base_url . '?mode=admin_errorlog', $cookie_jar);
  [$admin_auditlog_unauthorized_status] = http_request($base_url . '?mode=admin_auditlog', $cookie_jar);
  [$admin_temporary_images_unauthorized_status] = http_request($base_url . '?mode=admin_temporary_images', $cookie_jar);
  [$admin_detail_unauthorized_status] = http_request($base_url . '?mode=admin_post&id=1', $cookie_jar);
  [$admin_edit_unauthorized_status] = http_request($base_url . '?mode=admin_edit&id=1', $cookie_jar);
  [$admin_manage_unauthorized_status] = http_request($base_url . '?mode=admin_manage', $cookie_jar, [
    'operation' => 'hide', 'delno' => ['1'], 'token' => $token,
  ]);
  integration_test('administration routes require a login session', static function () use (
    $admin_unauthorized_status, $admin_errorlog_unauthorized_status, $admin_auditlog_unauthorized_status,
    $admin_temporary_images_unauthorized_status, $admin_detail_unauthorized_status,
    $admin_edit_unauthorized_status, $admin_manage_unauthorized_status
  ): bool {
    return $admin_unauthorized_status === 403
      && $admin_errorlog_unauthorized_status === 403
      && $admin_auditlog_unauthorized_status === 403
      && $admin_temporary_images_unauthorized_status === 403
      && $admin_detail_unauthorized_status === 403
      && $admin_edit_unauthorized_status === 403
      && $admin_manage_unauthorized_status === 403;
  });

  [$admin_login_form_status, $admin_login_form_body] = http_request($base_url . '?mode=admin_in', $cookie_jar);
  integration_test('administrator login form contains a CSRF token', static function () use ($admin_login_form_status, $admin_login_form_body, $token): bool {
    return $admin_login_form_status === 200
      && str_contains($admin_login_form_body, 'mode=admin_login')
      && str_contains($admin_login_form_body, 'name="token" value="' . $token . '"');
  });

  [$admin_wrong_password_status] = http_request($base_url . '?mode=admin_login', $cookie_jar, [
    'adminpass' => 'wrong-admin-pass', 'token' => $token,
  ]);
  integration_test('administrator login rejects a wrong password', static function () use ($admin_wrong_password_status): bool {
    return $admin_wrong_password_status === 403;
  });

  [$admin_login_status] = http_request($base_url . '?mode=admin_login', $cookie_jar, [
    'adminpass' => 'integration-admin-pass', 'token' => $token,
  ]);
  $admin_session_id = cookie_value($cookie_jar, 'noreita_session');
  $token = $admin_session_id === null ? '' : hash('sha256', $admin_session_id);
  $login_attempt_records_after_success = glob($webroot . '/session/admin-login-*.json') ?: [];
  [$admin_status, $admin_body] = http_request($base_url . '?mode=admin', $cookie_jar);
  [$admin_public_status, $admin_public_body] = http_request($base_url, $cookie_jar);
  integration_test('public pages visibly indicate an active administrator session', static function () use (
    $startup_body, $admin_public_status, $admin_public_body
  ): bool {
    return !str_contains($startup_body, '管理者ログイン中')
      && str_contains($startup_body, 'mode=admin_in')
      && $admin_public_status === 200
      && str_contains($admin_public_body, 'class="admin-session-status"')
      && str_contains($admin_public_body, '管理者ログイン中')
      && str_contains($admin_public_body, 'mode=admin');
  });
  integration_test('administrator login persists and clears prior failures', static function () use (
    $admin_login_status, $admin_status, $admin_body, $login_attempt_records_after_success
  ): bool {
    $admin_css_position = strpos($admin_body, 'css/eda_admin.css');
    $custom_css_position = strpos($admin_body, 'theme/starter/theme.css?v=1.0.0-');
    return $admin_login_status === 302 && $admin_status === 200
      && $login_attempt_records_after_success === []
      && str_contains($admin_body, 'ADMIN MODE')
      && str_contains($admin_body, 'id="eda-theme-color-form"')
      && str_contains($admin_body, 'css/eda_admin.css')
      && str_contains($admin_body, 'id="eda-theme-color-preset"')
      && str_contains($admin_body, 'value="dark">dark</option>')
      && str_contains($admin_body, 'themeColorManager.js')
      && str_contains($admin_body, 'EDA_THEME_COLOR_PRESETS')
      && str_contains($admin_body, 'css/mono/eda.min.css')
      && str_contains($admin_body, 'theme/starter/theme.css?v=1.0.0-')
      && $admin_css_position !== false && $custom_css_position !== false
      && $admin_css_position < $custom_css_position
      && !str_contains($admin_body, 'switchcss.js')
      && !str_contains($admin_body, 'css/reita/eda.min.css')
      && str_contains($admin_body, 'mode=admin_theme_settings')
      && str_contains($admin_body, '基本統計')
      && str_contains($admin_body, '総投稿数')
      && str_contains($admin_body, '画像ディレクトリ:')
      && str_contains($admin_body, 'mode=admin_logout')
      && str_contains($admin_body, 'mode=admin_errorlog')
      && str_contains($admin_body, 'mode=admin_auditlog')
      && str_contains($admin_body, 'mode=admin_temporary_images')
      && str_contains($admin_body, 'mode=admin_manage')
      && str_contains($admin_body, 'value="hide"')
      && str_contains($admin_body, 'value="show"')
      && str_contains($admin_body, 'value="delete"');
  });

  [$admin_errorlog_status, $admin_errorlog_body] = http_request(
    $base_url . '?mode=admin_errorlog&log_status=4xx', $cookie_jar
  );
  integration_test('administrator can view filtered error logs without exposing files directly', static function () use (
    $admin_errorlog_status, $admin_errorlog_body
  ): bool {
    return $admin_errorlog_status === 200
      && str_contains($admin_errorlog_body, '管理者向けエラーログ')
      && str_contains($admin_errorlog_body, 'http-client-error')
      && str_contains($admin_errorlog_body, '403');
  });

  [$admin_auditlog_status, $admin_auditlog_body] = http_request(
    $base_url . '?mode=admin_auditlog', $cookie_jar
  );
  $error_storage = '';
  foreach (glob($webroot . '/errorlog/error-*.log') ?: [] as $error_file) {
    $error_storage .= (string)file_get_contents($error_file);
  }
  $audit_storage = '';
  foreach (glob($webroot . '/auditlog/audit-*.log') ?: [] as $audit_file) {
    $audit_storage .= (string)file_get_contents($audit_file);
  }
  integration_test('administrator audits use storage independent from HTTP error logs', static function () use (
    $admin_auditlog_status, $admin_auditlog_body, $error_storage, $audit_storage
  ): bool {
    return $admin_auditlog_status === 200
      && str_contains($admin_auditlog_body, '管理操作の監査ログ')
      && str_contains($admin_auditlog_body, 'Administrator action: login')
      && str_contains($audit_storage, '"type":"admin-audit"')
      && str_contains($audit_storage, '"audit_action":"login"')
      && !str_contains($audit_storage, '"type":"http-client-error"')
      && !str_contains($error_storage, '"type":"admin-audit"');
  });

  $theme_colors = [
    'pageBackground' => '#123456', 'pageBackgroundEnd' => '#000000',
    'text' => '#eeeeee', 'link' => '#eeeeee', 'linkVisited' => '#999999', 'linkAction' => '#cc0000',
    'surface' => '#112222', 'border' => '#992222',
    'buttonBorder' => '#111111', 'buttonBorderInset' => '#222222',
    'button' => '#333355', 'buttonText' => '#ffffff',
    'inputBackground' => '#eeeeee', 'inputText' => '#000000',
    'threadBackground' => '#001122', 'threadText' => '#ddffee',
    'noticeBackground' => '#554433', 'replyText' => '#cc88cc',
  ];
  [$theme_save_status] = http_request($base_url . '?mode=admin_theme_settings', $cookie_jar, [
    'operation' => 'save', 'theme_settings' => ['colors' => $theme_colors], 'token' => $token,
  ]);
  $theme_database = new PDO('sqlite:' . $webroot . '/theme/starter/theme_settings.db');
  $stored_theme_colors = (string)$theme_database->query(
    "SELECT value FROM theme_settings WHERE setting_key = 'colors'"
  )->fetchColumn();
  [$theme_render_status, $theme_render_body] = http_request($base_url . '?mode=admin', $cookie_jar);
  [$theme_reset_status] = http_request($base_url . '?mode=admin_theme_settings', $cookie_jar, [
    'operation' => 'reset', 'token' => $token,
  ]);
  $theme_color_rows_after_reset = (int)$theme_database->query('SELECT COUNT(*) FROM theme_settings')->fetchColumn();
  integration_test('administrator saves eda theme colors in the separate theme database', static function () use (
    $theme_save_status, $stored_theme_colors, $theme_render_status, $theme_render_body,
    $theme_reset_status, $theme_color_rows_after_reset
  ): bool {
    return $theme_save_status === 302
      && str_contains($stored_theme_colors, '"pageBackground":"#123456"')
      && $theme_render_status === 200 && str_contains($theme_render_body, '"pageBackground":"#123456"')
      && $theme_reset_status === 302
      && $theme_color_rows_after_reset === 0;
  });

  [$admin_empty_operation_status] = http_request($base_url . '?mode=admin_manage', $cookie_jar, [
    'operation' => 'hide', 'token' => $token,
  ]);
  [$admin_invalid_operation_status] = http_request($base_url . '?mode=admin_manage', $cookie_jar, [
    'operation' => 'invalid', 'token' => $token,
  ]);
  [$admin_missing_post_status] = http_request($base_url . '?mode=admin_manage', $cookie_jar, [
    'operation' => 'hide', 'delno' => ['999999'], 'token' => $token,
  ]);
  integration_test('administrator bulk operation validates selection and operation', static function () use (
    $admin_empty_operation_status, $admin_invalid_operation_status, $admin_missing_post_status
  ): bool {
    return $admin_empty_operation_status === 400
      && $admin_invalid_operation_status === 400
      && $admin_missing_post_status === 404;
  });

  $share_title = '共有テスト';
  $share_target = 'https://example.com/post/1';
  [$share_form_status, $share_form_body] = http_request(
    $base_url . '?mode=set_share_server&encoded_t=' . rawurlencode($share_title) . '&encoded_u=' . rawurlencode($share_target),
    $cookie_jar
  );
  integration_test('share destination form is rendered through HTTP', static function () use ($share_form_status, $share_form_body, $token): bool {
    return $share_form_status === 200 && str_contains($share_form_body, 'sns_server_radio')
      && str_contains($share_form_body, 'name="token" value="' . $token . '"');
  });

  [$share_status, , $share_redirect] = http_request($base_url, $cookie_jar, [
    'mode' => 'post_share_server', 'sns_server_radio' => 'https://bsky.app',
    'sns_server_direct_input' => '', 'encoded_t' => $share_title, 'encoded_u' => $share_target,
    'token' => $token,
  ]);
  integration_test('share destination redirects with CSRF validation', static function () use ($share_status, $share_redirect, $share_title, $share_target): bool {
    return $share_status === 302
      && $share_redirect === 'https://bsky.app/intent/compose?text=' . rawurlencode($share_title . ' ' . $share_target);
  });

  [$invalid_csrf_status, $invalid_csrf_body] = http_request($base_url, $cookie_jar, [
    'mode' => 'post_share_server', 'sns_server_radio' => 'https://bsky.app',
    'sns_server_direct_input' => '', 'encoded_t' => $share_title, 'encoded_u' => $share_target,
    'token' => 'invalid-token',
  ]);
  $http_error_log_contents = '';
  foreach (glob($webroot . '/errorlog/error-*.log') ?: [] as $error_log_file) {
    $http_error_log_contents .= (string)file_get_contents($error_log_file);
  }
  integration_test('invalid CSRF token is rejected and logged through HTTP', static function () use (
    $invalid_csrf_status, $invalid_csrf_body, $http_error_log_contents
  ): bool {
    return $invalid_csrf_status === 403 && str_contains($invalid_csrf_body, 'CSRF token mismatch')
      && str_contains($http_error_log_contents, '"type":"http-client-error"')
      && str_contains($http_error_log_contents, '"http_status":403');
  });

  $marker = 'integration-' . bin2hex(random_bytes(6));
  $raw_trip_name = "Integration O'Brien#integration-trip";
  [$post_status, $post_body] = http_request($base_url . '?mode=regist', $cookie_jar, [
    'mode' => 'regist', 'send' => '1', 'name' => $raw_trip_name, 'mail' => '', 'url' => '',
    'sub' => "Integration's subject", 'com' => "結合テスト user's {$marker}", 'pwd' => 'delete-pass',
    'invz' => '0', 'img_w' => '0', 'img_h' => '0', 'sodane' => '0', 'nsfw' => '0', 'token' => $token,
  ]);

  $db = new PDO('sqlite:' . $webroot . '/reita.db');
  $row = $db->query('SELECT tid, a_name, sub, com FROM board_log ORDER BY tid DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
  integration_test('post stores a displayed trip while retaining its original name in the cookie', static function () use (
    $status, $post_status, $row, $marker, $cookie_jar, $raw_trip_name
  ): bool {
    return $status === 200 && $post_status === 200 && is_array($row)
      && str_starts_with((string)$row['a_name'], "Integration O'Brien ◆")
      && !str_contains((string)$row['a_name'], '#integration-trip')
      && $row['sub'] === "Integration's subject"
      && $row['com'] === "結合テスト user's {$marker}"
      && urldecode((string)cookie_value($cookie_jar, 'name_c')) === $raw_trip_name;
  });

  $shared_thread_id = (int)($row['tid'] ?? 0);
  $sodane_count = static fn(): int => (int)$db->query('SELECT sodane FROM board_log WHERE tid = ' . $shared_thread_id)->fetchColumn();
  foreach ([
    'GET' => [null, 405],
    'missing token' => [['resto' => (string)$shared_thread_id], 403],
    'wrong token' => [['resto' => (string)$shared_thread_id, 'token' => 'invalid'], 403],
  ] as $case => [$payload, $expected_status]) {
    $before_count = $sodane_count();
    [$reaction_status] = http_request($base_url . '?mode=sodane&resto=' . $shared_thread_id, $cookie_jar, $payload);
    integration_test('sodane rejects ' . $case . ' without incrementing', static function () use (
      $reaction_status, $expected_status, $before_count, $sodane_count
    ): bool {
      return $reaction_status === $expected_status && $sodane_count() === $before_count;
    });
  }
  $before_count = $sodane_count();
  [$reaction_status] = http_request($base_url . '?mode=sodane', $cookie_jar, [
    'resto' => (string)$shared_thread_id, 'token' => $token,
  ]);
  integration_test('sodane accepts a POST with a valid CSRF token', static function () use (
    $reaction_status, $before_count, $sodane_count
  ): bool {
    return $reaction_status === 302 && $sodane_count() === $before_count + 1;
  });
  [$shared_thread_status, $shared_thread_body] = http_request(
    $base_url . '?resno=' . $shared_thread_id, $cookie_jar
  );
  [$legacy_thread_status, $legacy_thread_body] = http_request(
    $base_url . '?mode=res&res=' . $shared_thread_id, $cookie_jar
  );
  integration_test('thread links use the shared format and accept legacy URLs', static function () use (
    $shared_thread_status, $shared_thread_body, $legacy_thread_status, $legacy_thread_body, $marker
  ): bool {
    return $shared_thread_status === 200 && $legacy_thread_status === 200
      && str_contains($shared_thread_body, $marker)
      && str_contains($legacy_thread_body, $marker);
  });

  $api_url = substr($base_url, 0, -strlen('index.php')) . 'api.php';
  [$api_threads_status, $api_threads_body, , $api_threads_headers] = http_request(
    $api_url . '?mode=threads&per_page=1', $cookie_jar
  );
  [$api_thread_status, $api_thread_body] = http_request(
    $api_url . '?mode=thread&id=' . $shared_thread_id, $cookie_jar
  );
  [$api_search_status, $api_search_body] = http_request(
    $api_url . '?mode=search&q=' . rawurlencode($marker), $cookie_jar
  );
  integration_test('public JSON API exposes visible posts without private fields', static function () use (
    $api_threads_status, $api_threads_body, $api_threads_headers, $api_thread_status, $api_thread_body,
    $api_search_status, $api_search_body, $shared_thread_id, $marker
  ): bool {
    $threads = json_decode($api_threads_body, true);
    $thread = json_decode($api_thread_body, true);
    $search = json_decode($api_search_body, true);
    $item = is_array($threads) ? ($threads['items'][0] ?? null) : null;
    return $api_threads_status === 200 && $api_thread_status === 200 && $api_search_status === 200
      && ($api_threads_headers['content-type'] ?? '') === 'application/json; charset=UTF-8'
      && is_array($item) && (int)($item['id'] ?? 0) > 0
      && !array_key_exists('pwd', $item) && !array_key_exists('host', $item) && !array_key_exists('id_code', $item)
      && is_array($thread) && (int)($thread['thread']['id'] ?? 0) === $shared_thread_id
      && is_array($search) && str_contains(json_encode($search, JSON_UNESCAPED_UNICODE) ?: '', $marker);
  });

  $subject_escape_probe = '<b>XSS</b>';
  $subject_escape_stmt = $db->prepare('UPDATE board_log SET sub = :sub WHERE tid = :tid');
  $subject_escape_stmt->execute([':sub' => $subject_escape_probe, ':tid' => (int)($row['tid'] ?? 0)]);
  [$subject_escape_status, $subject_escape_body] = http_request($base_url . '?mode=admin', $cookie_jar);
  $subject_escape_stmt->execute([':sub' => "Integration's subject", ':tid' => (int)($row['tid'] ?? 0)]);
  integration_test('administration list escapes truncated post subjects', static function () use (
    $subject_escape_status, $subject_escape_body
  ): bool {
    return $subject_escape_status === 200
      && str_contains($subject_escape_body, '&lt;b&gt;XSS')
      && !str_contains($subject_escape_body, '<b>XSS');
  });

  $search_term = "user's {$marker}";
  [$search_status, $search_body] = http_request($base_url . '?mode=search&tag=tag&search=' . rawurlencode($search_term), $cookie_jar);
  integration_test('search finds the posted comment', static function () use ($search_status, $search_body, $marker): bool {
    return $search_status === 200 && str_contains($search_body, $marker) && str_contains($search_body, '1件');
  });

  [$public_search_status, $public_search_body] = http_request(
    $base_url . '?mode=search&target=all&match=partial&post_type=thread&image=any&nsfw=safe&sort=newest&search=' . rawurlencode($marker),
    $cookie_jar
  );
  [$empty_public_search_status, $empty_public_search_body] = http_request(
    $base_url . '?mode=search&target=all&search=', $cookie_jar
  );
  [$long_public_search_status] = http_request(
    $base_url . '?mode=search&target=all&search=' . str_repeat('a', 101), $cookie_jar
  );
  integration_test('public search filters targets and returns no rows for an empty query', static function () use (
    $public_search_status, $public_search_body, $empty_public_search_status, $empty_public_search_body,
    $long_public_search_status, $marker
  ): bool {
    return $public_search_status === 200
      && $empty_public_search_status === 200
      && str_contains($empty_public_search_body, '0件')
      && !str_contains($empty_public_search_body, $marker)
      && $long_public_search_status === 400
      && str_contains($public_search_body, '検索結果 - すべて')
      && str_contains($public_search_body, $marker)
      && str_contains($public_search_body, 'name="post_type"')
      && str_contains($public_search_body, 'value="thread" selected');
  });

  $post_id = (int)($row['tid'] ?? 0);
  $admin_password_probe_cookie_jar = $root . '/admin-password-probe-cookies.txt';
  http_request($base_url . '?mode=pictmp', $admin_password_probe_cookie_jar);
  $admin_password_probe_session_id = cookie_value($admin_password_probe_cookie_jar, 'noreita_session');
  $admin_password_probe_token = $admin_password_probe_session_id === null
    ? '' : hash('sha256', $admin_password_probe_session_id);
  [$admin_password_edit_status] = http_request($base_url . '?mode=editexec', $admin_password_probe_cookie_jar, [
    'mode' => 'editexec', 'e_no' => (string)$post_id, 'name' => 'Password probe', 'mail' => '', 'url' => '',
    'sub' => 'Admin password probe', 'com' => "管理パスワード試行 {$marker}", 'pwd' => 'integration-admin-pass',
    'sodane' => '0', 'token' => $admin_password_probe_token,
  ]);
  [$forged_admin_edit_status] = http_request($base_url . '?mode=editexec', $admin_password_probe_cookie_jar, [
    'mode' => 'editexec', 'e_no' => (string)$post_id, 'name' => 'Forged administrator', 'mail' => '', 'url' => '',
    'sub' => 'Forged administrator edit', 'com' => "管理者編集フラグの偽装 {$marker}", 'pwd' => 'integration-admin-pass',
    'sodane' => '0', 'admin_edit' => '1', 'token' => $admin_password_probe_token,
  ]);
  [$admin_password_delete_status] = http_request($base_url . '?mode=del', $admin_password_probe_cookie_jar, [
    'mode' => 'del', 'delno' => (string)$post_id, 'pwd' => 'integration-admin-pass',
  ]);
  [$admin_password_post_status] = http_request($base_url . '?mode=regist', $admin_password_probe_cookie_jar, [
    'mode' => 'regist', 'send' => '1', 'name' => '管理人', 'mail' => '', 'url' => '',
    'sub' => 'Admin password post probe', 'com' => '管理パスワードでも https://example.com は許可されません',
    'pwd' => 'integration-admin-pass', 'invz' => '0', 'img_w' => '0', 'img_h' => '0',
    'sodane' => '0', 'nsfw' => '0', 'token' => $admin_password_probe_token,
  ]);
  $admin_password_post = $db->query(
    "SELECT tid, a_name, admins FROM board_log WHERE sub = 'Admin password post probe' ORDER BY tid DESC LIMIT 1"
  )->fetch(PDO::FETCH_ASSOC);
  if (is_array($admin_password_post)) {
    $db->exec('DELETE FROM board_log WHERE tid = ' . (int)$admin_password_post['tid']);
  }
  $after_admin_password_probes = $db->query(
    'SELECT sub, com FROM board_log WHERE tid = ' . $post_id
  )->fetch(PDO::FETCH_ASSOC);
  integration_test('general posting, edit, and delete never accept the administrator password', static function () use (
    $admin_password_edit_status, $forged_admin_edit_status, $admin_password_delete_status,
    $admin_password_post_status, $admin_password_post, $after_admin_password_probes, $marker
  ): bool {
    return $admin_password_edit_status === 403
      && $forged_admin_edit_status === 403
      && $admin_password_delete_status === 403
      && $admin_password_post_status === 200
      && is_array($admin_password_post)
      && $admin_password_post['a_name'] === '管理人(ではない)'
      && (int)$admin_password_post['admins'] === 0
      && is_array($after_admin_password_probes)
      && $after_admin_password_probes['sub'] === "Integration's subject"
      && $after_admin_password_probes['com'] === "結合テスト user's {$marker}";
  });
  [$owner_edit_form_status, $owner_edit_form_body] = http_request($base_url . '?mode=edit', $cookie_jar, [
    'mode' => 'edit', 'delno' => (string)$post_id, 'pwd' => 'delete-pass',
  ]);
  preg_match('/<input[^>]+name="name"[^>]+value="([^"]*)"/i', $owner_edit_form_body, $owner_name_match);
  integration_test('owner edit form restores the pre-trip name', static function () use (
    $owner_edit_form_status, $owner_name_match, $raw_trip_name
  ): bool {
    return $owner_edit_form_status === 200
      && html_entity_decode((string)($owner_name_match[1] ?? ''), ENT_QUOTES, 'UTF-8') === $raw_trip_name;
  });
  [$trip_edit_status] = http_request($base_url . '?mode=editexec', $cookie_jar, [
    'mode' => 'editexec', 'e_no' => (string)$post_id, 'name' => $raw_trip_name, 'mail' => '', 'url' => '',
    'sub' => "Integration's subject", 'com' => "結合テスト user's {$marker}", 'pwd' => 'delete-pass',
    'sodane' => '0', 'token' => $token,
  ]);
  $trip_name_after_edit = (string)$db->query(
    'SELECT a_name FROM board_log WHERE tid = ' . $post_id
  )->fetchColumn();
  integration_test('owner edit converts the retained raw name back to a displayed trip', static function () use (
    $trip_edit_status, $trip_name_after_edit, $cookie_jar, $raw_trip_name
  ): bool {
    return $trip_edit_status === 200
      && str_starts_with($trip_name_after_edit, "Integration O'Brien ◆")
      && !str_contains($trip_name_after_edit, '#integration-trip')
      && urldecode((string)cookie_value($cookie_jar, 'name_c')) === $raw_trip_name;
  });
  [$admin_manage_csrf_status] = http_request($base_url . '?mode=admin_manage', $cookie_jar, [
    'operation' => 'hide', 'delno' => [(string)$post_id], 'token' => 'invalid-token',
  ]);
  $after_invalid_admin_csrf = (int)$db->query('SELECT invz FROM board_log WHERE tid = ' . $post_id)->fetchColumn();
  integration_test('administrator post management rejects an invalid CSRF token', static function () use (
    $admin_manage_csrf_status, $after_invalid_admin_csrf
  ): bool {
    return $admin_manage_csrf_status === 403 && $after_invalid_admin_csrf === 0;
  });

  [$admin_detail_status, $admin_detail_body] = http_request(
    $base_url . '?mode=admin_post&id=' . $post_id, $cookie_jar
  );
  [$admin_detail_invalid_status] = http_request($base_url . '?mode=admin_post&id=invalid', $cookie_jar);
  [$admin_detail_missing_status] = http_request($base_url . '?mode=admin_post&id=999999', $cookie_jar);
  [$admin_detail_edit_status, $admin_detail_edit_body] = http_request(
    $base_url . '?mode=admin_edit&id=' . $post_id, $cookie_jar
  );
  integration_test('administrator can inspect a post detail', static function () use (
    $admin_detail_status, $admin_detail_body, $admin_detail_invalid_status,
    $admin_detail_missing_status, $post_id, $marker
  ): bool {
    return $admin_detail_status === 200
      && str_contains($admin_detail_body, '投稿詳細 No.' . $post_id)
      && str_contains($admin_detail_body, $marker)
      && str_contains($admin_detail_body, 'mode=admin_edit')
      && str_contains($admin_detail_body, 'name="operation" value="hide"')
      && $admin_detail_invalid_status === 400
      && $admin_detail_missing_status === 404;
  });
  integration_test('administrator can open the edit form from a post detail', static function () use (
    $admin_detail_edit_status, $admin_detail_edit_body
  ): bool {
    return $admin_detail_edit_status === 200
      && str_contains($admin_detail_edit_body, 'mode=editexec')
      && str_contains($admin_detail_edit_body, 'name="e_no"')
      && str_contains($admin_detail_edit_body, 'name="admin_edit" value="1"')
      && str_contains($admin_detail_edit_body, '管理者セッションで認証済み');
  });

  $password_hash_before_edit = (string)$db->query('SELECT pwd FROM board_log WHERE tid = ' . $post_id)->fetchColumn();
  [$rejected_edit_status] = http_request($base_url . '?mode=editexec', $cookie_jar, [
    'mode' => 'editexec', 'e_no' => (string)$post_id, 'name' => 'Attacker', 'mail' => '', 'url' => '',
    'sub' => 'Unauthorized edit', 'com' => "不正な編集 {$marker}", 'pwd' => 'wrong-pass',
    'sodane' => '0', 'token' => $token,
  ]);
  $after_rejected_edit = $db->query('SELECT sub, com, pwd FROM board_log WHERE tid = ' . $post_id)->fetch(PDO::FETCH_ASSOC);
  integration_test('edit rejects an invalid password without changing the post', static function () use ($rejected_edit_status, $after_rejected_edit, $password_hash_before_edit): bool {
    return $rejected_edit_status === 403 && is_array($after_rejected_edit)
      && $after_rejected_edit['sub'] === "Integration's subject"
      && $after_rejected_edit['pwd'] === $password_hash_before_edit;
  });

  [$edit_status] = http_request($base_url . '?mode=editexec', $cookie_jar, [
    'mode' => 'editexec', 'e_no' => (string)$post_id, 'name' => "Edited O'Brien", 'mail' => '', 'url' => '',
    'sub' => "Edited user's subject", 'com' => "編集後 user's 結合テスト {$marker}", 'pwd' => 'delete-pass',
    'sodane' => '0', 'token' => $token,
  ]);
  $edited = $db->query('SELECT sub, com, pwd FROM board_log WHERE tid = ' . $post_id)->fetch(PDO::FETCH_ASSOC);
  integration_test('authorized edit is validated and stored through HTTP', static function () use ($edit_status, $edited, $marker, $password_hash_before_edit): bool {
    return $edit_status === 200 && is_array($edited) && $edited['sub'] === "Edited user's subject"
      && $edited['com'] === "編集後 user's 結合テスト {$marker}"
      && $edited['pwd'] === $password_hash_before_edit;
  });

  [$admin_edit_status] = http_request($base_url . '?mode=editexec', $cookie_jar, [
    'mode' => 'editexec', 'e_no' => (string)$post_id, 'name' => 'Administrator', 'mail' => '', 'url' => '',
    'sub' => "Administrator's edit", 'com' => "管理者編集 user's {$marker}", 'pwd' => 'wrong-and-ignored',
    'sodane' => '0', 'admin_edit' => '1', 'token' => $token,
  ]);
  $admin_edited = $db->query('SELECT sub, com, pwd FROM board_log WHERE tid = ' . $post_id)->fetch(PDO::FETCH_ASSOC);
  integration_test('administrator can edit without replacing the post password', static function () use ($admin_edit_status, $admin_edited, $marker, $password_hash_before_edit): bool {
    return $admin_edit_status === 200 && is_array($admin_edited)
      && $admin_edited['sub'] === "Administrator's edit"
      && $admin_edited['com'] === "管理者編集 user's {$marker}"
      && $admin_edited['pwd'] === $password_hash_before_edit;
  });

  $count_before_rejections = (int)$db->query('SELECT COUNT(*) FROM board_log')->fetchColumn();
  [$ng_status, $ng_body] = http_request($base_url . '?mode=regist', $cookie_jar, [
    'mode' => 'regist', 'send' => '1', 'name' => 'NG test', 'mail' => '', 'url' => '',
    'sub' => 'NG subject', 'com' => '著作権の侵害を含む本文です', 'pwd' => 'ng-pass',
    'invz' => '0', 'sodane' => '0', 'nsfw' => '0', 'token' => $token,
  ]);
  $count_after_ng = (int)$db->query('SELECT COUNT(*) FROM board_log')->fetchColumn();
  integration_test('NG word is rejected through HTTP', static function () use ($ng_status, $ng_body, $count_before_rejections, $count_after_ng): bool {
    return $ng_status === 400 && $count_after_ng === $count_before_rejections
      && str_contains($ng_body, 'Invalid characters');
  });

  [$blocked_status, $blocked_body] = http_request($base_url . '?mode=regist', $cookie_jar, [
    'mode' => 'regist', 'send' => '1', 'name' => 'Blocked host', 'mail' => '', 'url' => '',
    'sub' => 'Blocked subject', 'com' => '拒否ホストからの本文です', 'pwd' => 'blocked-pass',
    'invz' => '0', 'sodane' => '0', 'nsfw' => '0', 'token' => $token,
  ], '198.51.100.0');
  $count_after_blocked = (int)$db->query('SELECT COUNT(*) FROM board_log')->fetchColumn();
  integration_test('blocked host is rejected through HTTP', static function () use ($blocked_status, $blocked_body, $count_before_rejections, $count_after_blocked): bool {
    return $blocked_status === 403 && $count_after_blocked === $count_before_rejections
      && str_contains($blocked_body, 'host is blocked');
  });

  [$duplicate_status, $duplicate_body] = http_request($base_url . '?mode=regist', $cookie_jar, [
    'mode' => 'regist', 'send' => '1', 'name' => 'Duplicate test', 'mail' => '', 'url' => '',
    'sub' => "Administrator's edit", 'com' => "管理者編集 user's {$marker}", 'pwd' => 'duplicate-pass',
    'invz' => '0', 'sodane' => '0', 'nsfw' => '0', 'token' => $token,
  ]);
  $count_after_duplicate = (int)$db->query('SELECT COUNT(*) FROM board_log')->fetchColumn();
  integration_test('duplicate post is rejected through HTTP', static function () use ($duplicate_status, $duplicate_body, $count_before_rejections, $count_after_duplicate): bool {
    return $duplicate_status === 409 && $count_after_duplicate === $count_before_rejections
      && str_contains($duplicate_body, 'Duplicate post');
  });

  $image_base = 'image-' . bin2hex(random_bytes(6));
  $image_name = $image_base . '.png';
  $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
  if ($png === false) throw new RuntimeException('Could not decode integration PNG');
  file_put_contents($webroot . '/tmp/' . $image_name, $png);
  file_put_contents($webroot . '/tmp/' . $image_base . '.dat', "127.0.0.1\tlocalhost\tagent\t.png\tcode\trep\t100\t160\t0\tneo");
  file_put_contents($webroot . '/tmp/' . $image_base . '.pch', 'NEO animation');
  [$image_status] = http_request($base_url . '?mode=regist', $cookie_jar, [
    'mode' => 'regist', 'send' => '1', 'name' => 'Image test', 'mail' => '', 'url' => '',
    'sub' => 'Image subject', 'com' => "画像付き投稿の本文です\n二行目です", 'pwd' => 'image-pass',
    'picfile' => $image_name, 'ctype' => 'new', 'invz' => '0', 'sodane' => '0', 'nsfw' => '0',
    'token' => $token,
  ]);
  $image_row = $db->query("SELECT tid, picfile, pchfile, img_w, img_h, psec, tool, nsfw, thumbnail FROM board_log WHERE sub = 'Image subject' ORDER BY tid DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
  integration_test('image and animation post is stored through HTTP', static function () use ($image_status, $image_row, $webroot, $image_name, $image_base): bool {
    return $image_status === 200 && is_array($image_row)
      && $image_row['picfile'] === $image_name && $image_row['pchfile'] === $image_base . '.pch'
      && (int)$image_row['img_w'] === 1 && (int)$image_row['img_h'] === 1
      && (int)$image_row['psec'] === 60 && $image_row['tool'] === 'PaintBBS NEO'
      && is_file($webroot . '/img/' . $image_name)
      && is_file($webroot . '/img/' . $image_base . '.pch')
      && !is_file($webroot . '/tmp/' . $image_base . '.dat');
  });

  $admin_temporary_base = 'admin-temp-' . bin2hex(random_bytes(6));
  $admin_temporary_name = $admin_temporary_base . '.png';
  file_put_contents($webroot . '/tmp/' . $admin_temporary_name, $png);
  file_put_contents($webroot . '/tmp/' . $admin_temporary_base . '.dat', "127.0.0.1\tlocalhost\tagent\t.png\tcode\trep\t100\t160\t0\tneo");
  file_put_contents($webroot . '/tmp/' . $admin_temporary_base . '.pch', 'temporary animation');
  $admin_temporary_second_base = 'admin-temp-second-' . bin2hex(random_bytes(6));
  $admin_temporary_second_name = $admin_temporary_second_base . '.png';
  file_put_contents($webroot . '/tmp/' . $admin_temporary_second_name, $png);
  file_put_contents($webroot . '/tmp/' . $admin_temporary_second_base . '.dat', "127.0.0.1\tlocalhost\tagent\t.png\tcode\trep\t100\t160\t0\tneo");
  touch($webroot . '/tmp/' . $admin_temporary_name, time());
  touch($webroot . '/tmp/' . $admin_temporary_second_name, time() - 1);
  [$admin_temporary_page_one_status, $admin_temporary_page_one_body] = http_request(
    $base_url . '?mode=admin_temporary_images&page=1', $cookie_jar
  );
  [$admin_temporary_page_two_status, $admin_temporary_page_two_body] = http_request(
    $base_url . '?mode=admin_temporary_images&page=2', $cookie_jar
  );
  [$admin_temporary_invalid_page_status] = http_request(
    $base_url . '?mode=admin_temporary_images&page=invalid', $cookie_jar
  );
  [$admin_temporary_missing_page_status] = http_request(
    $base_url . '?mode=admin_temporary_images&page=99', $cookie_jar
  );
  [$admin_temporary_delete_status] = http_request($base_url . '?mode=admin_temporary_images_manage', $cookie_jar, [
    'operation' => 'delete_selected', 'temporary_image' => [$admin_temporary_name], 'token' => $token,
  ]);
  $audit_log = '';
  foreach (glob($webroot . '/auditlog/audit-*.log') ?: [] as $audit_file) {
    $contents = file_get_contents($audit_file);
    if (is_string($contents)) $audit_log .= $contents;
  }
  integration_test('administrator manages pending images without accepting arbitrary files', static function () use (
    $admin_temporary_page_one_status, $admin_temporary_page_one_body,
    $admin_temporary_page_two_status, $admin_temporary_page_two_body,
    $admin_temporary_invalid_page_status, $admin_temporary_missing_page_status, $admin_temporary_delete_status,
    $admin_temporary_name, $admin_temporary_second_name, $admin_temporary_base, $webroot, $audit_log
  ): bool {
    return $admin_temporary_page_one_status === 200
      && str_contains($admin_temporary_page_one_body, '一時画像の管理')
      && str_contains($admin_temporary_page_one_body, $admin_temporary_name)
      && !str_contains($admin_temporary_page_one_body, $admin_temporary_second_name)
      && str_contains($admin_temporary_page_one_body, 'name="temporary_image[]"')
      && str_contains($admin_temporary_page_one_body, 'mode=temporary_image')
      && str_contains($admin_temporary_page_one_body, '1 / 2 ページ')
      && $admin_temporary_page_two_status === 200
      && str_contains($admin_temporary_page_two_body, $admin_temporary_second_name)
      && !str_contains($admin_temporary_page_two_body, $admin_temporary_name)
      && str_contains($admin_temporary_page_two_body, '2 / 2 ページ')
      && $admin_temporary_invalid_page_status === 400
      && $admin_temporary_missing_page_status === 404
      && $admin_temporary_delete_status === 302
      && !is_file($webroot . '/tmp/' . $admin_temporary_name)
      && !is_file($webroot . '/tmp/' . $admin_temporary_base . '.dat')
      && !is_file($webroot . '/tmp/' . $admin_temporary_base . '.pch')
      && str_contains($audit_log, '"type":"admin-audit"')
      && str_contains($audit_log, '"audit_action":"temporary-images-delete"')
      && str_contains($audit_log, '"images":1')
      && !str_contains($audit_log, $admin_temporary_name);
  });

  $temporary_owner_cookie_jar = $root . '/temporary-image-owner-cookies.txt';
  http_request($base_url, $temporary_owner_cookie_jar);
  $temporary_owner_code = cookie_value($temporary_owner_cookie_jar, 'usercode');
  if ($temporary_owner_code === null || $temporary_owner_code === '') {
    throw new RuntimeException('Could not create a temporary-image owner session.');
  }
  $temporary_private_base = 'private-temp-' . bin2hex(random_bytes(6));
  $temporary_private_name = $temporary_private_base . '.png';
  file_put_contents($webroot . '/tmp/' . $temporary_private_name, $png);
  file_put_contents(
    $webroot . '/tmp/' . $temporary_private_base . '.dat',
    "127.0.0.1\tlocalhost\tagent\t.png\t{$temporary_owner_code}\t\t100\t160\t0\tneo"
  );
  file_put_contents($webroot . '/tmp/' . $temporary_private_base . '.psd', 'private working data');
  $temporary_image_url = $base_url . '?mode=temporary_image&file=' . rawurlencode($temporary_private_name);
  [$temporary_owner_status, $temporary_owner_body, , $temporary_owner_headers] = http_request(
    $temporary_image_url, $temporary_owner_cookie_jar
  );
  $temporary_guest_cookie_jar = $root . '/temporary-image-guest-cookies.txt';
  [$temporary_guest_status, $temporary_guest_body] = http_request($temporary_image_url, $temporary_guest_cookie_jar);
  [$temporary_admin_status, $temporary_admin_body] = http_request($temporary_image_url, $cookie_jar);
  [$temporary_direct_status, $temporary_direct_body] = http_request(
    $origin_url . '/tmp/' . rawurlencode($temporary_private_name), $temporary_guest_cookie_jar
  );
  [$temporary_work_status, $temporary_work_body] = http_request(
    $base_url . '?mode=temporary_image&file=' . rawurlencode($temporary_private_base . '.psd'), $temporary_owner_cookie_jar
  );
  integration_test('temporary images require owner or administrator authorization over HTTP', static function () use (
    $temporary_owner_status, $temporary_owner_body, $temporary_owner_headers,
    $temporary_guest_status, $temporary_guest_body, $temporary_admin_status, $temporary_admin_body,
    $temporary_direct_status, $temporary_direct_body, $temporary_work_status, $temporary_work_body, $png
  ): bool {
    return $temporary_owner_status === 200 && $temporary_owner_body === $png
      && ($temporary_owner_headers['content-type'] ?? '') === 'image/png'
      && str_contains((string)($temporary_owner_headers['cache-control'] ?? ''), 'no-store')
      && $temporary_guest_status === 404 && !str_contains($temporary_guest_body, 'PNG')
      && $temporary_admin_status === 200 && $temporary_admin_body === $png
      && $temporary_direct_status === 403 && !str_contains($temporary_direct_body, 'PNG')
      && $temporary_work_status === 404 && !str_contains($temporary_work_body, 'private working data');
  });

  $image_post_id = (int)($image_row['tid'] ?? 0);
  [$image_admin_detail_status, $image_admin_detail_body] = http_request(
    $base_url . '?mode=admin_post&id=' . $image_post_id, $cookie_jar
  );
  integration_test('administrator post detail links animation to the playback screen', static function () use (
    $image_admin_detail_status, $image_admin_detail_body, $image_base
  ): bool {
    return $image_admin_detail_status === 200
      && str_contains($image_admin_detail_body, 'mode=anime')
      && str_contains($image_admin_detail_body, 'pch=' . $image_base . '.pch')
      && str_contains($image_admin_detail_body, '動画を再生する')
      && !str_contains($image_admin_detail_body, '>関連する動画ファイルを開く<');
  });

  [$image_edit_form_status, $image_edit_form_body] = http_request($base_url, $cookie_jar, [
    'mode' => 'edit', 'delno' => (string)$image_post_id, 'pwd' => 'image-pass',
  ]);
  integration_test('image edit form includes the current NSFW setting', static function () use ($image_edit_form_status, $image_edit_form_body): bool {
    return $image_edit_form_status === 200
      && str_contains($image_edit_form_body, 'id="edit_nsfw"')
      && str_contains($image_edit_form_body, 'src="img/')
      && str_contains($image_edit_form_body, "画像付き投稿の本文です\n二行目です")
      && !str_contains($image_edit_form_body, '&lt;br')
      && !str_contains($image_edit_form_body, '&NewLine;')
      && !str_contains($image_edit_form_body, 'checked="checked"');
  });

  [$image_nsfw_status] = http_request($base_url . '?mode=editexec', $cookie_jar, [
    'mode' => 'editexec', 'e_no' => (string)$image_post_id, 'name' => 'Image test', 'mail' => '', 'url' => '',
    'sub' => 'Image subject', 'com' => "画像付き投稿の本文です\n二行目です", 'pwd' => 'image-pass',
    'sodane' => '0', 'nsfw' => '1', 'token' => $token,
  ]);
  $nsfw_image_row = $db->query('SELECT nsfw, thumbnail FROM board_log WHERE tid = ' . $image_post_id)->fetch(PDO::FETCH_ASSOC);
  $nsfw_thumbnail = (string)($nsfw_image_row['thumbnail'] ?? '');
  integration_test('comment edit can enable NSFW and refresh the thumbnail', static function () use ($image_nsfw_status, $nsfw_image_row, $nsfw_thumbnail, $webroot): bool {
    return $image_nsfw_status === 200 && (int)$nsfw_image_row['nsfw'] === 1
      && $nsfw_thumbnail !== '' && is_file($webroot . '/img/' . $nsfw_thumbnail);
  });

  // 一時テストDBだけを更新し、旧データなどでサムネイルが未登録の場合も検証する。
  $ogp_update = $db->prepare('UPDATE board_log SET nsfw = ?, thumbnail = ? WHERE tid = ?');
  try {
    foreach ([
      'NSFW with empty thumbnail' => [1, '', ''],
      'NSFW with null thumbnail' => [1, null, ''],
      'NSFW with blurred thumbnail' => [1, $nsfw_thumbnail, $nsfw_thumbnail],
      'safe original image' => [0, '', $image_name],
    ] as $case => [$nsfw_state, $thumbnail_name, $expected_image]) {
      $ogp_update->execute([$nsfw_state, $thumbnail_name, $image_post_id]);
      [$ogp_status, $ogp_body] = http_request(
        $base_url . '?resno=' . $image_post_id, $root . '/ogp-anonymous-cookies.txt'
      );
      integration_test('SNS image metadata: ' . $case, static function () use (
        $ogp_status, $ogp_body, $expected_image
      ): bool {
        if ($ogp_status !== 200) return false;
        $tags = [];
        preg_match_all('/<meta\s+(?:property|name)="(?:og:image|twitter:image)"\s+content="([^"]*)"/', $ogp_body, $tags);
        if ($expected_image === '') {
          return $tags[1] === [] && str_contains($ogp_body, 'name="twitter:card" content="summary"');
        }
        return count($tags[1]) === 2 && $tags[1][0] === $tags[1][1]
          && str_ends_with($tags[1][0], '/img/' . rawurlencode($expected_image))
          && str_contains($ogp_body, 'name="twitter:card" content="summary_large_image"');
      });
    }
  } finally {
    $ogp_update->execute([$nsfw_image_row['nsfw'], $nsfw_image_row['thumbnail'], $image_post_id]);
  }

  [, $checked_edit_form_body] = http_request($base_url, $cookie_jar, [
    'mode' => 'edit', 'delno' => (string)$image_post_id, 'pwd' => 'image-pass',
  ]);
  integration_test('image edit form shows an enabled NSFW setting', static function () use ($checked_edit_form_body): bool {
    return preg_match('/id="edit_nsfw"[^>]*checked="checked"/', $checked_edit_form_body) === 1;
  });

  [$image_safe_status] = http_request($base_url . '?mode=editexec', $cookie_jar, [
    'mode' => 'editexec', 'e_no' => (string)$image_post_id, 'name' => 'Image test', 'mail' => '', 'url' => '',
    'sub' => 'Image subject', 'com' => "画像付き投稿の本文です\n二行目です", 'pwd' => 'image-pass',
    'sodane' => '0', 'nsfw' => '0', 'token' => $token,
  ]);
  $safe_image_row = $db->query('SELECT nsfw, thumbnail FROM board_log WHERE tid = ' . $image_post_id)->fetch(PDO::FETCH_ASSOC);
  clearstatcache(true, $webroot . '/img/' . $nsfw_thumbnail);
  integration_test('comment edit can disable NSFW and remove an obsolete thumbnail', static function () use ($image_safe_status, $safe_image_row, $webroot, $nsfw_thumbnail): bool {
    return $image_safe_status === 200 && (int)$safe_image_row['nsfw'] === 0
      && (string)$safe_image_row['thumbnail'] === ''
      && !is_file($webroot . '/img/' . $nsfw_thumbnail);
  });

  http_request($base_url . '?mode=editexec', $cookie_jar, [
    'mode' => 'editexec', 'e_no' => (string)$image_post_id, 'name' => 'Image test', 'mail' => '', 'url' => '',
    'sub' => 'Image subject', 'com' => "画像付き投稿の本文です\n二行目です", 'pwd' => 'image-pass',
    'sodane' => '0', 'nsfw' => '1', 'token' => $token,
  ]);
  $continued_from_thumbnail = (string)$db->query('SELECT thumbnail FROM board_log WHERE tid = ' . $image_post_id)->fetchColumn();

  $replacement_base = 'replacement-' . bin2hex(random_bytes(6));
  $replacement_code = 'replace-code-' . bin2hex(random_bytes(4));
  $resized_replacement = imagecreatetruecolor(17, 11);
  imagepng($resized_replacement, $webroot . '/tmp/' . $replacement_base . '.png');
  unset($resized_replacement);
  file_put_contents(
    $webroot . '/tmp/' . $replacement_base . '.dat',
    "127.0.0.1\tlocalhost\tagent\t.png\tcode\t{$replacement_code}\t200\t260\t0\tneo"
  );
  file_put_contents($webroot . '/tmp/' . $replacement_base . '.pch', 'replacement animation');
  file_put_contents($webroot . '/tmp/' . $replacement_base . '.psd', 'replacement layers');
  $replacement_old_psd = pathinfo((string)$image_row['picfile'], PATHINFO_FILENAME) . '.psd';
  file_put_contents($webroot . '/img/' . $replacement_old_psd, 'old layers');
  [$replacement_authorization_status, $replacement_authorization_body] = http_request($base_url, $cookie_jar, [
    'mode' => 'contpaint', 'type' => 'rep', 'no' => (string)$image_post_id, 'pwd' => 'image-pass',
    'picw' => '300', 'pich' => '300', 'img' => (string)$image_row['picfile'], 'ctype' => 'img',
    'tools' => 'neo', 'anime' => 'true',
  ]);
  if (!replace_cookie_value($cookie_jar, 'pwd_cookie', 'another-post-pass')) {
    throw new RuntimeException('Could not prepare a mismatched saved password');
  }
  [$replacement_status, $replacement_body] = http_request(
    $base_url . '?mode=picrep&no=' . $image_post_id . '&repcode=' . rawurlencode($replacement_code)
      . '&stime=300',
    $cookie_jar,
    ['nsfw' => '0']
  );
  $replaced_image_row = $db->query('SELECT picfile, pchfile, nsfw, thumbnail, img_w, img_h FROM board_log WHERE tid = ' . $image_post_id)->fetch(PDO::FETCH_ASSOC);
  integration_test('image replacement updates stored dimensions after canvas resize', static function () use (
    $replacement_status, $replaced_image_row
  ): bool {
    return $replacement_status === 200 && is_array($replaced_image_row)
      && (int)$replaced_image_row['img_w'] === 17 && (int)$replaced_image_row['img_h'] === 11;
  });
  $replacement_thumbnail = (string)($replaced_image_row['thumbnail'] ?? '');
  clearstatcache(true, $webroot . '/img/' . $continued_from_thumbnail);
  integration_test('continued NSFW drawing can become safe with a fresh thumbnail', static function () use (
    $replacement_authorization_status, $replacement_authorization_body, $replacement_status,
    $replacement_body, $replaced_image_row, $replacement_base,
    $replacement_thumbnail, $continued_from_thumbnail, $webroot, $replacement_old_psd
  ): bool {
    return $replacement_authorization_status === 200
      && !str_contains($replacement_authorization_body, '&amp;pwd=')
      && !str_contains($replacement_authorization_body, 'enc_pwd')
      && $replacement_status === 200 && is_array($replaced_image_row)
      && $replaced_image_row['picfile'] === $replacement_base . '.png'
      && $replaced_image_row['pchfile'] === $replacement_base . '.pch'
      && is_file($webroot . '/img/' . $replacement_base . '.psd')
      && file_get_contents($webroot . '/img/' . $replacement_base . '.psd') === 'replacement layers'
      && !is_file($webroot . '/tmp/' . $replacement_base . '.psd')
      && !is_file($webroot . '/img/' . $replacement_old_psd)
      && (int)$replaced_image_row['nsfw'] === 0
      && $replacement_thumbnail !== ''
      && str_starts_with($replacement_thumbnail, $replacement_base . '_thumb_safe_')
      && is_file($webroot . '/img/' . $replacement_thumbnail)
      && !is_file($webroot . '/img/' . $continued_from_thumbnail)
      && str_contains($replacement_body, 'action="index.php?mode=editexec"')
      && preg_match('/name="pwd"[^>]+value="image-pass"/', $replacement_body) === 1
      && str_contains($replacement_body, 'src="img/' . $replacement_thumbnail . '"')
      && str_contains($replacement_body, 'id="edit_nsfw"');
  });

  preg_match('/name="token" value="([^"]+)"/', $replacement_body, $replacement_token_match);
  $replacement_edit_token = html_entity_decode(
    (string)($replacement_token_match[1] ?? ''), ENT_QUOTES, 'UTF-8'
  );
  $continued_subject = 'Continued drawing subject ' . $marker;
  $continued_comment = "続き描き後に更新した本文です\n{$marker}";
  [$continued_edit_status] = http_request($base_url . '?mode=editexec', $cookie_jar, [
    'mode' => 'editexec', 'e_no' => (string)$image_post_id, 'name' => 'Continued artist#trip-key',
    'mail' => 'continued@example.com', 'url' => 'https://example.com/continued',
    'sub' => $continued_subject, 'com' => $continued_comment, 'pwd' => 'image-pass',
    'sodane' => '0', 'nsfw' => '0', 'token' => $replacement_edit_token,
  ]);
  $continued_content = $db->query(
    'SELECT a_name, mail, a_url, sub, com FROM board_log WHERE tid = ' . $image_post_id
  )->fetch(PDO::FETCH_ASSOC);
  integration_test('comment fields submitted after continued drawing are stored', static function () use (
    $continued_edit_status, $continued_content, $continued_subject, $continued_comment
  ): bool {
    return $continued_edit_status === 200 && is_array($continued_content)
      && str_starts_with((string)$continued_content['a_name'], 'Continued artist ◆')
      && $continued_content['mail'] === 'continued@example.com'
      && $continued_content['a_url'] === 'https://example.com/continued'
      && $continued_content['sub'] === $continued_subject
      && $continued_content['com'] === $continued_comment;
  });

  // Tegakiは画像からの続き描きではリプレイを生成しない。そのためPNGだけを
  // saveimageへ送信してから、POSTのpicrepで差し替える実際の経路を確認する。
  [$tegaki_continue_form_status, $tegaki_continue_form_body] = http_request($base_url, $cookie_jar, [
    'mode' => 'contpaint', 'type' => 'rep', 'no' => (string)$image_post_id, 'pwd' => 'image-pass',
    'picw' => '300', 'pich' => '300', 'img' => (string)($replaced_image_row['picfile'] ?? ''),
    'ctype' => 'img', 'tools' => 'tegaki', 'anime' => 'true',
  ]);
  preg_match('/formData\\.append\\("repcode",\\s*"([a-f0-9]{32})"\\)/', $tegaki_continue_form_body, $tegaki_repcode_match);
  preg_match('/formData\\.append\\("no",\\s*"([0-9]+)"\\)/', $tegaki_continue_form_body, $tegaki_post_id_match);
  $tegaki_replacement_code = (string)($tegaki_repcode_match[1] ?? '');
  $tegaki_replacement_post_id = (string)($tegaki_post_id_match[1] ?? '');
  [$tegaki_save_status, $tegaki_save_body] = http_request(
    $base_url . '?mode=saveimage&tool=tegaki',
    $cookie_jar,
    [
      'picture' => new CURLFile($animation_png, 'image/png', 'continued-drawing.png'),
      'tool' => 'tegaki', 'repcode' => $tegaki_replacement_code,
      'stime' => (string)time(), 'resto' => '0',
    ]
  );
  [$tegaki_replace_status, $tegaki_replace_body] = http_request($base_url, $cookie_jar, [
    'mode' => 'picrep', 'no' => $tegaki_replacement_post_id, 'repcode' => $tegaki_replacement_code,
    'nsfw' => '0', 'paint_picrep' => 'true',
  ]);
  $tegaki_replaced_row = $db->query(
    'SELECT picfile, pchfile FROM board_log WHERE tid = ' . $image_post_id
  )->fetch(PDO::FETCH_ASSOC);
  integration_test('Tegaki continuation saves its PNG and opens the replacement edit form', static function () use (
    $tegaki_continue_form_status, $tegaki_continue_form_body, $tegaki_replacement_code, $tegaki_replacement_post_id,
    $tegaki_save_status, $tegaki_save_body, $tegaki_replace_status, $tegaki_replace_body,
    $tegaki_replaced_row, $webroot, $image_post_id
  ): bool {
    $tegaki_base = pathinfo((string)($tegaki_replaced_row['picfile'] ?? ''), PATHINFO_FILENAME);
    return $tegaki_continue_form_status === 200
      && str_contains($tegaki_continue_form_body, 'saveReplay:  false')
      && $tegaki_replacement_code !== ''
      && $tegaki_replacement_post_id === (string)$image_post_id
      && $tegaki_save_status === 200 && $tegaki_save_body === 'ok'
      && $tegaki_replace_status === 200 && is_array($tegaki_replaced_row)
      && preg_match('/^\\d{16}\\.png$/D', (string)$tegaki_replaced_row['picfile']) === 1
      && $tegaki_replaced_row['pchfile'] === ''
      && is_file($webroot . '/img/' . $tegaki_replaced_row['picfile'])
      && str_contains($tegaki_replace_body, 'action="index.php?mode=editexec"')
      && $tegaki_base !== '';
  });

  [$admin_page_one_status, $admin_page_one_body] = http_request($base_url . '?mode=admin&page=1', $cookie_jar);
  [$admin_page_two_status, $admin_page_two_body] = http_request($base_url . '?mode=admin&page=2', $cookie_jar);
  [$admin_filtered_status, $admin_filtered_body] = http_request($base_url . '?mode=admin&isAdministrator=no&page=1', $cookie_jar);
  [$admin_search_status, $admin_search_body] = http_request(
    $base_url . '?mode=admin&q=' . rawurlencode("Administrator's edit"), $cookie_jar
  );
  [$admin_invalid_filter_status] = http_request($base_url . '?mode=admin&image=invalid', $cookie_jar);
  [$admin_invalid_page_status] = http_request($base_url . '?mode=admin&page=invalid', $cookie_jar);
  [$admin_missing_page_status] = http_request($base_url . '?mode=admin&page=99', $cookie_jar);
  integration_test('administration pagination keeps thread pages separate and validates page numbers', static function () use (
    $admin_page_one_status, $admin_page_one_body, $admin_page_two_status, $admin_page_two_body,
    $admin_filtered_status, $admin_filtered_body, $admin_search_status, $admin_search_body,
    $admin_invalid_filter_status, $admin_invalid_page_status, $admin_missing_page_status, $image_post_id, $post_id
  ): bool {
    return $admin_page_one_status === 200 && $admin_page_two_status === 200
      && str_contains($admin_page_one_body, 'name="delno[]" value="' . $image_post_id . '"')
      && !str_contains($admin_page_one_body, 'name="delno[]" value="' . $post_id . '"')
      && str_contains($admin_page_one_body, 'page=2')
      && str_contains($admin_page_two_body, 'name="delno[]" value="' . $post_id . '"')
      && !str_contains($admin_page_two_body, 'name="delno[]" value="' . $image_post_id . '"')
      && str_contains($admin_page_two_body, 'page=1')
      && $admin_filtered_status === 200
      && str_contains($admin_filtered_body, 'isAdministrator=no')
      && str_contains($admin_filtered_body, 'page=2')
      && $admin_search_status === 200
      && str_contains($admin_search_body, 'name="delno[]" value="' . $post_id . '"')
      && !str_contains($admin_search_body, 'name="delno[]" value="' . $image_post_id . '"')
      && str_contains($admin_search_body, '検索結果')
      && $admin_invalid_filter_status === 400
      && $admin_invalid_page_status === 400 && $admin_missing_page_status === 404;
  });

  [$image_delete_status] = http_request($base_url, $cookie_jar, [
    'mode' => 'del', 'delno' => (string)$image_post_id, 'pwd' => 'image-pass',
  ]);
  clearstatcache(true, $webroot . '/img/' . $image_name);
  clearstatcache(true, $webroot . '/img/' . $image_base . '.pch');
  integration_test('deleting image post removes related files', static function () use ($image_delete_status, $db, $image_post_id, $webroot, $image_name, $image_base): bool {
    return $image_delete_status === 200
      && (int)$db->query('SELECT COUNT(*) FROM board_log WHERE tid = ' . $image_post_id)->fetchColumn() === 0
      && !is_file($webroot . '/img/' . $image_name)
      && !is_file($webroot . '/img/' . $image_base . '.pch');
  });

  [$admin_with_posts_status, $admin_with_posts_body] = http_request($base_url . '?mode=admin', $cookie_jar);
  integration_test('administration screen renders a checkbox for each post', static function () use ($admin_with_posts_status, $admin_with_posts_body, $post_id): bool {
    return $admin_with_posts_status === 200
      && str_contains($admin_with_posts_body, 'name="delno[]" value="' . $post_id . '"')
      && str_contains($admin_with_posts_body, 'mode=admin_post&amp;id=' . $post_id)
      && !str_contains($admin_with_posts_body, 'name="adminpass"');
  });

  [$hide_status] = http_request($base_url . '?mode=admin_manage', $cookie_jar, [
    'operation' => 'hide', 'delno' => [(string)$post_id], 'token' => $token,
  ]);
  $hidden_value = (int)$db->query('SELECT invz FROM board_log WHERE tid = ' . $post_id)->fetchColumn();
  [$hidden_detail_status, $hidden_detail_body] = http_request(
    $base_url . '?mode=admin_post&id=' . $post_id, $cookie_jar
  );
  [$hidden_filter_status, $hidden_filter_body] = http_request(
    $base_url . '?mode=admin&visibility=hidden', $cookie_jar
  );
  [$hidden_search_status, $hidden_search_body] = http_request(
    $base_url . '?mode=search&tag=tag&search=' . rawurlencode($search_term), $cookie_jar
  );
  integration_test('administrator can hide checked posts and find them with the hidden filter', static function () use (
    $hide_status, $hidden_value, $hidden_detail_status, $hidden_detail_body,
    $hidden_filter_status, $hidden_filter_body, $hidden_search_status, $hidden_search_body, $post_id
  ): bool {
    return $hide_status === 302 && $hidden_value === 1
      && $hidden_detail_status === 200 && str_contains($hidden_detail_body, 'この記事を再表示')
      && $hidden_filter_status === 200
      && str_contains($hidden_filter_body, 'name="delno[]" value="' . $post_id . '"')
      && str_contains($hidden_filter_body, '非表示')
      && str_contains($hidden_filter_body, 'selected post(s) were hidden')
      && $hidden_search_status === 200 && str_contains($hidden_search_body, '0件');
  });

  $hidden_uuid = (string)$db->query('SELECT uuid FROM board_log WHERE tid = ' . $post_id)->fetchColumn();
  foreach (['?resno=' . $post_id, '?mode=res&res=' . $post_id,
    '?mode=res&uuid=' . rawurlencode($hidden_uuid), '?resno=2147483647'] as $query) {
    [$private_status, $private_body] = http_request($base_url . $query, $root . '/anonymous-cookies.txt');
    integration_test('public response rejects hidden or missing posts: ' . $query, static function () use (
      $private_status, $private_body, $marker
    ): bool {
      return $private_status === 404 && !str_contains($private_body, $marker)
        && !str_contains($private_body, 'property="og:description"')
        && !str_contains($private_body, 'property="og:image"');
    });
  }

  [$show_status] = http_request($base_url . '?mode=admin_manage', $cookie_jar, [
    'operation' => 'show', 'delno' => [(string)$post_id], 'token' => $token,
  ]);
  $visible_value = (int)$db->query('SELECT invz FROM board_log WHERE tid = ' . $post_id)->fetchColumn();
  [$visible_admin_status, $visible_admin_body] = http_request($base_url . '?mode=admin', $cookie_jar);
  [$visible_search_status, $visible_search_body] = http_request(
    $base_url . '?mode=search&tag=tag&search=' . rawurlencode($search_term), $cookie_jar
  );
  integration_test('administrator can make checked posts visible again', static function () use (
    $show_status, $visible_value, $visible_admin_status, $visible_admin_body,
    $visible_search_status, $visible_search_body, $marker
  ): bool {
    return $show_status === 302 && $visible_value === 0
      && $visible_admin_status === 200 && str_contains($visible_admin_body, 'selected post(s) were made visible')
      && $visible_search_status === 200 && str_contains($visible_search_body, $marker);
  });

  [$delete_status] = http_request($base_url . '?mode=admin_manage', $cookie_jar, [
    'operation' => 'delete', 'delno' => [(string)$post_id], 'token' => $token,
  ]);
  $remaining = (int)$db->query('SELECT COUNT(*) FROM board_log WHERE tid = ' . $post_id)->fetchColumn();
  integration_test('administrator can delete checked posts without resending the password', static function () use ($delete_status, $remaining): bool {
    return $delete_status === 302 && $remaining === 0;
  });

  [$empty_status, $empty_body] = http_request($base_url . '?mode=search&tag=tag&search=' . rawurlencode($search_term), $cookie_jar);
  integration_test('deleted post disappears from search', static function () use ($empty_status, $empty_body): bool {
    return $empty_status === 200 && str_contains($empty_body, '0件');
  });

  $upload_source = $root . '/direct-upload.png';
  $upload_png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
  if ($upload_png === false || file_put_contents($upload_source, $upload_png) === false) {
    throw new RuntimeException('Could not create direct upload image.');
  }
  $upload_marker = 'direct-upload-' . bin2hex(random_bytes(6));
  // IHDRだけのPNGで検証順序を確認する。巨大な画像データは生成・展開しない。
  $upload_defaults = require $webroot . '/config.php';
  foreach ([
    'width' => [$upload_defaults['limits']['image_width'] + 1, 1, 'dimensions exceed the limit'],
    'height' => [1, $upload_defaults['limits']['image_height'] + 1, 'dimensions exceed the limit'],
    'invalid pixels' => [1, 1, 'Unsupported image format'],
  ] as $case => [$header_width, $header_height, $expected_error]) {
    $header = 'IHDR' . pack('NNCCCCC', $header_width, $header_height, 8, 2, 0, 0, 0);
    $header_file = $root . '/header-only.png';
    file_put_contents($header_file, "\x89PNG\r\n\x1a\n" . pack('N', 13) . $header . pack('N', crc32($header)));
    $before_rows = (int)$db->query('SELECT COUNT(*) FROM board_log')->fetchColumn();
    $before_files = glob($webroot . '/img/*');
    [$dimension_status, $dimension_body] = http_request($base_url . '?mode=regist', $cookie_jar, [
      'mode' => 'regist', 'send' => '1', 'name' => 'upload-test', 'sub' => 'Invalid image',
      'com' => '画像の寸法検証 ' . $case, 'pwd' => 'upload-delete-pass', 'token' => $token,
      'image_upload' => new CURLFile($header_file, 'image/png', 'header-only.png'),
    ]);
    integration_test('upload validates dimensions before decoding: ' . $case, static function () use (
      $dimension_status, $dimension_body, $expected_error, $before_rows, $before_files, $db, $webroot
    ): bool {
      return $dimension_status === 400 && str_contains($dimension_body, $expected_error)
        && (int)$db->query('SELECT COUNT(*) FROM board_log')->fetchColumn() === $before_rows
        && glob($webroot . '/img/*') === $before_files;
    });
  }
  [$direct_upload_status] = http_request($base_url . '?mode=regist', $cookie_jar, [
    'mode' => 'regist', 'send' => '1', 'name' => 'upload-test', 'mail' => '', 'url' => '',
    'sub' => 'Direct image upload', 'com' => "画像アップロード {$upload_marker}", 'pwd' => 'upload-delete-pass',
    'invz' => '0', 'img_w' => '0', 'img_h' => '0', 'sodane' => '0', 'nsfw' => '0', 'token' => $token,
    'image_upload' => new CURLFile($upload_source, 'image/png', 'untrusted-client-name.png'),
  ]);
  $upload_row_statement = $db->prepare('SELECT picfile, img_w, img_h, tool, thumbnail FROM board_log WHERE com = :comment LIMIT 1');
  $upload_row_statement->execute([':comment' => "画像アップロード {$upload_marker}"]);
  $upload_row = $upload_row_statement->fetch(PDO::FETCH_ASSOC);
  integration_test('direct image upload uses an oekaki-style generated filename', static function () use (
    $direct_upload_status, $upload_row, $webroot
  ): bool {
    return $direct_upload_status === 200 && is_array($upload_row)
      && preg_match('/^\\d{16}\\.png$/D', (string)$upload_row['picfile']) === 1
      && (int)$upload_row['img_w'] === 1 && (int)$upload_row['img_h'] === 1
      && $upload_row['tool'] === 'Upload' && $upload_row['thumbnail'] === ''
      && is_file($webroot . '/img/' . $upload_row['picfile']);
  });

  $jpeg_upload_source = $root . '/direct-upload-exif.jpg';
  $jpeg_marker = 'noreita-exif-' . bin2hex(random_bytes(8));
  $jpeg_canvas = imagecreatetruecolor(2, 2);
  if ($jpeg_canvas === false || !imagejpeg($jpeg_canvas, $jpeg_upload_source, 100)) {
    throw new RuntimeException('Could not create direct JPEG upload image.');
  }
  unset($jpeg_canvas);
  $jpeg_source = file_get_contents($jpeg_upload_source);
  $jpeg_metadata = "Exif\x00\x00" . $jpeg_marker;
  if (!is_string($jpeg_source) || strlen($jpeg_source) < 2
    || file_put_contents($jpeg_upload_source, substr($jpeg_source, 0, 2) . "\xff\xe1"
      . pack('n', strlen($jpeg_metadata) + 2) . $jpeg_metadata . substr($jpeg_source, 2)) === false) {
    throw new RuntimeException('Could not add EXIF metadata to direct JPEG upload image.');
  }
  $jpeg_upload_comment = 'direct-upload-exif-' . bin2hex(random_bytes(6));
  $jpeg_files_before_upload = glob($webroot . '/img/*.jpg') ?: [];
  [$jpeg_upload_status] = http_request($base_url . '?mode=regist', $cookie_jar, [
    'mode' => 'regist', 'send' => '1', 'name' => 'upload-test', 'mail' => '', 'url' => '',
    'sub' => 'Direct JPEG upload', 'com' => "JPEGアップロード {$jpeg_upload_comment}", 'pwd' => 'upload-delete-pass',
    'invz' => '0', 'img_w' => '0', 'img_h' => '0', 'sodane' => '0', 'nsfw' => '0', 'token' => $token,
    'image_upload' => new CURLFile($jpeg_upload_source, 'image/jpeg', 'metadata.jpg'),
  ]);
  $jpeg_files_after_upload = glob($webroot . '/img/*.jpg') ?: [];
  $jpeg_uploaded_files = array_values(array_diff($jpeg_files_after_upload, $jpeg_files_before_upload));
  integration_test('direct JPEG upload removes embedded EXIF metadata', static function () use (
    $jpeg_upload_status, $jpeg_uploaded_files, $jpeg_marker
  ): bool {
    if ($jpeg_upload_status !== 200 || count($jpeg_uploaded_files) !== 1) return false;
    $contents = file_get_contents($jpeg_uploaded_files[0]);
    return is_string($contents) && !str_contains($contents, $jpeg_marker);
  });

  [$pending_drawing_status, $pending_drawing_body] = http_request(
    $base_url . '?mode=animation_upload',
    $cookie_jar,
    [
      'token' => $token,
      'picture' => new CURLFile($animation_png, 'image/png', 'pending-drawing.png'),
      'animation' => new CURLFile($valid_pch, 'application/octet-stream', 'pending-drawing.pch'),
    ]
  );
  $pending_drawing_lines = preg_split('/\r?\n/', trim($pending_drawing_body)) ?: [];
  $pending_drawing_image = (string)($pending_drawing_lines[1] ?? '');
  $pending_drawing_base = pathinfo($pending_drawing_image, PATHINFO_FILENAME);
  [$pending_form_status, $pending_form_body] = http_request($base_url . '?mode=pictmp', $cookie_jar);
  integration_test('a pending drawing hides the unrelated image upload field', static function () use (
    $pending_drawing_status, $pending_drawing_image, $pending_form_status, $pending_form_body
  ): bool {
    return $pending_drawing_status === 200
      && preg_match('/^\d{16}\.png$/D', $pending_drawing_image) === 1
      && $pending_form_status === 200
      && !str_contains($pending_form_body, 'name="image_upload"')
      && !str_contains($pending_form_body, 'name="replace_pending_image"');
  });

  $drawing_failure_db = new PDO('sqlite:' . $webroot . '/reita.db');
  $drawing_failure_db->exec("CREATE TRIGGER fail_drawing_insert BEFORE INSERT ON board_log
    BEGIN SELECT RAISE(ABORT, 'forced drawing insert failure'); END");
  $drawing_originals = [];
  foreach (glob($webroot . '/tmp/' . $pending_drawing_base . '.*') ?: [] as $path) {
    $drawing_originals[$path] = hash_file('sha256', $path);
  }
  $drawing_count_before = (int)$drawing_failure_db->query('SELECT COUNT(*) FROM board_log')->fetchColumn();
  try {
    [$drawing_failure_status] = http_request($base_url . '?mode=regist', $cookie_jar, [
      'mode' => 'regist', 'send' => '1', 'name' => 'drawing-rollback', 'mail' => '', 'url' => '',
      'sub' => 'Drawing rollback', 'com' => "描画保存失敗 {$marker}", 'pwd' => 'drawing-pass',
      'picfile' => $pending_drawing_image, 'ctype' => 'new', 'nsfw' => '1', 'token' => $token,
    ]);
  } finally {
    $drawing_failure_db->exec('DROP TRIGGER fail_drawing_insert');
  }
  integration_test('database failure preserves pending drawing and removes published files', static function () use (
    $drawing_failure_status, $drawing_originals, $webroot, $pending_drawing_base,
    $drawing_failure_db, $drawing_count_before
  ): bool {
    clearstatcache();
    if ($drawing_failure_status !== 500 || count($drawing_originals) < 3
      || (int)$drawing_failure_db->query('SELECT COUNT(*) FROM board_log')->fetchColumn() !== $drawing_count_before
      || (glob($webroot . '/img/' . $pending_drawing_base . '*') ?: []) !== []) return false;
    foreach ($drawing_originals as $path => $hash) {
      if (!is_file($path) || hash_file('sha256', $path) !== $hash) return false;
    }
    return true;
  });

  $pending_replacement_marker = 'pending-replacement-' . bin2hex(random_bytes(6));
  [$pending_replacement_status] = http_request($base_url . '?mode=regist', $cookie_jar, [
    'mode' => 'regist', 'send' => '1', 'name' => 'pending-replacement', 'mail' => '', 'url' => '',
    'sub' => 'Replace pending drawing', 'com' => "お絵かき差し替え {$pending_replacement_marker}", 'pwd' => 'replacement-pass',
    'invz' => '0', 'img_w' => '0', 'img_h' => '0', 'sodane' => '0', 'nsfw' => '0', 'token' => $token,
    'replace_pending_image' => '1',
    'image_upload' => new CURLFile($upload_source, 'image/png', 'replacement.png'),
  ]);
  $pending_replacement_db = new PDO('sqlite:' . $webroot . '/reita.db');
  $pending_replacement_statement = $pending_replacement_db->prepare(
    'SELECT picfile, tool FROM board_log WHERE com = :comment LIMIT 1'
  );
  $pending_replacement_statement->execute([':comment' => "お絵かき差し替え {$pending_replacement_marker}"]);
  $pending_replacement_row = $pending_replacement_statement->fetch(PDO::FETCH_ASSOC);
  integration_test('an uploaded image can replace a pending drawing and cleans its temporary files', static function () use (
    $pending_replacement_status, $pending_replacement_row, $pending_drawing_image,
    $pending_drawing_base, $webroot
  ): bool {
    return $pending_replacement_status === 200 && is_array($pending_replacement_row)
      && $pending_replacement_row['picfile'] !== $pending_drawing_image
      && $pending_replacement_row['tool'] === 'Upload'
      && !is_file($webroot . '/tmp/' . $pending_drawing_image)
      && !is_file($webroot . '/tmp/' . $pending_drawing_base . '.pch')
      && !is_file($webroot . '/tmp/' . $pending_drawing_base . '.dat');
  });

  // piccomは直前の絵を既定にするが、投稿途中画像が複数あれば選び直せる。
  [, $multiple_pending_first_body] = http_request($base_url . '?mode=animation_upload', $cookie_jar, [
    'token' => $token,
    'picture' => new CURLFile($animation_png, 'image/png', 'multiple-first.png'),
    'animation' => new CURLFile($valid_pch, 'application/octet-stream', 'multiple-first.pch'),
  ]);
  [, $multiple_pending_second_body] = http_request($base_url . '?mode=animation_upload', $cookie_jar, [
    'token' => $token,
    'picture' => new CURLFile($animation_png, 'image/png', 'multiple-second.png'),
    'animation' => new CURLFile($valid_pch, 'application/octet-stream', 'multiple-second.pch'),
  ]);
  $multiple_pending_first = (string)((preg_split('/\r?\n/', trim($multiple_pending_first_body)) ?: [])[1] ?? '');
  $multiple_pending_second = (string)((preg_split('/\r?\n/', trim($multiple_pending_second_body)) ?: [])[1] ?? '');
  [$multiple_piccom_status, $multiple_piccom_body] = http_request($base_url . '?mode=piccom', $cookie_jar);
  $multiple_marker = 'multiple-pending-' . bin2hex(random_bytes(6));
  [$multiple_selection_status] = http_request($base_url . '?mode=regist', $cookie_jar, [
    'mode' => 'regist', 'send' => '1', 'name' => 'multiple-pending', 'mail' => '', 'url' => '',
    'sub' => 'Multiple pending images', 'com' => "複数画像 {$multiple_marker}", 'pwd' => 'multiple-pending-pass',
    'picfile' => $multiple_pending_first, 'invz' => '0', 'img_w' => '0', 'img_h' => '0',
    'sodane' => '0', 'nsfw' => '0', 'token' => $token,
  ]);
  // Earlier test queries can keep an SQLite read snapshot open; use a fresh connection
  // so this assertion observes the post just made through HTTP.
  $multiple_pending_db = new PDO('sqlite:' . $webroot . '/reita.db');
  $multiple_pending_statement = $multiple_pending_db->prepare(
    'SELECT picfile FROM board_log WHERE com = :comment LIMIT 1'
  );
  $multiple_pending_statement->execute([':comment' => "複数画像 {$multiple_marker}"]);
  $multiple_pending_row = $multiple_pending_statement->fetch(PDO::FETCH_ASSOC);
  integration_test('piccom allows selecting another owned image when multiple pending drawings exist', static function () use (
    $multiple_piccom_status, $multiple_pending_first, $multiple_pending_second,
    $multiple_piccom_body, $multiple_selection_status, $multiple_pending_row
  ): bool {
    return $multiple_piccom_status === 200
      && $multiple_pending_first !== '' && $multiple_pending_second !== ''
      && str_contains($multiple_piccom_body, 'name="picfile"')
      && str_contains($multiple_piccom_body, $multiple_pending_first)
      && str_contains($multiple_piccom_body, $multiple_pending_second)
      && $multiple_selection_status === 200 && is_array($multiple_pending_row)
      && $multiple_pending_row['picfile'] === $multiple_pending_first;
  });

  $unsupported_avif_rejected = true;
  if (!function_exists('imagecreatefromavif')) {
    $avif_source = $root . '/unsupported-upload.avif';
    $avif_probe = pack('N', 24) . 'ftypavif' . pack('N', 0) . 'avifmif1';
    if (file_put_contents($avif_source, $avif_probe) !== strlen($avif_probe)) {
      throw new RuntimeException('Could not create unsupported AVIF upload probe.');
    }
    $avif_marker = 'unsupported-avif-' . bin2hex(random_bytes(6));
    $avif_comment = '非対応AVIF ' . $avif_marker;
    [$unsupported_avif_status, $unsupported_avif_body] = http_request($base_url . '?mode=regist', $cookie_jar, [
      'mode' => 'regist', 'send' => '1', 'name' => 'upload-test', 'mail' => '', 'url' => '',
      'sub' => 'Unsupported AVIF upload', 'com' => $avif_comment, 'pwd' => 'upload-delete-pass',
      'invz' => '0', 'img_w' => '0', 'img_h' => '0', 'sodane' => '0', 'nsfw' => '0', 'token' => $token,
      'image_upload' => new CURLFile($avif_source, 'image/avif', 'unsupported.avif'),
    ]);
    $avif_row_statement = $db->prepare('SELECT COUNT(*) FROM board_log WHERE com = :comment');
    $avif_row_statement->execute([':comment' => $avif_comment]);
    $unsupported_avif_rejected = $unsupported_avif_status === 415
      && str_contains($unsupported_avif_body, 'not supported by this server')
      && (int)$avif_row_statement->fetchColumn() === 0;
  }
  integration_test('unsupported AVIF uploads are rejected before database storage', static function () use (
    $unsupported_avif_rejected
  ): bool {
    return $unsupported_avif_rejected;
  });

  $monoreita_config_local = str_replace(
    "'paths' => ['theme' => 'starter'],",
    "'paths' => ['theme' => 'monoreita'],",
    $config_local
  );
  if ($monoreita_config_local === $config_local
    || file_put_contents($webroot . '/config.local.php', $monoreita_config_local) === false) {
    throw new RuntimeException('Could not select the monoreita theme for the HTTP test.');
  }
  if (is_resource($process)) {
    proc_terminate($process);
    proc_close($process);
    $process = null;
  }
  $monoreita_socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error_message);
  if ($monoreita_socket === false) throw new RuntimeException("Could not reserve monoreita test port: {$error_message}");
  $monoreita_address = stream_socket_get_name($monoreita_socket, false);
  fclose($monoreita_socket);
  $monoreita_port = (int)substr(strrchr((string)$monoreita_address, ':'), 1);
  $monoreita_base_url = "http://127.0.0.1:{$monoreita_port}/index.php";
  $process = proc_open(
    [PHP_BINARY, '-d', 'opcache.enable_cli=0', '-d', 'opcache.file_cache_only=0',
      '-S', "127.0.0.1:{$monoreita_port}", '-t', $webroot, __DIR__ . '/http-router.php'],
    [STDIN, $log, $log],
    $pipes,
    $webroot
  );
  if (!is_resource($process)) throw new RuntimeException('Could not start monoreita PHP server.');
  $monoreita_ready = false;
  for ($attempt = 0; $attempt < 50; $attempt++) {
    usleep(100000);
    try {
      [$monoreita_startup_status] = http_request($monoreita_base_url, $root . '/monoreita-ready-cookies.txt');
      if ($monoreita_startup_status === 200) {
        $monoreita_ready = true;
        break;
      }
    } catch (Throwable $ignored) {
    }
  }
  if (!$monoreita_ready) throw new RuntimeException('Monoreita PHP server did not become ready.');
  $monoreita_cookie_jar = $root . '/monoreita-cookies.txt';
  [$monoreita_login_form_status, $monoreita_login_form_body] = http_request(
    $monoreita_base_url . '?mode=admin_in', $monoreita_cookie_jar
  );
  $monoreita_session_id = cookie_value($monoreita_cookie_jar, 'noreita_session');
  $monoreita_token = $monoreita_session_id === null ? '' : hash('sha256', $monoreita_session_id);
  [$monoreita_login_status] = http_request($monoreita_base_url . '?mode=admin_login', $monoreita_cookie_jar, [
    'adminpass' => 'integration-admin-pass', 'token' => $monoreita_token,
  ]);
  [$monoreita_admin_status, $monoreita_admin_body] = http_request(
    $monoreita_base_url . '?mode=admin', $monoreita_cookie_jar
  );
  [$monoreita_public_status, $monoreita_public_body] = http_request(
    $monoreita_base_url, $monoreita_cookie_jar
  );
  [$monoreita_errorlog_status, $monoreita_errorlog_body] = http_request(
    $monoreita_base_url . '?mode=admin_errorlog', $monoreita_cookie_jar
  );
  [$monoreita_auditlog_status, $monoreita_auditlog_body] = http_request(
    $monoreita_base_url . '?mode=admin_auditlog', $monoreita_cookie_jar
  );
  [$monoreita_temporary_status, $monoreita_temporary_body] = http_request(
    $monoreita_base_url . '?mode=admin_temporary_images', $monoreita_cookie_jar
  );
  integration_test('monoreita renders administrator pages through BladeOne over HTTP', static function () use (
    $monoreita_login_form_status, $monoreita_login_form_body, $monoreita_login_status,
    $monoreita_admin_status, $monoreita_admin_body, $monoreita_public_status, $monoreita_public_body,
    $monoreita_errorlog_status, $monoreita_errorlog_body,
    $monoreita_auditlog_status, $monoreita_auditlog_body,
    $monoreita_temporary_status, $monoreita_temporary_body
  ): bool {
    return $monoreita_login_form_status === 200
      && str_contains($monoreita_login_form_body, 'mode=admin_login')
      && $monoreita_login_status === 302
      && $monoreita_admin_status === 200
      && str_contains($monoreita_admin_body, 'theme/monoreita/css/monoreita_index.min.css')
      && $monoreita_public_status === 200
      && str_contains($monoreita_public_body, 'class="admin-session-status"')
      && str_contains($monoreita_public_body, '管理者ログイン中')
      && str_contains($monoreita_admin_body, 'mode=admin_errorlog')
      && str_contains($monoreita_admin_body, 'mode=admin_auditlog')
      && str_contains($monoreita_admin_body, 'mode=admin_temporary_images')
      && !str_contains($monoreita_admin_body, '{{')
      && $monoreita_errorlog_status === 200
      && str_contains($monoreita_errorlog_body, '管理者向けエラーログ')
      && !str_contains($monoreita_errorlog_body, '@foreach')
      && $monoreita_auditlog_status === 200
      && str_contains($monoreita_auditlog_body, '管理操作の監査ログ')
      && !str_contains($monoreita_auditlog_body, '@foreach')
      && $monoreita_temporary_status === 200
      && str_contains($monoreita_temporary_body, '一時画像の管理')
      && str_contains($monoreita_temporary_body, 'mode=admin_temporary_images_manage');
  });
  $base_url = $monoreita_base_url;

  [$admin_logout_status] = http_request($base_url . '?mode=admin_logout', $cookie_jar, ['token' => $token]);
  [$admin_after_logout_status] = http_request($base_url . '?mode=admin', $cookie_jar);
  [$admin_auditlog_after_logout_status] = http_request($base_url . '?mode=admin_auditlog', $cookie_jar);
  [$admin_detail_after_logout_status] = http_request($base_url . '?mode=admin_post&id=1', $cookie_jar);
  [$admin_edit_after_logout_status] = http_request($base_url . '?mode=admin_edit&id=1', $cookie_jar);
  integration_test('administrator logout destroys access to every administration screen', static function () use (
    $admin_logout_status, $admin_after_logout_status, $admin_auditlog_after_logout_status,
    $admin_detail_after_logout_status, $admin_edit_after_logout_status
  ): bool {
    return $admin_logout_status === 302
      && $admin_after_logout_status === 403
      && $admin_auditlog_after_logout_status === 403
      && $admin_detail_after_logout_status === 403
      && $admin_edit_after_logout_status === 403;
  });

  [, $rate_limit_form_body] = http_request($base_url . '?mode=admin_in', $cookie_jar);
  $rate_limit_session_id = cookie_value($cookie_jar, 'noreita_session');
  $rate_limit_token = $rate_limit_session_id === null ? '' : hash('sha256', $rate_limit_session_id);
  $rate_limit_password = 'never-log-this-password';
  [$rate_first_status, $rate_first_body] = http_request($base_url . '?mode=admin_login', $cookie_jar, [
    'adminpass' => $rate_limit_password, 'token' => $rate_limit_token,
  ]);
  [$rate_second_status, $rate_second_body] = http_request($base_url . '?mode=admin_login', $cookie_jar, [
    'adminpass' => $rate_limit_password, 'token' => $rate_limit_token,
  ]);
  [$rate_third_status, $rate_third_body, , $rate_third_headers] = http_request($base_url . '?mode=admin_login', $cookie_jar, [
    'adminpass' => $rate_limit_password, 'token' => $rate_limit_token,
  ]);
  [$rate_correct_status, $rate_correct_body, , $rate_correct_headers] = http_request($base_url . '?mode=admin_login', $cookie_jar, [
    'adminpass' => 'integration-admin-pass', 'token' => $rate_limit_token,
  ]);
  $rate_limit_records = glob($webroot . '/session/admin-login-*.json') ?: [];
  $rate_limit_record = count($rate_limit_records) === 1
    ? (string)file_get_contents($rate_limit_records[0])
    : '';
  $rate_limit_log = is_file($server_log) ? (string)file_get_contents($server_log) : '';
  integration_test('administrator login rate limit blocks repeated failures without storing passwords', static function () use (
    $rate_limit_form_body, $rate_first_status, $rate_first_body, $rate_second_status, $rate_second_body,
    $rate_third_status, $rate_third_body, $rate_correct_status, $rate_correct_body,
    $rate_third_headers, $rate_correct_headers, $rate_limit_password,
    $rate_limit_records, $rate_limit_record, $rate_limit_log
  ): bool {
    $responses = $rate_limit_form_body . $rate_first_body . $rate_second_body . $rate_third_body . $rate_correct_body;
    $third_retry_after = $rate_third_headers['retry-after'] ?? '';
    $correct_retry_after = $rate_correct_headers['retry-after'] ?? '';
    return $rate_first_status === 403
      && $rate_second_status === 403
      && $rate_third_status === 429
      && $rate_correct_status === 429
      && ctype_digit($third_retry_after) && (int)$third_retry_after > 0
      && ctype_digit($correct_retry_after) && (int)$correct_retry_after > 0
      && str_contains($rate_third_body, 'Too many administrator login attempts')
      && count($rate_limit_records) === 1
      && !str_contains($rate_limit_record, $rate_limit_password)
      && !str_contains($responses, $rate_limit_password)
      && !str_contains($rate_limit_log, $rate_limit_password);
  });

  $diary_config_local = str_replace(
    "    'misskey_note' => false,",
    "    'misskey_note' => false,\n    'diary_mode' => true,\n    'diary_allow_public_replies' => false,",
    $config_local
  );
  if ($diary_config_local === $config_local
    || file_put_contents($webroot . '/config.local.php', $diary_config_local) === false) {
    throw new RuntimeException('Could not enable diary mode for the HTTP test.');
  }

  if (is_resource($process)) {
    proc_terminate($process);
    proc_close($process);
    $process = null;
  }
  $diary_socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error_message);
  if ($diary_socket === false) throw new RuntimeException("Could not reserve diary test port: {$error_message}");
  $diary_address = stream_socket_get_name($diary_socket, false);
  fclose($diary_socket);
  $diary_port = (int)substr(strrchr((string)$diary_address, ':'), 1);
  $diary_base_url = "http://127.0.0.1:{$diary_port}/index.php";
  $process = proc_open(
    [PHP_BINARY, '-d', 'opcache.enable_cli=0', '-d', 'opcache.file_cache_only=0',
      '-S', "127.0.0.1:{$diary_port}", '-t', $webroot, __DIR__ . '/http-router.php'],
    [STDIN, $log, $log],
    $pipes,
    $webroot
  );
  if (!is_resource($process)) throw new RuntimeException('Could not start diary-mode PHP server.');
  $diary_ready = false;
  for ($attempt = 0; $attempt < 50; $attempt++) {
    usleep(100000);
    try {
      [$diary_startup_status] = http_request($diary_base_url, $root . '/diary-ready-cookies.txt');
      if ($diary_startup_status === 200) {
        $diary_ready = true;
        break;
      }
    } catch (Throwable $ignored) {
    }
  }
  if (!$diary_ready) throw new RuntimeException('Diary-mode PHP server did not become ready.');

  $diary_cookie_jar = $root . DIRECTORY_SEPARATOR . 'diary-cookies.txt';
  [$diary_form_status, $diary_form_body] = http_request($diary_base_url, $diary_cookie_jar);
  http_request($diary_base_url . '?mode=admin_in', $diary_cookie_jar);
  $diary_session_id = cookie_value($diary_cookie_jar, 'noreita_session');
  $diary_token = $diary_session_id === null ? '' : hash('sha256', $diary_session_id);
  $diary_parent_statement = $db->prepare('SELECT tid FROM board_log WHERE com = :comment LIMIT 1');
  $diary_parent_statement->execute([':comment' => "画像アップロード {$upload_marker}"]);
  $diary_parent_id = (int)$diary_parent_statement->fetchColumn();
  [$diary_new_post_status] = http_request($diary_base_url . '?mode=regist', $diary_cookie_jar, [
    'mode' => 'regist', 'send' => '1', 'name' => 'public diary visitor', 'mail' => '', 'url' => '',
    'sub' => 'Denied diary post', 'com' => 'This new post must be rejected.', 'pwd' => 'public-pass',
    'invz' => '0', 'img_w' => '0', 'img_h' => '0', 'sodane' => '0', 'nsfw' => '0', 'token' => $diary_token,
  ]);
  [$diary_upload_form_status] = http_request($diary_base_url . '?mode=pictmp', $diary_cookie_jar);
  [$diary_reply_denied_status] = http_request($diary_base_url . '?mode=reply', $diary_cookie_jar, [
    'mode' => 'reply', 'send' => '1', 'resto' => (string)$diary_parent_id,
    'name' => 'public diary visitor', 'mail' => '', 'url' => '', 'sub' => '',
    'com' => 'This reply must be rejected.', 'pwd' => 'public-pass',
    'invz' => '0', 'img_w' => '0', 'img_h' => '0', 'sodane' => '0', 'nsfw' => '0', 'token' => $diary_token,
  ]);
  integration_test('diary mode rejects public new posts, uploads, and replies when replies are disabled', static function () use (
    $diary_form_status, $diary_form_body, $diary_new_post_status, $diary_upload_form_status, $diary_reply_denied_status
  ): bool {
    return $diary_form_status === 200
      && $diary_new_post_status === 403
      && $diary_upload_form_status === 403
      && $diary_reply_denied_status === 403;
  });

  // OPcacheが秒単位の更新時刻で設定ファイルを検出する環境でも、次の設定を読み直せるようにします。
  sleep(1);
  $diary_replies_config = str_replace(
    "    'diary_allow_public_replies' => false,",
    "    'diary_allow_public_replies' => true,",
    $diary_config_local
  );
  if ($diary_replies_config === $diary_config_local
    || file_put_contents($webroot . '/config.local.php', $diary_replies_config) === false) {
    throw new RuntimeException('Could not enable public diary replies for the HTTP test.');
  }
  if (is_resource($process)) {
    proc_terminate($process);
    proc_close($process);
    $process = null;
  }
  $diary_replies_socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error_message);
  if ($diary_replies_socket === false) throw new RuntimeException("Could not reserve diary reply test port: {$error_message}");
  $diary_replies_address = stream_socket_get_name($diary_replies_socket, false);
  fclose($diary_replies_socket);
  $diary_replies_port = (int)substr(strrchr((string)$diary_replies_address, ':'), 1);
  $diary_replies_url = "http://127.0.0.1:{$diary_replies_port}/index.php";
  $process = proc_open(
    [PHP_BINARY, '-d', 'opcache.enable_cli=0', '-d', 'opcache.file_cache_only=0',
      '-S', "127.0.0.1:{$diary_replies_port}", '-t', $webroot, __DIR__ . '/http-router.php'],
    [STDIN, $log, $log],
    $pipes,
    $webroot
  );
  if (!is_resource($process)) throw new RuntimeException('Could not restart PHP server for public diary replies.');
  $diary_replies_ready = false;
  for ($attempt = 0; $attempt < 50; $attempt++) {
    usleep(100000);
    try {
      [$diary_replies_startup_status] = http_request($diary_replies_url, $root . '/diary-replies-ready-cookies.txt');
      if ($diary_replies_startup_status === 200) {
        $diary_replies_ready = true;
        break;
      }
    } catch (Throwable $ignored) {
    }
  }
  if (!$diary_replies_ready) throw new RuntimeException('Public-reply diary PHP server did not become ready.');
  $diary_replies_cookie_jar = $root . DIRECTORY_SEPARATOR . 'diary-replies-cookies.txt';
  http_request($diary_replies_url . '?mode=admin_in', $diary_replies_cookie_jar);
  $diary_replies_session_id = cookie_value($diary_replies_cookie_jar, 'noreita_session');
  $diary_replies_token = $diary_replies_session_id === null ? '' : hash('sha256', $diary_replies_session_id);
  $diary_reply_marker = '日記返信-' . bin2hex(random_bytes(6));
  [$diary_reply_allowed_status, $diary_reply_allowed_body] = http_request($diary_replies_url . '?mode=reply', $diary_replies_cookie_jar, [
    'mode' => 'reply', 'send' => '1', 'resto' => (string)$diary_parent_id,
    'name' => 'public diary visitor', 'mail' => '', 'url' => '', 'sub' => '',
    'com' => $diary_reply_marker, 'pwd' => 'public-pass',
    'invz' => '0', 'img_w' => '0', 'img_h' => '0', 'sodane' => '0', 'nsfw' => '0', 'token' => $diary_replies_token,
  ]);
  [$diary_new_post_still_denied_status] = http_request($diary_replies_url . '?mode=regist', $diary_replies_cookie_jar, [
    'mode' => 'regist', 'send' => '1', 'name' => 'public diary visitor', 'mail' => '', 'url' => '',
    'sub' => 'Still denied diary post', 'com' => 'This new post must still be rejected.', 'pwd' => 'public-pass',
    'invz' => '0', 'img_w' => '0', 'img_h' => '0', 'sodane' => '0', 'nsfw' => '0', 'token' => $diary_replies_token,
  ]);
  integration_test('diary mode can allow public replies without allowing new posts', static function () use (
    $diary_reply_allowed_status, $diary_reply_allowed_body, $diary_new_post_still_denied_status
  ): bool {
    return $diary_reply_allowed_status === 200
      && (str_contains($diary_reply_allowed_body, '書き込みに成功しました。')
        || str_contains($diary_reply_allowed_body, 'Successfully posted.'))
      && $diary_new_post_still_denied_status === 403;
  });
} catch (Throwable $e) {
  echo "FAIL: integration setup ({$e->getMessage()})\n";
  $failed++;
  if (is_file($server_log)) {
    echo "--- server log ---\n" . file_get_contents($server_log) . "\n";
  }
} finally {
  if ($failed > 0 && is_file($server_log)) {
    echo "--- server log ---\n" . file_get_contents($server_log) . "\n";
  }
  if ($failed > 0) {
    foreach (glob($webroot . '/errorlog/error-*.log') ?: [] as $application_log) {
      echo "--- application error log ---\n" . file_get_contents($application_log) . "\n";
    }
  }
  if (is_resource($process)) {
    proc_terminate($process);
    proc_close($process);
  }
  remove_tree($root);
}

echo "\nIntegration tests: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
