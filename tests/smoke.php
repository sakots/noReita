<?php
declare(strict_types=1);

const LANG = 'Japanese';
const PHP_SELF = 'index.php';

require_once dirname(__DIR__) . '/noreita/functions.php';
require_once dirname(__DIR__) . '/noreita/thumbnail.inc.php';

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

echo "\nSmoke tests: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);

