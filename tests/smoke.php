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

require_once dirname(__DIR__) . '/noreita/functions.php';
require_once dirname(__DIR__) . '/noreita/thumbnail.inc.php';
require_once dirname(__DIR__) . '/noreita/database.inc.php';
require_once dirname(__DIR__) . '/noreita/image.inc.php';

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

smoke_test('image MIME mapping', static function (): bool {
  return get_image_type('image/jpeg') === '.jpg'
    && get_image_type('image/png') === '.png'
    && get_image_type('image/webp') === '.webp'
    && get_image_type('image/avif') === '.avif';
});

smoke_test('external URL security boundaries', static function (): bool {
  return resolve_public_ip('127.0.0.1') === false
    && resolve_public_ip('192.168.1.1') === false
    && resolve_public_ip('169.254.169.254') === false
    && resolve_public_ip('::1') === false
    && resolve_redirect_url('https://example.com/a/b.png', '../c.png') === 'https://example.com/c.png'
    && resolve_redirect_url('https://example.com/a/b.png', "https://example.com/x\nInjected: yes") === false;
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

echo "\nSmoke tests: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
