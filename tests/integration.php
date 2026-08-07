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
  $skip = ['backup', 'cache', 'errorlog', 'img', 'session', 'temp', 'thumb', 'thumbnail', 'tmp'];
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
      'Client-IP: ' . $forwarded_for, 'X-Forwarded-For: ' . $forwarded_for,
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
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
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
    'login' => ['max_failures' => 3],
  ],
  'site' => ['base_url' => 'http://localhost/'],
  'features' => [
    'external_image_thumbnail' => false,
    'misskey_note' => false,
  ],
];
PHP;
  if (file_put_contents($webroot . '/config.local.php', $config_local) === false) {
    throw new RuntimeException('Could not create test config.local.php');
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
    [PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $webroot, __DIR__ . '/http-router.php'],
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
    'reita.db' => 'SQLite format',
    'session/http-access-probe' => 'session-secret',
    'backup/http-access-probe.db' => 'backup-secret',
    'cache/http-access-probe.bladec' => 'blade-cache-secret',
    'errorlog/http-access-probe.log' => 'error-log-secret',
  ];
  foreach ($protected_probes as $relative_path => $secret) {
    $probe_path = $webroot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative_path);
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
    return count($protected_results) === 7 && !in_array(false, $protected_results, true);
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

  [$misskey_callback_status, $misskey_callback_body] = http_request(
    $origin_url . '/connect_misskey_api.php', $cookie_jar
  );
  integration_test('Misskey callback initializes standalone dependencies', static function () use (
    $misskey_callback_status, $misskey_callback_body
  ): bool {
    return $misskey_callback_status === 200
      && str_contains($misskey_callback_body, 'セッションがありません')
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
  [$status] = http_request($base_url . '?mode=pictmp', $cookie_jar);
  $session_id = cookie_value($cookie_jar, 'noreita_session');
  $token = $session_id === null ? '' : hash('sha256', $session_id);

  [$admin_unauthorized_status] = http_request($base_url . '?mode=admin', $cookie_jar);
  [$admin_detail_unauthorized_status] = http_request($base_url . '?mode=admin_post&id=1', $cookie_jar);
  [$admin_edit_unauthorized_status] = http_request($base_url . '?mode=admin_edit&id=1', $cookie_jar);
  [$admin_manage_unauthorized_status] = http_request($base_url . '?mode=admin_manage', $cookie_jar, [
    'operation' => 'hide', 'delno' => ['1'], 'token' => $token,
  ]);
  integration_test('administration routes require a login session', static function () use (
    $admin_unauthorized_status, $admin_detail_unauthorized_status,
    $admin_edit_unauthorized_status, $admin_manage_unauthorized_status
  ): bool {
    return $admin_unauthorized_status === 403
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
  integration_test('administrator login persists and clears prior failures', static function () use (
    $admin_login_status, $admin_status, $admin_body, $login_attempt_records_after_success
  ): bool {
    return $admin_login_status === 302 && $admin_status === 200
      && $login_attempt_records_after_success === []
      && str_contains($admin_body, 'ADMIN MODE')
      && str_contains($admin_body, '基本統計')
      && str_contains($admin_body, '総投稿数')
      && str_contains($admin_body, '画像ディレクトリ:')
      && str_contains($admin_body, 'mode=admin_logout')
      && str_contains($admin_body, 'mode=admin_manage')
      && str_contains($admin_body, 'value="hide"')
      && str_contains($admin_body, 'value="show"')
      && str_contains($admin_body, 'value="delete"');
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
  integration_test('invalid CSRF token is rejected through HTTP', static function () use ($invalid_csrf_status, $invalid_csrf_body): bool {
    return $invalid_csrf_status === 403 && str_contains($invalid_csrf_body, 'CSRF token mismatch');
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

  $search_term = "user's {$marker}";
  [$search_status, $search_body] = http_request($base_url . '?mode=search&tag=tag&search=' . rawurlencode($search_term), $cookie_jar);
  integration_test('search finds the posted comment', static function () use ($search_status, $search_body, $marker): bool {
    return $search_status === 200 && str_contains($search_body, $marker) && str_contains($search_body, '1件');
  });

  $post_id = (int)($row['tid'] ?? 0);
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
      && str_contains($admin_detail_edit_body, 'name="e_no"');
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
    'sub' => "Administrator's edit", 'com' => "管理者編集 user's {$marker}", 'pwd' => 'integration-admin-pass',
    'sodane' => '0', 'token' => $token,
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
  file_put_contents($webroot . '/tmp/' . $replacement_base . '.png', $png);
  file_put_contents(
    $webroot . '/tmp/' . $replacement_base . '.dat',
    "127.0.0.1\tlocalhost\tagent\t.png\tcode\t{$replacement_code}\t200\t260\t0\tneo"
  );
  file_put_contents($webroot . '/tmp/' . $replacement_base . '.pch', 'replacement animation');
  $encrypted_password = openssl_encrypt(
    'image-pass', 'aes-128-cbc', '0qYzf1x6nyN4gS1', OPENSSL_RAW_DATA, 'T3pkYxNyjN7Wz3pu'
  );
  if ($encrypted_password === false) throw new RuntimeException('Could not encrypt replacement password');
  if (!replace_cookie_value($cookie_jar, 'pwd_cookie', 'another-post-pass')) {
    throw new RuntimeException('Could not prepare a mismatched saved password');
  }
  [$replacement_status, $replacement_body] = http_request(
    $base_url . '?mode=picrep&no=' . $image_post_id . '&repcode=' . rawurlencode($replacement_code)
      . '&pwd=' . bin2hex($encrypted_password) . '&stime=300',
    $cookie_jar,
    ['nsfw' => '0']
  );
  $replaced_image_row = $db->query('SELECT picfile, pchfile, nsfw, thumbnail FROM board_log WHERE tid = ' . $image_post_id)->fetch(PDO::FETCH_ASSOC);
  $replacement_thumbnail = (string)($replaced_image_row['thumbnail'] ?? '');
  clearstatcache(true, $webroot . '/img/' . $continued_from_thumbnail);
  integration_test('continued NSFW drawing can become safe with a fresh thumbnail', static function () use (
    $replacement_status, $replacement_body, $replaced_image_row, $replacement_base,
    $replacement_thumbnail, $continued_from_thumbnail, $webroot
  ): bool {
    return $replacement_status === 200 && is_array($replaced_image_row)
      && $replaced_image_row['picfile'] === $replacement_base . '.png'
      && $replaced_image_row['pchfile'] === $replacement_base . '.pch'
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

  [$admin_logout_status] = http_request($base_url . '?mode=admin_logout', $cookie_jar, ['token' => $token]);
  [$admin_after_logout_status] = http_request($base_url . '?mode=admin', $cookie_jar);
  [$admin_detail_after_logout_status] = http_request($base_url . '?mode=admin_post&id=1', $cookie_jar);
  [$admin_edit_after_logout_status] = http_request($base_url . '?mode=admin_edit&id=1', $cookie_jar);
  integration_test('administrator logout destroys access to every administration screen', static function () use (
    $admin_logout_status, $admin_after_logout_status,
    $admin_detail_after_logout_status, $admin_edit_after_logout_status
  ): bool {
    return $admin_logout_status === 302
      && $admin_after_logout_status === 403
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
