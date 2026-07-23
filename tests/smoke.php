<?php
declare(strict_types=1);

const LANG = 'Japanese';
const PHP_SELF = 'index.php';
const PMAX_W = 800;
const PMAX_H = 800;
const PTIME_D = '日';
const PTIME_H = '時間';
const PTIME_M = '分';
const PTIME_S = '秒';
const ID_CYCLE = '0';
const ID_SEED = 'smoke-test-seed';

require_once dirname(__DIR__) . '/noreita/functions.php';
require_once dirname(__DIR__) . '/noreita/request_security.inc.php';
require_once dirname(__DIR__) . '/noreita/request_info.inc.php';
require_once dirname(__DIR__) . '/noreita/thumbnail.inc.php';
require_once dirname(__DIR__) . '/noreita/external_image.inc.php';
require_once dirname(__DIR__) . '/noreita/database.inc.php';
require_once dirname(__DIR__) . '/noreita/initialization.inc.php';
require_once dirname(__DIR__) . '/noreita/image.inc.php';
require_once dirname(__DIR__) . '/noreita/post.inc.php';
require_once dirname(__DIR__) . '/noreita/share.inc.php';
require_once dirname(__DIR__) . '/plugins/check-image-consistency.php';

$passed = 0;
$failed = 0;

function smoke_test(string $name, callable $test): void {
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

smoke_test('required PHP extensions', static function (): bool {
  foreach (['curl', 'gd', 'mbstring', 'pdo_sqlite'] as $extension) {
    if (!extension_loaded($extension)) {
      throw new RuntimeException("missing extension: {$extension}");
    }
  }
  return true;
});

smoke_test('session directory ships an Apache access denial rule', static function (): bool {
  $rule = file_get_contents(dirname(__DIR__) . '/noreita/session/.htaccess');
  return is_string($rule)
    && str_contains($rule, 'Require all denied')
    && str_contains($rule, 'Deny from all');
});

smoke_test('request client IP is resolved from supported sources', static function (): bool {
  return RequestInfo::clientIp(['REMOTE_ADDR' => '203.0.113.10']) === '203.0.113.10'
    && RequestInfo::clientIp([
      'HTTP_X_FORWARDED_FOR' => 'invalid, 198.51.100.20, 203.0.113.20',
      'REMOTE_ADDR' => '192.0.2.10',
    ]) === '198.51.100.20'
    && RequestInfo::clientIp(['HTTP_CLIENT_IP' => 'not-an-ip']) === '';
});

smoke_test('administrator session validates password changes and idle timeout', static function (): bool {
  $now = 1_700_000_000;
  $session = [
    'admin_auth_fingerprint' => AdminAuth::sessionFingerprint('admin-secret'),
    'admin_auth_last_activity' => $now - 60,
  ];
  return AdminAuth::hasValidSession($session, 'admin-secret', 1800, $now)
    && !AdminAuth::hasValidSession($session, 'changed-secret', 1800, $now)
    && !AdminAuth::hasValidSession($session, 'admin-secret', 30, $now)
    && !AdminAuth::hasValidSession($session, 'admin-secret', 1800, $now - 120);
});

smoke_test('Blade include names match template filename case', static function (): bool {
  $theme = dirname(__DIR__) . '/noreita/theme/monoreita';
  $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($theme));
  foreach ($iterator as $file) {
    if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) continue;
    $source = file_get_contents($file->getPathname());
    if (!is_string($source)) return false;
    preg_match_all("/@include\\(['\"]([^'\"]+)['\"]/", $source, $matches);
    foreach ($matches[1] as $include) {
      $path = $theme . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $include) . '.blade.php';
      if (!is_file($path)) {
        throw new RuntimeException("missing template {$include} referenced by {$file->getFilename()}");
      }
    }
  }
  return true;
});

smoke_test('SQLite read and write', static function (): bool {
  $db = new PDO('sqlite::memory:');
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $db->exec('CREATE TABLE smoke (id INTEGER PRIMARY KEY, value TEXT NOT NULL)');
  $statement = $db->prepare('INSERT INTO smoke (value) VALUES (:value)');
  $statement->execute(['value' => 'noReita']);
  return $db->query('SELECT value FROM smoke')->fetchColumn() === 'noReita';
});

smoke_test('database migration and backup', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_db_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) {
    throw new RuntimeException('could not create temporary directory');
  }
  $database_file = $directory . DIRECTORY_SEPARATOR . 'smoke.db';
  $backup_dir = $directory . DIRECTORY_SEPARATOR . 'backup';

  try {
    $db = new PDO('sqlite:' . $database_file);
    $migrator = new DatabaseMigrator($db, $database_file, $backup_dir);
    if ($migrator->migrate() !== null || $migrator->schemaVersion() !== DatabaseMigrator::SCHEMA_VERSION) {
      return false;
    }

    $db->exec("INSERT INTO board_log (com) VALUES ('preserved')");
    $db->exec('PRAGMA user_version = 0');
    $backup = $migrator->migrate();
    if ($backup === null || !is_file($backup) || $migrator->schemaVersion() !== DatabaseMigrator::SCHEMA_VERSION) {
      return false;
    }

    $backup_db = new PDO('sqlite:' . $backup);
    return $backup_db->query('SELECT com FROM board_log')->fetchColumn() === 'preserved';
  } finally {
    foreach (glob($directory . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . '*.db') ?: [] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_file($database_file)) unlink($database_file);
    if (is_dir($backup_dir)) rmdir($backup_dir);
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('legacy SQLite database backup', static function (): bool {
  if (!class_exists('SQLite3')) return true;

  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_legacy_db_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) return false;
  $database_file = $directory . DIRECTORY_SEPARATOR . 'source.db';
  $backup_file = $directory . DIRECTORY_SEPARATOR . 'backup.db';

  try {
    $db = new PDO('sqlite:' . $database_file);
    $db->exec('CREATE TABLE backup_test (value TEXT NOT NULL)');
    $db->exec("INSERT INTO backup_test VALUES ('preserved')");
    $migrator = new DatabaseMigrator($db, $database_file, $directory);
    $method = new ReflectionMethod($migrator, 'createLegacyBackup');
    $method->setAccessible(true);
    $method->invoke($migrator, $backup_file);

    $backup = new PDO('sqlite:' . $backup_file);
    return $backup->query('SELECT value FROM backup_test')->fetchColumn() === 'preserved';
  } finally {
    foreach ([$database_file, $database_file . '-wal', $database_file . '-shm', $backup_file] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('application initialization prepares runtime state', static function (): bool {
  $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_init_' . bin2hex(random_bytes(8));
  if (!mkdir($root, 0700)) return false;
  $database_file = $root . DIRECTORY_SEPARATOR . 'board.db';
  $backup_dir = $root . DIRECTORY_SEPARATOR . 'backup';
  $directories = [
    $root . DIRECTORY_SEPARATOR . 'img',
    $root . DIRECTORY_SEPARATOR . 'temp',
    $root . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'session',
  ];
  try {
    $initializer = new ApplicationInitializer(
      'sqlite:' . $database_file, $database_file, $backup_dir, $root, $directories, 0700
    );
    $initializer->prepareDirectories();
    $initializer->migrateDatabase();
    $initializer->secureDatabaseFile();
    $database = new PDO('sqlite:' . $database_file);
    $schema_version = (int)$database->query('PRAGMA user_version')->fetchColumn();
    $database = null;
    return count(ApplicationInitializer::securityHeaders()) === 5
      && $schema_version === DatabaseMigrator::SCHEMA_VERSION
      && !array_filter($directories, static fn(string $directory): bool => !is_dir($directory))
      && (fileperms($database_file) & 0777) === 0600;
  } finally {
    foreach ([$database_file, $database_file . '-wal', $database_file . '-shm'] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_dir($backup_dir)) rmdir($backup_dir);
    if (is_dir($directories[2])) rmdir($directories[2]);
    if (is_dir($root . DIRECTORY_SEPARATOR . 'nested')) rmdir($root . DIRECTORY_SEPARATOR . 'nested');
    foreach (array_slice($directories, 0, 2) as $directory) if (is_dir($directory)) rmdir($directory);
    if (is_dir($root)) rmdir($root);
  }
});

smoke_test('version 2 database is not modified automatically', static function (): bool {
  $database_file = tempnam(sys_get_temp_dir(), 'noreita_v2_');
  if ($database_file === false) {
    throw new RuntimeException('could not create temporary database');
  }
  $backup_dir = $database_file . '_backup';

  try {
    $db = new PDO('sqlite:' . $database_file);
    $db->exec('CREATE TABLE tlog (tid INTEGER PRIMARY KEY)');
    $migrator = new DatabaseMigrator($db, $database_file, $backup_dir);
    try {
      $migrator->migrate();
    } catch (RuntimeException $e) {
      return str_contains($e->getMessage(), 'Version 2')
        && (int)$db->query('SELECT COUNT(*) FROM tlog')->fetchColumn() === 0
        && !is_dir($backup_dir);
    }
    return false;
  } finally {
    if (is_file($database_file)) unlink($database_file);
    if (is_dir($backup_dir)) rmdir($backup_dir);
  }
});

smoke_test('failed database operation is rolled back', static function (): bool {
  $db = new PDO('sqlite::memory:');
  $migrator = new DatabaseMigrator($db, ':memory:', sys_get_temp_dir());
  $transaction = new ReflectionMethod($migrator, 'transaction');
  $transaction->setAccessible(true);

  try {
    $transaction->invoke($migrator, static function () use ($db): void {
      $db->exec('CREATE TABLE should_rollback (id INTEGER)');
      throw new RuntimeException('expected failure');
    });
  } catch (RuntimeException $e) {
    $exists = (int)$db->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'should_rollback'")->fetchColumn();
    return $e->getMessage() === 'expected failure' && $exists === 0 && !$db->inTransaction();
  }
  return false;
});

smoke_test('administrator pagination keeps replies with their parent thread', static function (): bool {
  $db = new PDO('sqlite::memory:');
  (new DatabaseMigrator($db, ':memory:', sys_get_temp_dir()))->migrate();
  $repository = new BoardRepository($db);
  $insert = static function (int $thread, ?int $parent, int $tree, string $subject) use ($repository): int {
    return $repository->insertPost([
      'thread' => $thread, 'parent' => $parent, 'tree' => $tree,
      'sub' => $subject, 'com' => '本文', 'a_name' => '名前',
      'pwd' => password_hash('pass', PASSWORD_DEFAULT), 'picfile' => '',
      'invz' => 0, 'age' => $tree,
    ]);
  };
  $old = $insert(1, null, 100, '古い親');
  $middle = $insert(1, null, 200, '中間の親');
  $new = $insert(1, null, 300, '新しい親');
  $reply_one = $insert(0, $middle, 201, '中間のレス1');
  $reply_two = $insert(0, $middle, 202, '中間のレス2');
  $db->exec("UPDATE board_log SET picfile='reply.png', nsfw=1, invz=1, admins=1 WHERE tid={$reply_two}");

  $page = $repository->listAdminThreads(1, 1);
  $replies = $repository->listAdminReplies(array_column($page, 'tid'));
  $page_ids = array_map(static fn(array $row): int => (int)$row['tid'], $page);
  $reply_filter = AdminPostFilter::normalize(['q' => 'レス1', 'type' => 'reply']);
  $filtered_page = $repository->listAdminThreads(0, 10, $reply_filter);
  $filter_valid = $repository->countAdminPosts($reply_filter) === 1
    && $repository->countAdminThreads($reply_filter) === 1
    && count($filtered_page) === 1 && (int)$filtered_page[0]['tid'] === $middle
    && !AdminPostFilter::matches($filtered_page[0], $reply_filter)
    && AdminPostFilter::matches($repository->findPost($reply_one), $reply_filter)
    && str_contains(AdminPostFilter::query($reply_filter), 'q=%E3%83%AC%E3%82%B91');
  try {
    AdminPostFilter::normalize(['date_from' => '2026-02-30']);
    return false;
  } catch (InvalidArgumentException $e) {
  }

  $stats = $repository->adminDashboardStats();
  return $filter_valid && $repository->countAdminPosts() === 5
    && $repository->countAdminThreads() === 3
    && $stats['total'] === 5 && $stats['threads'] === 3 && $stats['replies'] === 2
    && $stats['images'] === 1 && $stats['nsfw'] === 1 && $stats['hidden'] === 1
    && $stats['administrators'] === 1 && $stats['today'] === 5
    && $stats['last_7_days'] === 5 && $stats['last_30_days'] === 5
    && count($page) === 1 && (int)$page[0]['tid'] === $middle
    && array_map(static fn(array $row): int => (int)$row['tid'], $replies) === [$reply_one, $reply_two]
    && !in_array($old, $page_ids, true)
    && !in_array($new, $page_ids, true);
});

smoke_test('UUIDv7 format and uniqueness', static function (): bool {
  $first = generate_uuid();
  $second = generate_uuid();
  $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
  return $first !== $second && preg_match($pattern, $first) === 1 && preg_match($pattern, $second) === 1;
});

smoke_test('escaping and NG-word helpers', static function (): bool {
  return h('<script>') === '&lt;script&gt;'
    && t("a\tb") === 'ab'
    && s('<b>text</b>') === 'text'
    && is_ngword(['spam'], ['safe', 'contains spam'])
    && !is_ngword(['spam'], 'safe');
});

smoke_test('post validation is independent from HTTP rendering', static function (): bool {
  $input = [
    'sub' => '題名', 'name' => '名前', 'mail' => '', 'url' => '', 'com' => '本文です',
    'pwd' => 'secret', 'resto' => '',
  ];
  $rules = [
    'en' => false, 'request_method' => 'POST', 'host' => 'client.example.com',
    'blocked_hosts' => [], 'require_name' => true, 'require_comment' => true,
    'require_subject' => true, 'max_comment' => 100, 'max_name' => 100,
    'max_email' => 100, 'max_subject' => 100, 'max_url' => 100,
    'japanese_filter' => true, 'deny_comment_urls' => true, 'admin_pass' => 'admin',
    'bad_strings' => ['禁止語'], 'bad_names' => ['使用禁止名'],
    'bad_strings_a' => ['激安'], 'bad_strings_b' => ['ブランド'],
  ];
  PostValidator::validate($input, $rules);

  $invalid_cases = [
    [array_merge($input, ['com' => '']), $rules, '本文は必須です。'],
    [array_merge($input, ['com' => 'https://example.com']), array_merge($rules, ['japanese_filter' => false]), 'コメントにはURLを含めることはできません。'],
    [array_merge($input, ['name' => '使用禁止名']), $rules, '無効な名前が使用されています。'],
    [$input, array_merge($rules, ['host' => 'blocked.example.com', 'blocked_hosts' => ['blocked\\.example\\.com']]), 'あなたのホストは拒絶されています。'],
  ];
  foreach ($invalid_cases as [$invalid_input, $invalid_rules, $expected]) {
    try {
      PostValidator::validate($invalid_input, $invalid_rules);
      return false;
    } catch (PostValidationException $e) {
      if ($e->getMessage() !== $expected) return false;
    }
  }
  return true;
});

smoke_test('ctype input sources are resolved in priority order', static function (): bool {
  return PostInput::resolveCtype([
      'direct' => 'img', 'usercode' => 'ctype=pch', 'http_usercode' => 'ctype=spch',
    ]) === 'img'
    && PostInput::resolveCtype(['usercode' => 'foo=bar&ctype=pch']) === 'pch'
    && PostInput::resolveCtype(['send_header' => 'usercode=' . rawurlencode('foo=bar&ctype=spch')]) === 'spch'
    && PostInput::resolveCtype(['http_usercode' => 'ctype=img']) === 'img'
    && PostInput::resolveCtype(['session_usercode' => 'ctype=pch']) === 'pch'
    && PostInput::resolveCtype(['direct' => '../invalid', 'usercode' => 'ctype=invalid']) === 'new';
});

smoke_test('post service centralizes edit and delete authorization', static function (): bool {
  $image_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_post_service_' . bin2hex(random_bytes(8));
  if (!mkdir($image_dir, 0700)) return false;
  try {
    $db = new PDO('sqlite::memory:');
    (new DatabaseMigrator($db, ':memory:', sys_get_temp_dir()))->migrate();
    $repository = new BoardRepository($db);
    $insert = static function (string $subject, string $password, string $image = '') use ($repository): int {
      return $repository->insertPost([
        'thread' => 1, 'sub' => $subject, 'com' => '本文', 'a_name' => '名前',
        'pwd' => password_hash($password, PASSWORD_DEFAULT), 'picfile' => $image,
        'invz' => 0, 'age' => 0, 'tree' => time(),
      ]);
    };
    $edit_id = $insert('編集前', 'owner-pass');
    $hide_id = $insert('非表示対象', 'another-pass');
    $delete_id = $insert('削除対象', 'delete-pass', 'owner.png');
    file_put_contents($image_dir . DIRECTORY_SEPARATOR . 'owner.png', 'image');
    $service = new PostService($repository, 'admin-pass', $image_dir);

    try {
      $service->edit($edit_id, 'wrong-pass', []);
      return false;
    } catch (PostAuthorizationException $e) {
    }
    $service->edit($edit_id, 'owner-pass', [
      'name' => '編集者', 'mail' => '', 'sub' => '編集後', 'com' => '編集本文',
      'url' => '', 'host' => 'localhost', 'sodane' => 0,
    ]);
    if (($repository->findPost($edit_id)['sub'] ?? '') !== '編集後') return false;

    if ($service->delete($hide_id, 'admin-pass', false) !== 'hidden'
      || (int)($repository->findPost($hide_id)['invz'] ?? 0) !== 1) return false;
    if ($service->setVisibilityManyAsAdmin([$hide_id], false) !== 1
      || (int)($repository->findPost($hide_id)['invz'] ?? 1) !== 0
      || $service->setVisibilityManyAsAdmin([$hide_id, $hide_id], true) !== 1
      || (int)($repository->findPost($hide_id)['invz'] ?? 0) !== 1) return false;
    if ($service->delete($delete_id, 'delete-pass', false) !== 'deleted'
      || $repository->findPost($delete_id) !== false
      || is_file($image_dir . DIRECTORY_SEPARATOR . 'owner.png')) return false;

    $batch_parent = $insert('一括削除親', 'parent-pass', 'batch-parent.png');
    $batch_reply = $repository->insertPost([
      'thread' => 0, 'parent' => $batch_parent, 'sub' => '一括削除レス', 'com' => '本文',
      'a_name' => '名前', 'pwd' => password_hash('reply-pass', PASSWORD_DEFAULT),
      'picfile' => 'batch-reply.png', 'invz' => 0, 'age' => 0, 'tree' => time(),
    ]);
    $batch_other = $insert('一括削除別記事', 'other-pass', 'batch-other.png');
    foreach (['batch-parent.png', 'batch-reply.png', 'batch-other.png'] as $image) {
      file_put_contents($image_dir . DIRECTORY_SEPARATOR . $image, 'image');
    }
    if ($service->deleteManyAsAdmin([$batch_parent, $batch_reply, $batch_other, $batch_other, 'invalid']) !== 3
      || $repository->findPost($batch_parent) !== false
      || $repository->findPost($batch_reply) !== false
      || $repository->findPost($batch_other) !== false) return false;
    foreach (['batch-parent.png', 'batch-reply.png', 'batch-other.png'] as $image) {
      if (is_file($image_dir . DIRECTORY_SEPARATOR . $image)) return false;
    }

    $new_input = [
      'name' => '投稿者', 'sub' => '新規題名', 'com' => '新規本文', 'mail' => '', 'url' => '',
      'picfile' => null, 'pwd' => 'new-pass', 'sodane' => 0, 'invz' => 0,
      'resto' => '', 'modid' => '',
    ];
    $settings = [
      'default_name' => '名無し', 'default_comment' => '本文なし', 'default_subject' => '無題',
      'admin_name' => '管理者', 'admin_cap' => '(ではない)',
    ];
    $prepared = $service->prepareNewPost($new_input, 'new.example.com', $settings);
    $new_id = $service->createPreparedPost($prepared, [
      'pchfile' => '', 'img_w' => 0, 'img_h' => 0, 'psec' => 0, 'utime' => '',
      'tool' => '', 'nsfw' => false, 'ctype' => null, 'thumbnail' => '',
    ]);
    if (($repository->findPost($new_id)['sub'] ?? '') !== '新規題名') return false;
    try {
      $service->prepareNewPost($new_input, 'new.example.com', $settings);
      return false;
    } catch (DuplicatePostException $e) {
    }
    $reply_input = array_merge($new_input, [
      'sub' => '返信題名', 'com' => '返信本文', 'resto' => (string)$new_id,
    ]);
    $reply = $service->prepareNewPost($reply_input, 'reply.example.com', $settings);
    $reply_id = $service->createPreparedPost($reply, [
      'pchfile' => '', 'img_w' => 0, 'img_h' => 0, 'psec' => 0, 'utime' => '',
      'tool' => '', 'nsfw' => false, 'ctype' => null, 'thumbnail' => '',
    ]);
    $reply_row = $repository->findPost($reply_id);
    $parent_row = $repository->findPost($new_id);
    if ((int)($reply_row['thread'] ?? 1) !== 0 || (int)($reply_row['parent'] ?? 0) !== $new_id
      || (int)($parent_row['age'] ?? 0) !== 1) return false;
    return true;
  } finally {
    foreach (glob($image_dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_dir($image_dir)) rmdir($image_dir);
  }
});

smoke_test('share service builds validated destination URLs', static function (): bool {
  $servers = ShareService::servers();
  if (end($servers) !== ['直接入力', 'direct']) return false;
  if (ShareService::buildShareUrl('https://x.com', '', '題名', 'https://example.com/post')
    !== 'https://twitter.com/intent/tweet?text=' . rawurlencode('題名 https://example.com/post')) return false;
  if (ShareService::buildShareUrl('direct', 'https://social.example/', 'title', 'https://example.com')
    !== 'https://social.example/share?text=' . rawurlencode('title https://example.com')) return false;
  foreach (['javascript:alert(1)', 'https://user:pass@example.com', 'https://example.com/?redirect=evil'] as $invalid) {
    try {
      ShareService::buildShareUrl('direct', $invalid, 'title', 'url');
      return false;
    } catch (InvalidArgumentException $e) {
    }
  }
  return true;
});

smoke_test('image MIME mapping', static function (): bool {
  return get_image_type('image/jpeg') === '.jpg'
    && get_image_type('image/png') === '.png'
    && get_image_type('image/webp') === '.webp'
    && get_image_type('image/avif') === '.avif';
});

smoke_test('image directory usage is counted and formatted', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_usage_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) return false;
  try {
    file_put_contents($directory . DIRECTORY_SEPARATOR . 'one.png', str_repeat('a', 1024));
    file_put_contents($directory . DIRECTORY_SEPARATOR . 'two.pch', str_repeat('b', 512));
    mkdir($directory . DIRECTORY_SEPARATOR . 'nested', 0700);
    file_put_contents($directory . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'ignored.png', 'ignored');
    $usage = ImageService::directoryUsage($directory);
    return $usage === ['files' => 2, 'bytes' => 1536]
      && ImageService::formatBytes($usage['bytes']) === '1.5 KiB'
      && ImageService::formatBytes(0) === '0 B';
  } finally {
    safe_unlink($directory . DIRECTORY_SEPARATOR . 'one.png');
    safe_unlink($directory . DIRECTORY_SEPARATOR . 'two.pch');
    safe_unlink($directory . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'ignored.png');
    if (is_dir($directory . DIRECTORY_SEPARATOR . 'nested')) rmdir($directory . DIRECTORY_SEPARATOR . 'nested');
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('animation filenames reject path traversal', static function (): bool {
  return ImageService::isSafeAnimationFilename('1712345678901234.pch')
    && ImageService::isSafeAnimationFilename('legacy-name_01.spch')
    && ImageService::isSafeAnimationFilename('drawing.tgkr')
    && !ImageService::isSafeAnimationFilename('../secret.pch')
    && !ImageService::isSafeAnimationFilename('subdir/secret.pch')
    && !ImageService::isSafeAnimationFilename('drawing.php')
    && !ImageService::isSafeAnimationFilename('drawing.chi')
    && !ImageService::isSafeAnimationFilename('.pch');
});

smoke_test('posted image filenames reject invalid continuation targets', static function (): bool {
  return ImageService::isSafePostedImageFilename('1784.png')
    && ImageService::isSafePostedImageFilename('drawing-name.webp')
    && !ImageService::isSafePostedImageFilename('1784')
    && !ImageService::isSafePostedImageFilename('../1784.png')
    && !ImageService::isSafePostedImageFilename('drawing.php');
});

smoke_test('temporary images are parsed, found, and cleaned up', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_temp_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) return false;
  $now = 1700000000;
  try {
    file_put_contents($directory . DIRECTORY_SEPARATOR . '100.png', 'image');
    file_put_contents($directory . DIRECTORY_SEPARATOR . '100.dat', "127.0.0.1\thost\tagent\t.png\tuser-a\treplace-a\t100\t160\t0\tneo");
    file_put_contents($directory . DIRECTORY_SEPARATOR . '200.png', 'image');
    file_put_contents($directory . DIRECTORY_SEPARATOR . '200.dat', "127.0.0.2\thost\tagent\t.png\tuser-b\treplace-b\t200\t230\t0\tklecks");
    file_put_contents($directory . DIRECTORY_SEPARATOR . 'orphan.dat', "127.0.0.3\thost\tagent\t.png\tuser-c\treplace-c\t0\t0\t0\tneo");

    $images = ImageService::listTemporaryImages($directory);
    $found = ImageService::findTemporaryImageByReplacementCode($directory, 'replace-b');
    if (count($images) !== 2 || $images[0]['filename'] !== '100.png'
      || $images[0]['paint_seconds'] !== 60 || $images[0]['tool'] !== 'neo'
      || $found === null || $found['base_name'] !== '200'
      || ImageService::findTemporaryImageByReplacementCode($directory, 'missing') !== null) {
      return false;
    }

    file_put_contents($directory . DIRECTORY_SEPARATOR . 'expired.tmp', 'old');
    file_put_contents($directory . DIRECTORY_SEPARATOR . 'pchup-test-tmp.pch', 'old upload');
    file_put_contents($directory . DIRECTORY_SEPARATOR . 'recent.tmp', 'recent');
    touch($directory . DIRECTORY_SEPARATOR . 'expired.tmp', $now - 86401);
    touch($directory . DIRECTORY_SEPARATOR . 'pchup-test-tmp.pch', $now - 301);
    touch($directory . DIRECTORY_SEPARATOR . 'recent.tmp', $now - 60);

    return ImageService::cleanupTemporaryFiles($directory, 1, $now) === 2
      && !is_file($directory . DIRECTORY_SEPARATOR . 'expired.tmp')
      && !is_file($directory . DIRECTORY_SEPARATOR . 'pchup-test-tmp.pch')
      && is_file($directory . DIRECTORY_SEPARATOR . 'recent.tmp');
  } finally {
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('animation playback data is built by the image service', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_playback_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) return false;
  try {
    $image = imagecreatetruecolor(120, 80);
    imagepng($image, $directory . DIRECTORY_SEPARATOR . 'drawing.png');
    file_put_contents($directory . DIRECTORY_SEPARATOR . 'drawing.pch', 'NEO animation');

    $data = ImageService::animationPlaybackData($directory, 'drawing.pch', 12);
    return $data['tool'] === 'neo' && $data['template_type'] === 'standard'
      && $data['picw'] === 120 && $data['pich'] === 80
      && $data['w'] === 300 && $data['h'] === 326
      && $data['pchfile'] === './drawing.pch'
      && $data['datasize'] === strlen('NEO animation') && $data['speed'] === 12;
  } finally {
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('external URL security boundaries', static function (): bool {
  return ExternalImageService::resolvePublicIp('127.0.0.1') === false
    && ExternalImageService::resolvePublicIp('192.168.1.1') === false
    && ExternalImageService::resolvePublicIp('169.254.169.254') === false
    && ExternalImageService::resolvePublicIp('::1') === false
    && ExternalImageService::resolveRedirectUrl('https://example.com/a/b.png', '../c.png') === 'https://example.com/c.png'
    && ExternalImageService::resolveRedirectUrl('https://example.com/a/b.png', "https://example.com/x\nInjected: yes") === false;
});

smoke_test('cached external image thumbnail link', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_external_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) return false;
  $url = 'https://example.com/picture.png';
  $thumbnail = $directory . DIRECTORY_SEPARATOR . md5($url) . '_thumb.jpg';
  try {
    file_put_contents($thumbnail, 'cached thumbnail');
    $service = new ExternalImageService($directory, 'thumbnail/', 200, 0600, 0700);
    $html = $service->addThumbnailLinks('image: ' . $url);
    return str_contains($html, 'href="' . $url . '"')
      && str_contains($html, 'src="thumbnail/' . basename($thumbnail) . '"');
  } finally {
    if (is_file($thumbnail)) unlink($thumbnail);
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('GD thumbnail generation', static function (): bool {
  $input = tempnam(sys_get_temp_dir(), 'noreita_smoke_');
  if ($input === false) {
    throw new RuntimeException('could not create temporary file');
  }

  $output = null;
  try {
    $image = imagecreatetruecolor(4, 4);
    if ($image === false) {
      throw new RuntimeException('could not create source image');
    }
    imagefill($image, 0, 0, imagecolorallocate($image, 20, 120, 220));
    if (!imagepng($image, $input)) {
      throw new RuntimeException('could not save source image');
    }

    $thumbnail = new Thumbnail($input, sys_get_temp_dir(), 20);
    $created = $thumbnail->createThumbnail();
    $output = $thumbnail->getOutputPath();
    return $created && $output !== null && is_file($output) && filesize($output) > 0;
  } finally {
    if (is_file($input)) unlink($input);
    if ($output !== null && is_file($output)) unlink($output);
  }
});

smoke_test('related image files are deleted together', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_images_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) return false;
  try {
    foreach (['png', 'webp', 'pch', 'dat'] as $extension) {
      file_put_contents($directory . DIRECTORY_SEPARATOR . 'post.' . $extension, 'test');
    }
    ImageService::deleteRelatedFiles($directory, 'post.png');
    return count(glob($directory . DIRECTORY_SEPARATOR . 'post.*') ?: []) === 0;
  } finally {
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('posted image replacement can roll back or complete atomically', static function (): bool {
  $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_replace_' . bin2hex(random_bytes(8));
  $temp = $root . DIRECTORY_SEPARATOR . 'tmp';
  $images = $root . DIRECTORY_SEPARATOR . 'img';
  mkdir($temp, 0700, true);
  mkdir($images, 0700, true);
  $write_source = static function () use ($temp): void {
    $image = imagecreatetruecolor(4, 3);
    imagefill($image, 0, 0, imagecolorallocate($image, 20, 120, 220));
    imagepng($image, $temp . DIRECTORY_SEPARATOR . 'new.png');
    file_put_contents($temp . DIRECTORY_SEPARATOR . 'new.dat', 'metadata');
    file_put_contents($temp . DIRECTORY_SEPARATOR . 'new.pch', 'new animation');
  };

  try {
    $old_image = imagecreatetruecolor(4, 3);
    imagefill($old_image, 0, 0, imagecolorallocate($old_image, 220, 20, 20));
    imagepng($old_image, $images . DIRECTORY_SEPARATOR . 'old.png');
    file_put_contents($images . DIRECTORY_SEPARATOR . 'old.pch', 'old animation');
    $write_source();

    $replacement = ImageService::replacePostedFiles(
      $temp, $images, 'new', '.png', 100, 'old.png', 'old.pch', 0600
    );
    if (!is_file($images . DIRECTORY_SEPARATOR . 'old.png')
      || !is_file($images . DIRECTORY_SEPARATOR . 'old.pch')
      || !is_file($images . DIRECTORY_SEPARATOR . 'new.png')
      || !is_file($temp . DIRECTORY_SEPARATOR . 'new.png')) return false;

    ImageService::rollbackPostedReplacement($replacement);
    if (!is_file($images . DIRECTORY_SEPARATOR . 'old.png')
      || !is_file($images . DIRECTORY_SEPARATOR . 'old.pch')
      || is_file($images . DIRECTORY_SEPARATOR . 'new.png')
      || is_file($images . DIRECTORY_SEPARATOR . 'new.pch')
      || !is_file($temp . DIRECTORY_SEPARATOR . 'new.png')) return false;

    $replacement = ImageService::replacePostedFiles(
      $temp, $images, 'new', '.png', 101, 'old.png', 'old.pch', 0600
    );
    ImageService::completePostedReplacement($replacement);
    return !is_file($images . DIRECTORY_SEPARATOR . 'old.png')
      && !is_file($images . DIRECTORY_SEPARATOR . 'old.pch')
      && is_file($images . DIRECTORY_SEPARATOR . 'new.png')
      && is_file($images . DIRECTORY_SEPARATOR . 'new.pch')
      && !is_file($temp . DIRECTORY_SEPARATOR . 'new.png')
      && !is_file($temp . DIRECTORY_SEPARATOR . 'new.pch')
      && !is_file($temp . DIRECTORY_SEPARATOR . 'new.dat');
  } finally {
    foreach ([$temp, $images] as $directory) {
      foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        if (is_file($file)) unlink($file);
      }
      if (is_dir($directory)) rmdir($directory);
    }
    if (is_dir($root)) rmdir($root);
  }
});

smoke_test('new post image and animation are finalized', static function (): bool {
  $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_finalize_' . bin2hex(random_bytes(8));
  $temp = $root . DIRECTORY_SEPARATOR . 'tmp';
  $images = $root . DIRECTORY_SEPARATOR . 'img';
  mkdir($temp, 0700, true);
  mkdir($images, 0700, true);
  try {
    $source = imagecreatetruecolor(4, 3);
    imagefill($source, 0, 0, imagecolorallocate($source, 20, 120, 220));
    imagepng($source, $temp . DIRECTORY_SEPARATOR . 'post.png');
    file_put_contents($temp . DIRECTORY_SEPARATOR . 'post.dat', "ip\thost\tagent\t.png\tcode\trep\t100\t160\t\tneo");
    file_put_contents($temp . DIRECTORY_SEPARATOR . 'post.pch', 'NEO');

    $result = ImageService::finalizeNewPost($temp, $images, 'post.png', 'new', true, 100, false, 0600);
    return $result['img_w'] === 4 && $result['img_h'] === 3
      && $result['psec'] === 60 && $result['tool'] === 'PaintBBS NEO'
      && $result['pchfile'] === 'post.pch'
      && is_file($images . DIRECTORY_SEPARATOR . 'post.png')
      && is_file($images . DIRECTORY_SEPARATOR . 'post.pch')
      && !is_file($temp . DIRECTORY_SEPARATOR . 'post.dat');
  } finally {
    foreach ([$temp, $images] as $directory) {
      foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) if (is_file($file)) unlink($file);
      if (is_dir($directory)) rmdir($directory);
    }
    if (is_dir($root)) rmdir($root);
  }
});

smoke_test('image consistency repair backs up data and fixes recoverable issues', static function (): bool {
  $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_consistency_' . bin2hex(random_bytes(8));
  $images = $root . DIRECTORY_SEPARATOR . 'img';
  mkdir($images, 0700, true);
  $database = $root . DIRECTORY_SEPARATOR . 'reita.db';
  try {
    $db = new PDO('sqlite:' . $database);
    $db->exec('CREATE TABLE board_log (
      tid INTEGER PRIMARY KEY, picfile TEXT, pchfile TEXT, thumbnail TEXT,
      img_w INTEGER, img_h INTEGER, nsfw INTEGER
    )');
    $image = imagecreatetruecolor(4, 3);
    imagepng($image, $images . DIRECTORY_SEPARATOR . 'valid.png');
    imagepng($image, $images . DIRECTORY_SEPARATOR . 'orphan.png');
    $insert = $db->prepare('INSERT INTO board_log VALUES (?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([1, 'valid.png', 'valid.pch', '', 40, 30, 1]);
    $insert->execute([2, 'missing.png', '', '', 8, 6, 0]);
    unset($db);

    $report = checker_scan($database, $images);
    $types = array_column($report['issues'], 'type');
    $repair = checker_repair([
      'root' => $root,
      'database' => $database,
      'image_dir' => $images,
      'thumbnail_file' => dirname(__DIR__) . '/noreita/thumbnail.inc.php',
      'thumbnail_width' => 20,
      'file_permission' => 0600,
    ], $report);
    $after = checker_scan($database, $images);
    $repaired = (new PDO('sqlite:' . $database))->query(
      'SELECT pchfile, thumbnail, img_w, img_h FROM board_log WHERE tid = 1'
    )->fetch(PDO::FETCH_ASSOC);
    $action_types = array_column($repair['actions'], 'type');
    $ok = $report['summary']['posts_checked'] === 2
      && $report['summary']['errors'] === 1
      && $report['summary']['warnings'] === 4
      && in_array('missing_image', $types, true)
      && in_array('orphan_file', $types, true)
      && $repair['failed'] === 0 && is_file($repair['backup'])
      && in_array('update_dimensions', $action_types, true)
      && in_array('clear_missing_pch', $action_types, true)
      && in_array('regenerate_thumbnail', $action_types, true)
      && in_array('quarantine_file', $action_types, true)
      && is_array($repaired) && $repaired['pchfile'] === ''
      && (int)$repaired['img_w'] === 4 && (int)$repaired['img_h'] === 3
      && is_file($images . DIRECTORY_SEPARATOR . $repaired['thumbnail'])
      && !is_file($images . DIRECTORY_SEPARATOR . 'orphan.png')
      && $after['summary']['errors'] === 1 && $after['summary']['warnings'] === 0;
    if (!$ok) {
      throw new RuntimeException(json_encode([
        'before' => $report, 'repair' => $repair, 'after' => $after, 'row' => $repaired,
      ], JSON_UNESCAPED_SLASHES));
    }
    return true;
  } finally {
    foreach (glob($images . DIRECTORY_SEPARATOR . '*') ?: [] as $file) if (is_file($file)) unlink($file);
    if (is_dir($images)) rmdir($images);
    if (is_file($database)) unlink($database);
    foreach (['backup', 'orphan'] as $subdirectory) {
      foreach (glob($root . DIRECTORY_SEPARATOR . $subdirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $entry) {
        if (is_file($entry)) unlink($entry);
        if (is_dir($entry)) {
          foreach (glob($entry . DIRECTORY_SEPARATOR . '*') ?: [] as $file) if (is_file($file)) unlink($file);
          rmdir($entry);
        }
      }
      if (is_dir($root . DIRECTORY_SEPARATOR . $subdirectory)) rmdir($root . DIRECTORY_SEPARATOR . $subdirectory);
    }
    if (is_dir($root)) rmdir($root);
  }
});

echo "\nSmoke tests: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
