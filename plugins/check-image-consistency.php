<?php
declare(strict_types=1);

// noReita image/database consistency checker and conservative repair tool

const CHECK_IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif'];
const CHECK_RELATED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'pch', 'spch', 'dat', 'chi', 'tgkr'];

function checker_usage(string $script): string {
  return <<<TEXT
Usage: php {$script} [--root=PATH] [--json] [--repair]

Checks noReita's database and image directory. The default mode is read-only.

  --root=PATH  Directory containing config.php and the SQLite database
  --json       Print a machine-readable JSON report
  --repair     Back up the database and repair safely recoverable problems
  --help       Show this help
TEXT;
}

/** @return array{root:string,json:bool,repair:bool,help:bool} */
function checker_options(array $arguments): array {
  $root = null;
  $json = false;
  $repair = false;
  $help = false;
  foreach (array_slice($arguments, 1) as $argument) {
    if ($argument === '--json') {
      $json = true;
    } elseif ($argument === '--repair') {
      $repair = true;
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
  return ['root' => $resolved, 'json' => $json, 'repair' => $repair, 'help' => $help];
}

/** @return array{root:string,database:string,image_dir:string,thumbnail_file:string,thumbnail_width:int,file_permission:int} */
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
  $thumbnail_file = $root . DIRECTORY_SEPARATOR . 'thumbnail.inc.php';
  return [
    'root' => $root,
    'database' => $database,
    'image_dir' => rtrim($image_dir, '/\\'),
    'thumbnail_file' => $thumbnail_file,
    'thumbnail_width' => defined('PDEF_W') ? max(1, (int)constant('PDEF_W')) : 400,
    'file_permission' => defined('PERMISSION_FOR_DEST') ? (int)constant('PERMISSION_FOR_DEST') : 0600,
  ];
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

function checker_backup_database(PDO $db, string $root): string {
  $backup_dir = $root . DIRECTORY_SEPARATOR . 'backup';
  if (!is_dir($backup_dir) && !mkdir($backup_dir, 0700, true)) {
    throw new RuntimeException("Could not create backup directory: {$backup_dir}");
  }
  $backup = $backup_dir . DIRECTORY_SEPARATOR . 'image-consistency-'
    . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.db';
  $db->exec('VACUUM INTO ' . $db->quote($backup));
  if (!is_file($backup) || filesize($backup) === 0) {
    throw new RuntimeException('Database backup could not be verified.');
  }
  return $backup;
}

/** @return array{name:string,created:bool} */
function checker_generate_thumbnail(
  string $thumbnail_file,
  string $image_dir,
  string $image_name,
  int $width,
  bool $nsfw,
  int $permission
): array {
  if (!is_file($thumbnail_file)) {
    throw new RuntimeException("thumbnail.inc.php was not found: {$thumbnail_file}");
  }
  require_once $thumbnail_file;
  if (!class_exists('Thumbnail')) throw new RuntimeException('Thumbnail class is unavailable.');
  $width = max(10, $width);

  $source = $image_dir . DIRECTORY_SEPARATOR . $image_name;
  $temporary_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_repair_' . bin2hex(random_bytes(8));
  if (!mkdir($temporary_dir, 0700)) throw new RuntimeException('Could not prepare a thumbnail work directory.');
  try {
    $thumbnail = new Thumbnail($source, $temporary_dir, $width, $nsfw);
    if (!$thumbnail->createThumbnail()) throw new RuntimeException("Could not regenerate thumbnail for {$image_name}.");
    $temporary_path = $thumbnail->getOutputPath();
    if (!is_string($temporary_path) || !is_file($temporary_path)) {
      throw new RuntimeException("Generated thumbnail for {$image_name} was not found.");
    }
    $extension = strtolower(pathinfo($temporary_path, PATHINFO_EXTENSION));
    $hash = substr(hash_file('sha256', $source) ?: hash('sha256', $image_name), 0, 16);
    $state = $nsfw ? 'nsfw' : 'safe';
    $name = pathinfo($image_name, PATHINFO_FILENAME) . "_thumb_{$state}_{$hash}.{$extension}";
    $destination = $image_dir . DIRECTORY_SEPARATOR . $name;
    $created = !is_file($destination);
    if (!$created) {
      unlink($temporary_path);
    } elseif (!rename($temporary_path, $destination)) {
      throw new RuntimeException("Could not publish regenerated thumbnail: {$name}");
    }
    chmod($destination, $permission);
    return ['name' => $name, 'created' => $created];
  } finally {
    foreach (glob($temporary_dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) if (is_file($file)) unlink($file);
    if (is_dir($temporary_dir)) rmdir($temporary_dir);
  }
}

/** @return array{backup:string,actions:array<int,array<string,mixed>>,failed:int} */
function checker_repair(array $configuration, array $report): array {
  $db = new PDO('sqlite:' . $configuration['database'], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
  $backup = checker_backup_database($db, $configuration['root']);
  $actions = [];
  $failed = 0;
  $created_thumbnails = [];
  $old_thumbnails = [];

  $by_post = [];
  $orphan_files = [];
  foreach ($report['issues'] as $issue) {
    if ($issue['post_id'] !== null) $by_post[(int)$issue['post_id']][] = $issue;
    if ($issue['type'] === 'orphan_file' && checker_safe_name((string)$issue['file'])) {
      $orphan_files[] = (string)$issue['file'];
    }
  }

  try {
    $db->beginTransaction();
    foreach ($by_post as $post_id => $issues) {
      $statement = $db->prepare('SELECT tid, picfile, pchfile, thumbnail, img_w, img_h, nsfw FROM board_log WHERE tid = ?');
      $statement->execute([$post_id]);
      $post = $statement->fetch(PDO::FETCH_ASSOC);
      if (!$post) continue;
      $types = array_column($issues, 'type');
      $image_name = (string)$post['picfile'];
      $image_path = $configuration['image_dir'] . DIRECTORY_SEPARATOR . $image_name;
      $valid_image = checker_safe_name($image_name) && is_file($image_path) && is_readable($image_path);
      $size = $valid_image ? @getimagesize($image_path) : false;

      if (in_array('image_dimensions_mismatch', $types, true) && is_array($size)) {
        $update = $db->prepare('UPDATE board_log SET img_w = ?, img_h = ? WHERE tid = ?');
        $update->execute([(int)$size[0], (int)$size[1], $post_id]);
        $actions[] = ['type' => 'update_dimensions', 'post_id' => $post_id, 'file' => $image_name, 'status' => 'repaired'];
      }
      if (in_array('missing_pchfile', $types, true) && checker_safe_name((string)$post['pchfile'])) {
        $update = $db->prepare("UPDATE board_log SET pchfile = '' WHERE tid = ?");
        $update->execute([$post_id]);
        $actions[] = ['type' => 'clear_missing_pch', 'post_id' => $post_id, 'file' => $post['pchfile'], 'status' => 'repaired'];
      }

      $thumbnail_problem = array_intersect(
        ['missing_thumbnail', 'unreadable_thumbnail', 'invalid_thumbnail', 'missing_nsfw_thumbnail_reference'],
        $types
      ) !== [];
      if ($thumbnail_problem && is_array($size)) {
        try {
          $generated = checker_generate_thumbnail(
            $configuration['thumbnail_file'], $configuration['image_dir'], $image_name,
            $configuration['thumbnail_width'], (int)$post['nsfw'] === 1, $configuration['file_permission']
          );
          $new_thumbnail = $generated['name'];
          if ($generated['created']) $created_thumbnails[] = $new_thumbnail;
          $old_thumbnail = (string)$post['thumbnail'];
          if (checker_safe_name($old_thumbnail) && $old_thumbnail !== $image_name && $old_thumbnail !== $new_thumbnail) {
            $reference = $db->prepare('SELECT COUNT(*) FROM board_log WHERE thumbnail = ? AND tid != ?');
            $reference->execute([$old_thumbnail, $post_id]);
            if ((int)$reference->fetchColumn() === 0) $old_thumbnails[] = $old_thumbnail;
          }
          $update = $db->prepare('UPDATE board_log SET thumbnail = ? WHERE tid = ?');
          $update->execute([$new_thumbnail, $post_id]);
          $actions[] = ['type' => 'regenerate_thumbnail', 'post_id' => $post_id, 'file' => $new_thumbnail, 'status' => 'repaired'];
        } catch (Throwable $e) {
          $actions[] = ['type' => 'regenerate_thumbnail', 'post_id' => $post_id, 'file' => $image_name, 'status' => 'failed', 'message' => $e->getMessage()];
          $failed++;
        }
      }
    }
    $db->commit();
  } catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    foreach ($created_thumbnails as $name) {
      $path = $configuration['image_dir'] . DIRECTORY_SEPARATOR . $name;
      if (is_file($path)) unlink($path);
    }
    throw $e;
  }

  $quarantine_candidates = array_values(array_unique(array_merge($orphan_files, $old_thumbnails)));
  if ($quarantine_candidates !== []) {
    $quarantine = $configuration['root'] . DIRECTORY_SEPARATOR . 'orphan' . DIRECTORY_SEPARATOR
      . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    if (!mkdir($quarantine, 0700, true)) throw new RuntimeException("Could not create quarantine directory: {$quarantine}");
    foreach ($quarantine_candidates as $name) {
      $source = $configuration['image_dir'] . DIRECTORY_SEPARATOR . $name;
      if (!is_file($source) || is_link($source)) continue;
      $destination = $quarantine . DIRECTORY_SEPARATOR . $name;
      if (rename($source, $destination)) {
        $actions[] = ['type' => 'quarantine_file', 'post_id' => null, 'file' => $name, 'status' => 'repaired', 'destination' => $destination];
      } else {
        $actions[] = ['type' => 'quarantine_file', 'post_id' => null, 'file' => $name, 'status' => 'failed'];
        $failed++;
      }
    }
  }
  return ['backup' => $backup, 'actions' => $actions, 'failed' => $failed];
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

function checker_print_repair(array $repair): void {
  echo "\nRepair mode\nDatabase backup: {$repair['backup']}\n";
  foreach ($repair['actions'] as $action) {
    $post = $action['post_id'] === null ? '-' : (string)$action['post_id'];
    $message = isset($action['message']) ? ': ' . $action['message'] : '';
    echo sprintf("[%s] %s post=%s file=%s%s\n", strtoupper($action['status']), $action['type'], $post, $action['file'], $message);
  }
  if ($repair['actions'] === []) echo "No automatically repairable inconsistencies found.\n";
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
    $lock = null;
    $repair = null;
    if ($options['repair']) {
      $lock_path = $configuration['root'] . DIRECTORY_SEPARATOR . '.image-consistency.lock';
      $lock = fopen($lock_path, 'c');
      if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        if (is_resource($lock)) fclose($lock);
        throw new RuntimeException('Another consistency repair is already running.');
      }
    }
    try {
      $report = checker_scan($configuration['database'], $configuration['image_dir']);
      if ($options['repair']) {
        $repair = checker_repair($configuration, $report);
        $report = checker_scan($configuration['database'], $configuration['image_dir']);
      }
    } finally {
      if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
      }
    }
    if ($options['json']) {
      echo json_encode([
        'database' => $configuration['database'],
        'image_dir' => $configuration['image_dir'],
        'repair' => $repair,
      ] + $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
      if ($repair !== null) checker_print_repair($repair);
      checker_print_text($report, $configuration['database'], $configuration['image_dir']);
    }
    return $report['summary']['issues'] === 0 && ($repair['failed'] ?? 0) === 0 ? 0 : 1;
  } catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    return 2;
  }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
  exit(checker_main($argv));
}
