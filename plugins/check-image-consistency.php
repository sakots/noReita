<?php
declare(strict_types=1);

// noReita image/database consistency checker (read-only)

const CHECK_IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif'];
const CHECK_RELATED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'pch', 'spch', 'dat', 'chi', 'tgkr'];

function checker_usage(string $script): string {
  return <<<TEXT
Usage: php {$script} [--root=PATH] [--json]

Checks noReita's database and image directory without changing files.

  --root=PATH  Directory containing config.php and the SQLite database
  --json       Print a machine-readable JSON report
  --help       Show this help
TEXT;
}

/** @return array{root:string,json:bool,help:bool} */
function checker_options(array $arguments): array {
  $root = null;
  $json = false;
  $help = false;
  foreach (array_slice($arguments, 1) as $argument) {
    if ($argument === '--json') {
      $json = true;
    } elseif ($argument === '--help' || $argument === '-h') {
      $help = true;
    } elseif (str_starts_with($argument, '--root=')) {
      $root = substr($argument, strlen('--root='));
    } else {
      throw new InvalidArgumentException("Unknown option: {$argument}");
    }
  }

  if ($root === null || $root === '') {
    $repository_default = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'noreita';
    $root = is_file($repository_default . DIRECTORY_SEPARATOR . 'config.php')
      ? $repository_default
      : __DIR__;
  }
  $resolved = realpath($root);
  if ($resolved === false || !is_dir($resolved)) {
    throw new RuntimeException("Root directory does not exist: {$root}");
  }
  return ['root' => $resolved, 'json' => $json, 'help' => $help];
}

/** @return array{database:string,image_dir:string} */
function checker_configuration(string $root): array {
  $config_file = $root . DIRECTORY_SEPARATOR . 'config.php';
  if (!is_file($config_file) || !is_readable($config_file)) {
    throw new RuntimeException("Readable config.php was not found in: {$root}");
  }

  require $config_file;
  if (!defined('DB_NAME') || !defined('IMG_DIR')) {
    throw new RuntimeException('config.php must define DB_NAME and IMG_DIR.');
  }

  $database_name = (string)constant('DB_NAME');
  if ($database_name === '' || basename($database_name) !== $database_name) {
    throw new RuntimeException('DB_NAME must be a plain file name.');
  }
  $image_setting = (string)constant('IMG_DIR');
  if ($image_setting === '') throw new RuntimeException('IMG_DIR must not be empty.');

  $image_dir = checker_absolute_path($root, $image_setting);
  $database = $root . DIRECTORY_SEPARATOR . $database_name . '.db';
  if (!is_file($database) || !is_readable($database)) {
    throw new RuntimeException("Readable database was not found: {$database}");
  }
  if (!is_dir($image_dir) || !is_readable($image_dir)) {
    throw new RuntimeException("Readable image directory was not found: {$image_dir}");
  }
  return ['database' => $database, 'image_dir' => rtrim($image_dir, '/\\')];
}

function checker_absolute_path(string $root, string $path): string {
  if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) return $path;
  return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function checker_safe_name(string $name): bool {
  return $name !== '' && basename($name) === $name
    && preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/D', $name) === 1;
}

/** @param array<int,array<string,mixed>> $issues */
function checker_add_issue(array &$issues, string $type, string $severity, ?int $post_id, string $file, string $message): void {
  $issues[] = [
    'type' => $type,
    'severity' => $severity,
    'post_id' => $post_id,
    'file' => $file,
    'message' => $message,
  ];
}

/** @return array{summary:array<string,int>,issues:array<int,array<string,mixed>>} */
function checker_scan(string $database, string $image_dir): array {
  $db = new PDO('sqlite:' . $database, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  $exists = (int)$db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='board_log'")->fetchColumn();
  if ($exists !== 1) throw new RuntimeException('The board_log table was not found.');

  $posts = $db->query(
    "SELECT tid, picfile, pchfile, thumbnail, img_w, img_h, nsfw FROM board_log
      WHERE COALESCE(picfile, '') != '' OR COALESCE(pchfile, '') != '' OR COALESCE(thumbnail, '') != ''"
  )->fetchAll();
  $issues = [];
  $referenced = [];
  $referenced_bases = [];

  foreach ($posts as $post) {
    $post_id = (int)$post['tid'];
    $image_name = (string)$post['picfile'];
    $animation_name = (string)$post['pchfile'];
    $thumbnail_name = (string)$post['thumbnail'];

    if ($image_name === '') {
      checker_add_issue($issues, 'missing_image_reference', 'error', $post_id, '', 'Related data exists but picfile is empty.');
    } elseif (!checker_safe_name($image_name)) {
      checker_add_issue($issues, 'unsafe_filename', 'error', $post_id, $image_name, 'picfile is not a safe plain file name.');
    } else {
      $referenced[$image_name] = true;
      $referenced_bases[pathinfo($image_name, PATHINFO_FILENAME)] = true;
      $image_path = $image_dir . DIRECTORY_SEPARATOR . $image_name;
      if (!is_file($image_path)) {
        checker_add_issue($issues, 'missing_image', 'error', $post_id, $image_name, 'The image referenced by the database does not exist.');
      } elseif (!is_readable($image_path)) {
        checker_add_issue($issues, 'unreadable_image', 'error', $post_id, $image_name, 'The image is not readable.');
      } else {
        $size = @getimagesize($image_path);
        if ($size === false) {
          checker_add_issue($issues, 'invalid_image', 'error', $post_id, $image_name, 'The referenced file is not a readable image.');
        } elseif ((int)$post['img_w'] !== (int)$size[0] || (int)$post['img_h'] !== (int)$size[1]) {
          checker_add_issue(
            $issues, 'image_dimensions_mismatch', 'warning', $post_id, $image_name,
            "Database dimensions {$post['img_w']}x{$post['img_h']} differ from file dimensions {$size[0]}x{$size[1]}."
          );
        }
      }
    }

    foreach ([['pchfile', $animation_name], ['thumbnail', $thumbnail_name]] as [$field, $name]) {
      if ($name === '') continue;
      if (!checker_safe_name($name)) {
        checker_add_issue($issues, 'unsafe_filename', 'error', $post_id, $name, "{$field} is not a safe plain file name.");
        continue;
      }
      $referenced[$name] = true;
      $path = $image_dir . DIRECTORY_SEPARATOR . $name;
      if (!is_file($path)) {
        checker_add_issue($issues, "missing_{$field}", 'warning', $post_id, $name, "The {$field} referenced by the database does not exist.");
      } elseif (!is_readable($path)) {
        checker_add_issue($issues, "unreadable_{$field}", 'warning', $post_id, $name, "The {$field} is not readable.");
      } elseif ($field === 'thumbnail' && @getimagesize($path) === false) {
        checker_add_issue($issues, 'invalid_thumbnail', 'warning', $post_id, $name, 'The thumbnail is not a readable image.');
      }
    }

    if ((int)$post['nsfw'] === 1 && $image_name !== '' && $thumbnail_name === '') {
      checker_add_issue($issues, 'missing_nsfw_thumbnail_reference', 'warning', $post_id, $image_name, 'An NSFW image has no thumbnail reference.');
    }
  }

  $entries = scandir($image_dir);
  if ($entries === false) throw new RuntimeException("Could not scan image directory: {$image_dir}");
  $files_checked = 0;
  foreach ($entries as $name) {
    if ($name === '.' || $name === '..') continue;
    $path = $image_dir . DIRECTORY_SEPARATOR . $name;
    if (!is_file($path) || is_link($path)) continue;
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($extension, CHECK_RELATED_EXTENSIONS, true)) continue;
    $files_checked++;
    $base = pathinfo($name, PATHINFO_FILENAME);
    $base = preg_replace('/_thumb_.+\z/', '', $base) ?? $base;
    if (!isset($referenced[$name]) && !isset($referenced_bases[$base])) {
      checker_add_issue($issues, 'orphan_file', 'warning', null, $name, 'The file is not associated with a database image record.');
    }
  }

  $errors = count(array_filter($issues, static fn(array $issue): bool => $issue['severity'] === 'error'));
  $warnings = count($issues) - $errors;
  return [
    'summary' => [
      'posts_checked' => count($posts),
      'files_checked' => $files_checked,
      'errors' => $errors,
      'warnings' => $warnings,
      'issues' => count($issues),
    ],
    'issues' => $issues,
  ];
}

function checker_print_text(array $report, string $database, string $image_dir): void {
  echo "noReita image consistency check (read-only)\n";
  echo "Database: {$database}\nImage directory: {$image_dir}\n\n";
  foreach ($report['issues'] as $issue) {
    $post = $issue['post_id'] === null ? '-' : (string)$issue['post_id'];
    $file = $issue['file'] === '' ? '-' : $issue['file'];
    echo sprintf("[%s] %s post=%s file=%s: %s\n", strtoupper($issue['severity']), $issue['type'], $post, $file, $issue['message']);
  }
  if ($report['issues'] === []) echo "No inconsistencies found.\n";
  $summary = $report['summary'];
  echo "\nPosts: {$summary['posts_checked']}, files: {$summary['files_checked']}, errors: {$summary['errors']}, warnings: {$summary['warnings']}\n";
}

function checker_main(array $arguments): int {
  try {
    if (PHP_SAPI !== 'cli') throw new RuntimeException('This checker can only be run from the command line.');
    $options = checker_options($arguments);
    if ($options['help']) {
      echo checker_usage($arguments[0] ?? 'check-image-consistency.php');
      return 0;
    }
    $configuration = checker_configuration($options['root']);
    $report = checker_scan($configuration['database'], $configuration['image_dir']);
    if ($options['json']) {
      echo json_encode([
        'database' => $configuration['database'],
        'image_dir' => $configuration['image_dir'],
      ] + $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
      checker_print_text($report, $configuration['database'], $configuration['image_dir']);
    }
    return $report['summary']['issues'] === 0 ? 0 : 1;
  } catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    return 2;
  }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
  exit(checker_main($argv));
}
