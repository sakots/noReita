<?php
declare(strict_types=1);

if (!extension_loaded('curl') || !extension_loaded('pdo_sqlite')) {
  fwrite(STDERR, "curl and pdo_sqlite extensions are required.\n");
  exit(1);
}

$source = dirname(__DIR__) . '/noreita';
if (!is_file($source . '/BladeOne/lib/BladeOne.php')) {
  fwrite(STDERR, "BladeOne is not installed. Run this test against an assembled release tree.\n");
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
    if ($item->isDot() || in_array($item->getFilename(), $skip, true) || $item->getFilename() === 'config.php') {
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

function http_request(string $url, string $cookie_jar, ?array $post = null): array {
  $curl = curl_init($url);
  curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_COOKIEJAR => $cookie_jar,
    CURLOPT_COOKIEFILE => $cookie_jar,
    CURLOPT_HTTPHEADER => ['Host: localhost', 'Origin: http://localhost', 'X-Forwarded-For: 127.0.0.1'],
  ]);
  if ($post !== null) {
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
  }
  $body = curl_exec($curl);
  $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
  $error = curl_error($curl);
  if ($body === false) {
    throw new RuntimeException("HTTP request failed: {$error}");
  }
  return [$status, $body];
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

try {
  copy_tree($source, $webroot);
  $config = file_get_contents($webroot . '/config.example.php');
  if ($config === false) throw new RuntimeException('Could not read config.example.php');
  $config = str_replace("\$admin_pass = 'admin_pass';", "\$admin_pass = 'integration-admin-pass';", $config);
  $config = str_replace("const BASE = 'https://example.com/noreita/';", "const BASE = 'http://localhost/';", $config);
  $config = str_replace('const EXTERNAL_IMAGE_THUMB = 1;', 'const EXTERNAL_IMAGE_THUMB = 0;', $config);
  $config = str_replace('const USE_MISSKEY_NOTE = 1;', 'const USE_MISSKEY_NOTE = 0;', $config);
  if (file_put_contents($webroot . '/config.php', $config) === false) {
    throw new RuntimeException('Could not create test config.php');
  }

  $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error_message);
  if ($socket === false) throw new RuntimeException("Could not reserve port: {$error_message}");
  $address = stream_socket_get_name($socket, false);
  fclose($socket);
  $port = (int)substr(strrchr((string)$address, ':'), 1);
  $base_url = "http://127.0.0.1:{$port}/index.php";

  $log = fopen($server_log, 'ab');
  if ($log === false) throw new RuntimeException('Could not create server log');
  $process = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $webroot], [STDIN, $log, $log], $pipes, $webroot);
  if (!is_resource($process)) throw new RuntimeException('Could not start PHP server');

  $ready = false;
  for ($attempt = 0; $attempt < 50; $attempt++) {
    usleep(100000);
    try {
      [$status] = http_request($base_url, $cookie_jar);
      if ($status === 200) {
        $ready = true;
        break;
      }
    } catch (Throwable $ignored) {
    }
  }
  if (!$ready) throw new RuntimeException('PHP server did not become ready');

  integration_test('new board creates versioned database', static function () use ($webroot): bool {
    $db = new PDO('sqlite:' . $webroot . '/reita.db');
    return (int)$db->query('PRAGMA user_version')->fetchColumn() === 1
      && (int)$db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='board_log'")->fetchColumn() === 1;
  });

  // pictmp initializes the CSRF token in the same session used for posting.
  [$status] = http_request($base_url . '?mode=pictmp', $cookie_jar);
  $session_id = cookie_value($cookie_jar, 'noreita_session');
  $token = $session_id === null ? '' : hash('sha256', $session_id);

  $marker = 'integration-' . bin2hex(random_bytes(6));
  [$post_status, $post_body] = http_request($base_url . '?mode=regist', $cookie_jar, [
    'mode' => 'regist', 'send' => '1', 'name' => "Integration O'Brien", 'mail' => '', 'url' => '',
    'sub' => "Integration's subject", 'com' => "結合テスト user's {$marker}", 'pwd' => 'delete-pass',
    'invz' => '0', 'img_w' => '0', 'img_h' => '0', 'sodane' => '0', 'nsfw' => '0', 'token' => $token,
  ]);

  $db = new PDO('sqlite:' . $webroot . '/reita.db');
  $row = $db->query('SELECT tid, a_name, sub, com FROM board_log ORDER BY tid DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
  integration_test('post is stored through HTTP', static function () use ($status, $post_status, $row, $marker): bool {
    return $status === 200 && $post_status === 200 && is_array($row)
      && $row['a_name'] === "Integration O'Brien" && $row['sub'] === "Integration's subject"
      && $row['com'] === "結合テスト user's {$marker}";
  });

  $search_term = "user's {$marker}";
  [$search_status, $search_body] = http_request($base_url . '?mode=search&tag=tag&search=' . rawurlencode($search_term), $cookie_jar);
  integration_test('search finds the posted comment', static function () use ($search_status, $search_body, $marker): bool {
    return $search_status === 200 && str_contains($search_body, $marker) && str_contains($search_body, '1件');
  });

  $post_id = (int)($row['tid'] ?? 0);
  $password_hash_before_edit = (string)$db->query('SELECT pwd FROM board_log WHERE tid = ' . $post_id)->fetchColumn();
  [$rejected_edit_status] = http_request($base_url . '?mode=editexec', $cookie_jar, [
    'mode' => 'editexec', 'e_no' => (string)$post_id, 'name' => 'Attacker', 'mail' => '', 'url' => '',
    'sub' => 'Unauthorized edit', 'com' => "不正な編集 {$marker}", 'pwd' => 'wrong-pass',
    'sodane' => '0', 'token' => $token,
  ]);
  $after_rejected_edit = $db->query('SELECT sub, com, pwd FROM board_log WHERE tid = ' . $post_id)->fetch(PDO::FETCH_ASSOC);
  integration_test('edit rejects an invalid password without changing the post', static function () use ($rejected_edit_status, $after_rejected_edit, $password_hash_before_edit): bool {
    return $rejected_edit_status === 200 && is_array($after_rejected_edit)
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

  [$delete_status, $delete_body] = http_request($base_url, $cookie_jar, [
    'mode' => 'del', 'delno' => (string)$post_id, 'pwd' => 'delete-pass',
  ]);
  $remaining = (int)$db->query('SELECT COUNT(*) FROM board_log WHERE tid = ' . $post_id)->fetchColumn();
  integration_test('delete removes the post through HTTP', static function () use ($delete_status, $remaining): bool {
    return $delete_status === 200 && $remaining === 0;
  });

  [$empty_status, $empty_body] = http_request($base_url . '?mode=search&tag=tag&search=' . rawurlencode($search_term), $cookie_jar);
  integration_test('deleted post disappears from search', static function () use ($empty_status, $empty_body): bool {
    return $empty_status === 200 && str_contains($empty_body, '0件');
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
  if (is_resource($process)) {
    proc_terminate($process);
    proc_close($process);
  }
  remove_tree($root);
}

echo "\nIntegration tests: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
