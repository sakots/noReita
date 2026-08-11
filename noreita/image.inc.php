<?php
// image.inc.php for noReita (C) sakots 2026 MIT License

const IMAGE_INC_VER = 20260811;

final class ImageUploadException extends RuntimeException {
}

final class ImageService {
  private const RELATED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'pch', 'spch', 'dat', 'chi', 'tgkr'];
  private const TEMPORARY_RELATED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'pch', 'spch', 'dat', 'chi', 'psd', 'tgkr'];
  private const PLAYABLE_ANIMATION_EXTENSIONS = ['pch', 'spch', 'tgkr'];

  public static function isSafePostedImageFilename(string $filename): bool {
    if ($filename === '' || strlen($filename) > 255 || basename($filename) !== $filename) {
      return false;
    }
    return preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,127}\.(?:png|jpe?g|gif|webp|avif)\z/iD', $filename) === 1;
  }

  public static function isSafeAnimationFilename(string $filename): bool {
    if ($filename === '' || strlen($filename) > 255 || basename($filename) !== $filename) {
      return false;
    }
    if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,127}\.([A-Za-z0-9]+)\z/D', $filename, $matches) !== 1) {
      return false;
    }
    return in_array(strtolower($matches[1]), self::PLAYABLE_ANIMATION_EXTENSIONS, true);
  }

  /** @return array{files:int,bytes:int} */
  public static function directoryUsage(string $directory): array {
    if (!is_dir($directory) || !is_readable($directory)) return ['files' => 0, 'bytes' => 0];

    $files = 0;
    $bytes = 0;
    foreach (new DirectoryIterator($directory) as $entry) {
      if ($entry->isDot() || !$entry->isFile() || $entry->isLink()) continue;
      $size = $entry->getSize();
      $files++;
      if ($size > 0) $bytes += $size;
    }
    return ['files' => $files, 'bytes' => $bytes];
  }

  public static function formatBytes(int $bytes): string {
    $bytes = max(0, $bytes);
    $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
    $value = (float)$bytes;
    $unit = 0;
    while ($value >= 1024 && $unit < count($units) - 1) {
      $value /= 1024;
      $unit++;
    }
    return ($unit === 0 ? (string)$bytes : number_format($value, 1)) . ' ' . $units[$unit];
  }

  public static function parseTemporaryMetadata(string $metadata_file): ?array {
    if (is_link($metadata_file) || !is_file($metadata_file) || !is_readable($metadata_file)
      || strtolower(pathinfo($metadata_file, PATHINFO_EXTENSION)) !== 'dat') {
      return null;
    }
    $base_name = pathinfo($metadata_file, PATHINFO_FILENAME);
    if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,127}\z/D', $base_name) !== 1) {
      return null;
    }
    $metadata = @file_get_contents($metadata_file, false, null, 0, 1024);
    if ($metadata === false) return null;

    $fields = explode("\t", rtrim($metadata) . "\t");
    $image_extension = strtolower((string)($fields[3] ?? ''));
    if (preg_match('/\A\.(?:png|jpe?g|gif|webp|avif)\z/D', $image_extension) !== 1) {
      return null;
    }
    $start_time = (int)($fields[6] ?? 0);
    $posted_time = (int)($fields[7] ?? 0);
    $paint_seconds = max(0, $posted_time - $start_time);

    return [
      'ip' => (string)($fields[0] ?? ''), 'host' => (string)($fields[1] ?? ''),
      'user_agent' => (string)($fields[2] ?? ''), 'image_extension' => $image_extension,
      'user_code' => (string)($fields[4] ?? ''), 'replacement_code' => (string)($fields[5] ?? ''),
      'start_time' => $start_time, 'posted_time' => $posted_time,
      'resto' => (string)($fields[8] ?? ''), 'tool' => (string)($fields[9] ?? ''),
      'hide_animation' => (string)($fields[10] ?? ''), 'base_name' => $base_name,
      'filename' => $base_name . $image_extension, 'paint_seconds' => $paint_seconds,
      'paint_time' => $paint_seconds > 0 ? calcPtime($paint_seconds) : '',
    ];
  }

  public static function listTemporaryImages(string $temp_dir): array {
    $temp_dir = rtrim($temp_dir, '/\\') . DIRECTORY_SEPARATOR;
    $files = @scandir($temp_dir);
    if ($files === false) return [];

    $images = [];
    foreach ($files as $file) {
      if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'dat') continue;
      $metadata = self::parseTemporaryMetadata($temp_dir . $file);
      if ($metadata === null) continue;
      $image_path = $temp_dir . $metadata['filename'];
      if (is_link($image_path) || !is_file($image_path)) continue;
      $images[] = $metadata;
    }
    usort($images, static fn(array $a, array $b): int => strcmp($a['filename'], $b['filename']));
    return $images;
  }

  public static function findTemporaryImageByReplacementCode(string $temp_dir, string $replacement_code): ?array {
    if ($replacement_code === '') return null;
    foreach (self::listTemporaryImages($temp_dir) as $image) {
      if (hash_equals($image['replacement_code'], $replacement_code)) return $image;
    }
    return null;
  }

  /**
   * Return valid pending images with only the operational data required by the administrator.
   *
   * @return array<int,array<string,int|string|bool>>
   */
  public static function temporaryImageEntries(string $temp_dir, int $limit_days, ?int $now = null): array {
    $temp_dir = rtrim($temp_dir, '/\\') . DIRECTORY_SEPARATOR;
    $now ??= time();
    $entries = [];
    foreach (self::listTemporaryImages($temp_dir) as $image) {
      $image_path = $temp_dir . $image['filename'];
      $modified = @filemtime($image_path);
      if ($modified === false) continue;
      $related = self::temporaryRelatedFilePaths($temp_dir, (string)$image['base_name']);
      $bytes = 0;
      foreach ($related as $path) {
        $size = @filesize($path);
        if ($size !== false && $size > 0) $bytes += $size;
      }
      $age = max(0, $now - $modified);
      $entries[] = [
        'filename' => (string)$image['filename'],
        'tool' => (string)$image['tool'],
        'resto' => (string)$image['resto'],
        'paint_time' => (string)$image['paint_time'],
        'modified_at' => $modified,
        'age_seconds' => $age,
        'expired' => $age > max(0, $limit_days) * 86400,
        'related_files' => count($related),
        'related_bytes' => $bytes,
      ];
    }
    usort($entries, static fn(array $left, array $right): int => $right['modified_at'] <=> $left['modified_at']
      ?: strcmp((string)$left['filename'], (string)$right['filename']));
    return $entries;
  }

  /**
   * Delete selected pending images and their known related files. Selection is accepted only
   * when the image has matching, valid .dat metadata in the temporary directory.
   *
   * @param array<int,mixed> $filenames
   * @return array{images:int,files:int,skipped:int}
   */
  public static function deleteTemporaryImages(string $temp_dir, array $filenames): array {
    $temp_dir = rtrim($temp_dir, '/\\') . DIRECTORY_SEPARATOR;
    $selected = [];
    foreach ($filenames as $filename) {
      if (!is_string($filename) || !self::isSafePostedImageFilename($filename)) continue;
      $selected[$filename] = true;
    }
    $result = ['images' => 0, 'files' => 0, 'skipped' => 0];
    foreach (array_keys($selected) as $filename) {
      $base_name = pathinfo($filename, PATHINFO_FILENAME);
      $metadata = self::parseTemporaryMetadata($temp_dir . $base_name . '.dat');
      if ($metadata === null || !hash_equals((string)$metadata['filename'], $filename)) {
        $result['skipped']++;
        continue;
      }
      $removed_image = false;
      foreach (self::temporaryRelatedFilePaths($temp_dir, $base_name) as $path) {
        if (safe_unlink($path)) {
          $result['files']++;
          if (basename($path) === $filename) $removed_image = true;
        }
      }
      if ($removed_image) $result['images']++;
      else $result['skipped']++;
    }
    return $result;
  }

  public static function cleanupTemporaryFiles(string $temp_dir, int $limit_days, ?int $now = null): int {
    $temp_dir = rtrim($temp_dir, '/\\') . DIRECTORY_SEPARATOR;
    $files = @scandir($temp_dir);
    if ($files === false) return 0;
    $now ??= time();
    $deleted = 0;
    foreach ($files as $file) {
      $path = $temp_dir . $file;
      if (!is_file($path)) continue;
      $modified = filemtime($path);
      if ($modified === false) continue;
      $age = $now - $modified;
      $expired = $age > max(0, $limit_days) * 86400;
      $expired_upload = preg_match('/\Apchup-.*-tmp\.s?pch\z/iD', $file) === 1 && $age > 300;
      if (($expired || $expired_upload) && safe_unlink($path)) $deleted++;
    }
    return $deleted;
  }

  /** @return array<int,string> */
  private static function temporaryRelatedFilePaths(string $temp_dir, string $base_name): array {
    if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,127}\z/D', $base_name) !== 1) return [];
    $paths = [];
    foreach (self::TEMPORARY_RELATED_EXTENSIONS as $extension) {
      $path = $temp_dir . $base_name . '.' . $extension;
      if (is_file($path) && !is_link($path)) $paths[] = $path;
    }
    return $paths;
  }

  public static function animationPlaybackData(string $image_dir, string $animation_name, int $speed): array {
    if (!self::isSafeAnimationFilename($animation_name)) {
      throw new InvalidArgumentException('Invalid animation filename.');
    }
    $image_dir = rtrim($image_dir, '/\\') . DIRECTORY_SEPARATOR;
    $base_name = pathinfo($animation_name, PATHINFO_FILENAME);
    $extension = strtolower(pathinfo($animation_name, PATHINFO_EXTENSION));
    $animation_file = $image_dir . $animation_name;
    $image_file = $image_dir . $base_name . '.png';

    if (!is_file($animation_file) || !is_readable($animation_file)) {
      throw new RuntimeException('Animation file was not found.');
    }
    $image_size = @getimagesize($image_file);
    if ($image_size === false) {
      throw new RuntimeException('Animation image was not found.');
    }
    $data_size = filesize($animation_file);
    if ($data_size === false) {
      throw new RuntimeException('Failed to read animation file size.');
    }

    $picture_width = (int)$image_size[0];
    $picture_height = (int)$image_size[1];
    $tools = ['pch' => 'neo', 'spch' => 'shi', 'tgkr' => 'tegaki'];

    return [
      'tool' => $tools[$extension],
      'template_type' => $extension === 'tgkr' ? 'tegaki' : 'standard',
      'picw' => $picture_width,
      'pich' => $picture_height,
      'w' => max(300, $picture_width),
      'h' => max(326, $picture_height + 26),
      'pchfile' => './' . $animation_name,
      'datasize' => $data_size,
      'speed' => $speed,
      'path' => $image_dir,
      'a_stime' => time(),
    ];
  }

  public static function validateUpload(string $file_path, array $allowed_types = ['image/jpeg', 'image/png', 'image/gif']): bool {
    if (!is_file($file_path) || !is_readable($file_path)) return false;
    $file_size = filesize($file_path);
    if ($file_size === false || $file_size === 0 || $file_size > 10 * 1024 * 1024) return false;

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file_path);
    if (!in_array($mime_type, $allowed_types, true)) return false;

    $image_info = @getimagesize($file_path);
    return $image_info !== false
      && $image_info[0] > 0 && $image_info[1] > 0
      && $image_info[0] <= Config::int('limits.paint_max_width') && $image_info[1] <= Config::int('limits.paint_max_height');
  }

  /**
   * Stores a browser-uploaded image using the same time + microsecond basename
   * used by the drawing applications. The client filename is never retained.
   *
   * @return array{picfile:string,img_w:int,img_h:int,pchfile:string,psec:int,utime:string,tool:string,thumbnail:string,nsfw:bool,ctype:string}
   */
  public static function storeUploadedImage(
    array $upload,
    string $image_dir,
    int $max_kilobytes,
    int $max_width,
    int $max_height,
    int $thumbnail_width,
    bool $nsfw,
    int $permission
  ): array {
    $error = $upload['error'] ?? UPLOAD_ERR_NO_FILE;
    if (!is_int($error) && !ctype_digit((string)$error)) {
      throw new ImageUploadException('Invalid uploaded file.', 400);
    }
    $error = (int)$error;
    if ($error !== UPLOAD_ERR_OK) {
      $messages = [
        UPLOAD_ERR_INI_SIZE => 'The uploaded image exceeds the server limit.',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded image exceeds the form limit.',
        UPLOAD_ERR_PARTIAL => 'The image upload was incomplete.',
        UPLOAD_ERR_NO_FILE => 'No image was uploaded.',
      ];
      throw new ImageUploadException($messages[$error] ?? 'Failed to upload image.', 400);
    }

    $temporary_file = $upload['tmp_name'] ?? null;
    $size = $upload['size'] ?? null;
    if (!is_string($temporary_file) || !is_uploaded_file($temporary_file)
      || (!is_int($size) && !ctype_digit((string)$size))) {
      throw new ImageUploadException('Invalid uploaded file.', 400);
    }
    $size = (int)$size;
    $max_bytes = $max_kilobytes * 1024;
    if ($max_kilobytes < 1 || $size < 1 || $size > $max_bytes) {
      throw new ImageUploadException('The uploaded image exceeds the size limit.', 400);
    }

    $types = [
      'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif',
      'image/webp' => 'webp', 'image/avif' => 'avif',
    ];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary_file);
    $image = @getimagesize($temporary_file);
    if (!is_string($mime) || !isset($types[$mime]) || $image === false
      || ($image['mime'] ?? null) !== $mime) {
      throw new ImageUploadException('Unsupported image format.', 400);
    }
    $width = (int)$image[0];
    $height = (int)$image[1];
    if ($max_width < 1 || $max_height < 1 || $width < 1 || $height < 1
      || $width > $max_width || $height > $max_height) {
      throw new ImageUploadException('The uploaded image dimensions exceed the limit.', 400);
    }

    $image_dir = rtrim($image_dir, '/\\') . DIRECTORY_SEPARATOR;
    if (!is_dir($image_dir) || !is_writable($image_dir)) {
      throw new RuntimeException('Image directory is not writable.');
    }
    $extension = $types[$mime];
    $filename = self::newOekakiImageFilename($image_dir, $extension);
    $destination = $image_dir . $filename;
    $staged = tempnam($image_dir, '.noreita_upload_');
    if ($staged === false) throw new RuntimeException('Failed to prepare uploaded image.');

    try {
      if (!move_uploaded_file($temporary_file, $staged)) {
        throw new RuntimeException('Failed to store uploaded image.');
      }
      if (is_file($destination) || !rename($staged, $destination)) {
        throw new RuntimeException('Failed to finalize uploaded image.');
      }
      @chmod($destination, $permission);
      $thumbnail = self::refreshNsfwThumbnail($image_dir, $filename, '', $nsfw, $thumbnail_width, $permission);
      return [
        'picfile' => $filename, 'img_w' => $width, 'img_h' => $height,
        'pchfile' => '', 'psec' => 0, 'utime' => '', 'tool' => 'Upload',
        'thumbnail' => $thumbnail, 'nsfw' => $nsfw, 'ctype' => 'img',
      ];
    } catch (Throwable $e) {
      safe_unlink($staged);
      self::deleteRelatedFiles($image_dir, $filename);
      throw $e;
    }
  }

  private static function newOekakiImageFilename(string $image_dir, string $extension): string {
    for ($attempt = 0; $attempt < 20; $attempt++) {
      // Keep the historical naming convention: Unix timestamp + six microsecond digits.
      $base_name = time() . substr(microtime(), 2, 6);
      if ((glob($image_dir . $base_name . '.*') ?: []) === []) {
        return $base_name . '.' . $extension;
      }
      usleep(1000);
    }
    throw new RuntimeException('Failed to allocate image filename.');
  }

  public static function deleteRelatedFiles(string $image_dir, string $image_name): void {
    foreach (self::relatedFilePaths($image_dir, [$image_name]) as $path) safe_unlink($path);
  }

  public static function stageRelatedFilesForDeletion(
    string $image_dir,
    string $staging_root,
    array $image_names,
    array $posts = []
  ): array {
    $paths = self::relatedFilePaths($image_dir, $image_names);
    if ($paths === []) return ['directory' => '', 'files' => [], 'lock_handle' => null];

    $staging_root = rtrim($staging_root, '/\\');
    if (!is_dir($staging_root) && !mkdir($staging_root, 0700, true) && !is_dir($staging_root)) {
      throw new RuntimeException('Failed to create deletion staging directory.');
    }
    $staging_directory = $staging_root . DIRECTORY_SEPARATOR . 'delete-' . bin2hex(random_bytes(12));
    if (!mkdir($staging_directory, 0700)) {
      throw new RuntimeException('Failed to create deletion staging operation.');
    }

    $lock_path = $staging_directory . DIRECTORY_SEPARATOR . '.lock';
    $lock_handle = @fopen($lock_path, 'c+');
    if ($lock_handle === false || !flock($lock_handle, LOCK_EX | LOCK_NB)) {
      if (is_resource($lock_handle)) fclose($lock_handle);
      @unlink($lock_path);
      @rmdir($staging_directory);
      throw new RuntimeException('Failed to lock deletion staging operation.');
    }
    @chmod($lock_path, 0600);

    $staged = ['directory' => $staging_directory, 'files' => [], 'lock_handle' => $lock_handle];
    $manifest = [
      'version' => 1,
      'created_at' => time(),
      'posts' => self::normalizeDeletionManifestPosts($posts),
      'files' => array_map('basename', $paths),
    ];
    try {
      self::writeDeletionManifest($staging_directory, $manifest);
      foreach ($paths as $path) {
        $destination = $staging_directory . DIRECTORY_SEPARATOR . basename($path);
        if (!rename($path, $destination)) {
          throw new RuntimeException('Failed to stage a related image file for deletion.');
        }
        $staged['files'][] = ['original' => $path, 'staged' => $destination];
      }
      return $staged;
    } catch (Throwable $e) {
      self::rollbackStagedDeletion($staged);
      throw $e;
    }
  }

  public static function rollbackStagedDeletion(array $staged): void {
    $failed = false;
    foreach (array_reverse($staged['files'] ?? []) as $file) {
      $original = (string)($file['original'] ?? '');
      $temporary = (string)($file['staged'] ?? '');
      if ($temporary === '' || !is_file($temporary)) continue;
      if ($original === '' || is_file($original) || !rename($temporary, $original)) $failed = true;
    }
    $directory = (string)($staged['directory'] ?? '');
    if ($failed) {
      self::releaseDeletionLock($staged);
      throw new RuntimeException('Failed to restore a related image file after deletion failure.');
    }
    self::removeDeletionStagingOperation($staged);
  }

  public static function completeStagedDeletion(array $staged): void {
    foreach ($staged['files'] ?? [] as $file) {
      safe_unlink((string)($file['staged'] ?? ''));
    }
    self::removeDeletionStagingOperation($staged);
  }

  public static function recoverStagedDeletions(
    string $image_dir,
    string $staging_root,
    callable $should_restore,
    string $quarantine_root = '',
    int $quarantine_retention_days = 30,
    ?int $now = null
  ): array {
    $result = ['restored' => 0, 'completed' => 0, 'skipped' => 0, 'invalid' => 0,
      'quarantined' => 0, 'purged' => 0];
    $image_dir = rtrim($image_dir, '/\\') . DIRECTORY_SEPARATOR;
    $staging_root = rtrim($staging_root, '/\\');
    $quarantine_root = $quarantine_root !== ''
      ? rtrim($quarantine_root, '/\\')
      : dirname($staging_root) . DIRECTORY_SEPARATOR . 'delete-quarantine';
    $now = $now ?? time();
    $result['purged'] = self::cleanupDeletionQuarantine(
      $quarantine_root, $quarantine_retention_days, 10, $now
    );
    if (!is_dir($staging_root)) return $result;

    foreach (new DirectoryIterator($staging_root) as $operation) {
      if ($operation->isDot() || $operation->isLink() || !$operation->isDir()
        || !preg_match('/^delete-[a-f0-9]{24}$/D', $operation->getFilename())) {
        continue;
      }
      $directory = $operation->getPathname();
      $lock_path = $directory . DIRECTORY_SEPARATOR . '.lock';
      $lock_handle = @fopen($lock_path, 'c+');
      if ($lock_handle === false || !flock($lock_handle, LOCK_EX | LOCK_NB)) {
        if (is_resource($lock_handle)) fclose($lock_handle);
        $result['skipped']++;
        continue;
      }

      $manifest = self::readDeletionManifest($directory);
      if ($manifest !== null && !self::deletionStagingContentsAreExpected($directory, $manifest['files'])) {
        $manifest = null;
      }
      if ($manifest === null) {
        $result['invalid']++;
        if (self::quarantineDeletionStaging($directory, $quarantine_root, $lock_handle, $now)) {
          $result['quarantined']++;
        } else {
          flock($lock_handle, LOCK_UN);
          fclose($lock_handle);
        }
        continue;
      }

      $staged = ['directory' => $directory, 'files' => [], 'lock_handle' => $lock_handle];
      foreach ($manifest['files'] as $filename) {
        $staged['files'][] = [
          'original' => $image_dir . $filename,
          'staged' => $directory . DIRECTORY_SEPARATOR . $filename,
        ];
      }

      if ($should_restore($manifest['posts'])) {
        self::rollbackStagedDeletion($staged);
        $result['restored']++;
      } else {
        // DBから記事が消えている場合は、移動前に残ったファイルも削除を完了する。
        foreach ($staged['files'] as $file) safe_unlink((string)$file['original']);
        self::completeStagedDeletion($staged);
        $result['completed']++;
      }
    }
    return $result;
  }

  public static function cleanupDeletionQuarantine(
    string $quarantine_root,
    int $retention_days,
    int $limit = 10,
    ?int $now = null
  ): int {
    $quarantine_root = rtrim($quarantine_root, '/\\');
    if ($quarantine_root === '' || $retention_days < 1 || $limit < 1 || !is_dir($quarantine_root)) return 0;
    $now = $now ?? time();
    $cutoff = $now - ($retention_days * 86400);
    $removed = 0;
    foreach (new DirectoryIterator($quarantine_root) as $entry) {
      if ($entry->isDot() || $entry->isLink() || !$entry->isDir()
        || !preg_match('/^quarantine-delete-[a-f0-9]{24}-\d{14}-[a-f0-9]{8}$/D', $entry->getFilename())
        || $entry->getMTime() >= $cutoff) {
        continue;
      }
      if (self::removeSafeQuarantineDirectory($entry->getPathname())) $removed++;
      if ($removed >= $limit) break;
    }
    return $removed;
  }

  private static function deletionStagingContentsAreExpected(string $directory, array $manifest_files): bool {
    $allowed = array_fill_keys(array_merge($manifest_files, ['manifest.json', '.lock']), true);
    foreach (new DirectoryIterator($directory) as $entry) {
      if ($entry->isDot()) continue;
      if ($entry->isLink() || !$entry->isFile() || !isset($allowed[$entry->getFilename()])) return false;
    }
    return true;
  }

  /** @param resource $lock_handle */
  private static function quarantineDeletionStaging(
    string $directory,
    string $quarantine_root,
    $lock_handle,
    int $now
  ): bool {
    if (!is_dir($quarantine_root)
      && !@mkdir($quarantine_root, 0700, true)
      && !is_dir($quarantine_root)) {
      return false;
    }
    @chmod($quarantine_root, 0700);
    try {
      $random = bin2hex(random_bytes(4));
    } catch (Throwable $e) {
      $random = substr(hash('sha256', uniqid('', true)), 0, 8);
    }
    $destination = $quarantine_root . DIRECTORY_SEPARATOR . 'quarantine-' . basename($directory)
      . '-' . date('YmdHis', $now) . '-' . $random;
    if (!@rename($directory, $destination)) return false;

    $metadata = json_encode([
      'version' => 1,
      'detected_at' => $now,
      'reason' => 'Invalid deletion manifest or unexpected staging contents.',
    ], JSON_UNESCAPED_SLASHES);
    if (is_string($metadata)) {
      @file_put_contents($destination . DIRECTORY_SEPARATOR . 'quarantine.json', $metadata, LOCK_EX);
    }
    @chmod($destination . DIRECTORY_SEPARATOR . 'quarantine.json', 0600);
    @chmod($destination, 0700);
    flock($lock_handle, LOCK_UN);
    fclose($lock_handle);
    return true;
  }

  private static function removeSafeQuarantineDirectory(string $directory): bool {
    $files = [];
    foreach (new DirectoryIterator($directory) as $entry) {
      if ($entry->isDot()) continue;
      if ($entry->isLink() || !$entry->isFile()) return false;
      $files[] = $entry->getPathname();
    }
    foreach ($files as $file) {
      if (!@unlink($file)) return false;
    }
    return @rmdir($directory);
  }

  private static function relatedFilePaths(string $image_dir, array $image_names): array {
    $image_dir = rtrim($image_dir, '/\\') . DIRECTORY_SEPARATOR;
    $paths = [];
    foreach ($image_names as $image_name) {
      if (!is_string($image_name) || $image_name === '') continue;
      $base_name = pathinfo(basename($image_name), PATHINFO_FILENAME);
      if ($base_name === '') continue;
      foreach (self::RELATED_EXTENSIONS as $extension) {
        $path = $image_dir . $base_name . '.' . $extension;
        if (is_file($path)) $paths[$path] = $path;
      }
      foreach (glob($image_dir . $base_name . '_thumb_*') ?: [] as $thumbnail) {
        if (is_file($thumbnail)) $paths[$thumbnail] = $thumbnail;
      }
    }
    return array_values($paths);
  }

  private static function normalizeDeletionManifestPosts(array $posts): array {
    $normalized = [];
    foreach ($posts as $post) {
      if (!is_array($post)) continue;
      $id = filter_var($post['tid'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
      $picfile = basename((string)($post['picfile'] ?? ''));
      if ($id !== false && $picfile !== '') {
        $normalized[(int)$id] = ['id' => (int)$id, 'picfile' => $picfile];
      }
    }
    return array_values($normalized);
  }

  private static function writeDeletionManifest(string $directory, array $manifest): void {
    $json = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $temporary = $directory . DIRECTORY_SEPARATOR . '.manifest.tmp';
    $path = $directory . DIRECTORY_SEPARATOR . 'manifest.json';
    if (file_put_contents($temporary, $json, LOCK_EX) === false || !rename($temporary, $path)) {
      @unlink($temporary);
      throw new RuntimeException('Failed to write deletion staging manifest.');
    }
    @chmod($path, 0600);
  }

  private static function readDeletionManifest(string $directory): ?array {
    $path = $directory . DIRECTORY_SEPARATOR . 'manifest.json';
    $json = @file_get_contents($path, false, null, 0, 65536);
    $manifest = is_string($json) ? json_decode($json, true) : null;
    if (!is_array($manifest) || (int)($manifest['version'] ?? 0) !== 1
      || !is_array($manifest['posts'] ?? null) || !is_array($manifest['files'] ?? null)) {
      return null;
    }
    $posts = [];
    foreach ($manifest['posts'] as $post) {
      if (!is_array($post) || !is_int($post['id'] ?? null) || $post['id'] < 1
        || !is_string($post['picfile'] ?? null) || basename($post['picfile']) !== $post['picfile']
        || $post['picfile'] === '') {
        return null;
      }
      $posts[] = ['id' => $post['id'], 'picfile' => $post['picfile']];
    }
    $files = [];
    foreach ($manifest['files'] as $filename) {
      if (!is_string($filename) || $filename === '' || basename($filename) !== $filename
        || in_array($filename, ['.', '..', 'manifest.json', '.lock'], true)) {
        return null;
      }
      $files[$filename] = $filename;
    }
    if ($posts === [] || $files === []) return null;
    return ['posts' => $posts, 'files' => array_values($files)];
  }

  private static function removeDeletionStagingOperation(array $staged): void {
    $directory = (string)($staged['directory'] ?? '');
    if ($directory === '') return;
    @unlink($directory . DIRECTORY_SEPARATOR . 'manifest.json');
    @unlink($directory . DIRECTORY_SEPARATOR . '.manifest.tmp');
    self::releaseDeletionLock($staged);
    @unlink($directory . DIRECTORY_SEPARATOR . '.lock');
    if (is_dir($directory)) @rmdir($directory);
  }

  private static function releaseDeletionLock(array $staged): void {
    $handle = $staged['lock_handle'] ?? null;
    if (is_resource($handle)) {
      flock($handle, LOCK_UN);
      fclose($handle);
    }
  }

  public static function createThumbnail(string $source, string $destination, int $width, bool $nsfw = false): string {
    $thumbnail = new Thumbnail($source, $destination, $width, $nsfw);
    return $thumbnail->createThumbnail() ? (string)$thumbnail->getOutputName() : '';
  }

  public static function refreshNsfwThumbnail(
    string $image_dir,
    string $image_name,
    string $current_thumbnail,
    bool $nsfw,
    int $thumbnail_width,
    int $permission,
    bool $always_create = false,
    bool $delete_current = true
  ): string {
    $image_dir = rtrim($image_dir, '/\\') . DIRECTORY_SEPARATOR;
    $image_name = basename($image_name);
    $source = $image_dir . $image_name;
    $size = @getimagesize($source);
    if ($image_name === '' || $size === false) {
      throw new RuntimeException('Posted image was not found.');
    }

    $new_thumbnail = '';
    if ($always_create || $nsfw || (int)$size[0] > $thumbnail_width) {
      $temporary_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_thumbnail_' . bin2hex(random_bytes(8));
      if (!mkdir($temporary_dir, 0700)) throw new RuntimeException('Failed to prepare thumbnail directory.');
      try {
        $temporary_thumbnail = self::createThumbnail($source, $temporary_dir, $thumbnail_width, $nsfw);
        $temporary_path = $temporary_dir . DIRECTORY_SEPARATOR . $temporary_thumbnail;
        if ($temporary_thumbnail === '' || !is_file($temporary_path)) {
          throw new RuntimeException('Failed to update thumbnail.');
        }
        $extension = strtolower(pathinfo($temporary_thumbnail, PATHINFO_EXTENSION));
        $content_hash = substr((string)hash_file('sha256', $temporary_path), 0, 12);
        $state = $nsfw ? 'nsfw' : 'safe';
        $new_thumbnail = pathinfo($image_name, PATHINFO_FILENAME)
          . '_thumb_' . $state . '_' . $content_hash . '.' . $extension;
        $destination = $image_dir . $new_thumbnail;
        if (!rename($temporary_path, $destination)) {
          throw new RuntimeException('Failed to save updated thumbnail.');
        }
        @chmod($destination, $permission);
      } finally {
        foreach (glob($temporary_dir . DIRECTORY_SEPARATOR . '*') ?: [] as $temporary_file) {
          if (is_file($temporary_file)) @unlink($temporary_file);
        }
        @rmdir($temporary_dir);
      }
    }

    $current_thumbnail = basename($current_thumbnail);
    if ($delete_current && $current_thumbnail !== '' && $current_thumbnail !== $image_name && $current_thumbnail !== $new_thumbnail) {
      safe_unlink($image_dir . $current_thumbnail);
    }
    return $new_thumbnail;
  }

  public static function finalizeNewPost(
    string $temp_dir,
    string $image_dir,
    string $image_name,
    string $ctype,
    bool $show_paint_time,
    int $thumbnail_width,
    bool $nsfw,
    int $permission
  ): array {
    $temp_dir = rtrim($temp_dir, '/\\') . DIRECTORY_SEPARATOR;
    $image_dir = rtrim($image_dir, '/\\') . DIRECTORY_SEPARATOR;
    $base_name = pathinfo($image_name, PATHINFO_FILENAME);
    $source = $temp_dir . $image_name;
    $metadata_file = $temp_dir . $base_name . '.dat';

    if (!self::validateUpload($source, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'])) {
      throw new RuntimeException('Invalid image file.');
    }
    $metadata = @file_get_contents($metadata_file, false, null, 0, 1024);
    if ($metadata === false) {
      throw new RuntimeException('Image metadata was not found.');
    }
    $fields = explode("\t", rtrim($metadata) . "\t");
    $start_time = (int)($fields[6] ?? 0);
    $posted_time = (int)($fields[7] ?? 0);
    $tool = (string)($fields[9] ?? '');

    $destination = $image_dir . $image_name;
    if (!rename($source, $destination)) {
      throw new RuntimeException('Failed to save image file.');
    }
    chmod($destination, $permission);

    $size = getimagesize($destination);
    if ($size === false) {
      throw new RuntimeException('Failed to read image dimensions.');
    }
    $paint_seconds = ($show_paint_time && $start_time > 0) ? max(0, $posted_time - $start_time) : 0;
    $paint_time = $paint_seconds > 0 ? calcPtime($paint_seconds) : '';
    $tool_names = [
      'neo' => 'PaintBBS NEO', 'shi' => 'Shi Painter', 'chicken' => 'litaChix', 'chi' => 'litaChix',
      'klecks' => 'Klecks', 'tegaki' => 'Tegaki.js', 'axnos' => 'AxnosPaint',
    ];

    $thumbnail = '';
    if ((int)$size[0] > $thumbnail_width || $nsfw) {
      $thumbnail = self::createThumbnail($destination, $image_dir, $thumbnail_width, $nsfw);
    }

    $animation = '';
    if ($ctype !== 'img') {
      foreach (['pch', 'spch', 'chi', 'tgkr'] as $extension) {
        $candidate = $base_name . '.' . $extension;
        if (is_file($temp_dir . $candidate)) {
          if (rename($temp_dir . $candidate, $image_dir . $candidate)) {
            chmod($image_dir . $candidate, $permission);
            $animation = $candidate;
          }
          break;
        }
      }
    }
    safe_unlink($metadata_file);

    return [
      'img_w' => (int)$size[0], 'img_h' => (int)$size[1], 'pchfile' => $animation,
      'psec' => $paint_seconds, 'utime' => $paint_time, 'tool' => $tool_names[$tool] ?? '???',
      'thumbnail' => $thumbnail, 'nsfw' => $nsfw,
    ];
  }

  public static function replacePostedFiles(
    string $temp_dir,
    string $image_dir,
    string $filename,
    string $image_extension,
    int $_temporary_name,
    string $old_image,
    string $old_animation,
    int $permission
  ): array {
    $temp_dir = rtrim($temp_dir, '/\\') . DIRECTORY_SEPARATOR;
    $image_dir = rtrim($image_dir, '/\\') . DIRECTORY_SEPARATOR;
    $source = $temp_dir . $filename . $image_extension;
    if (!self::validateUpload($source, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'])) {
      throw new RuntimeException('Invalid replacement image.');
    }

    $work_file = tempnam($image_dir, '.noreita_replace_');
    if ($work_file === false || !copy($source, $work_file) || !is_file($work_file)) {
      if (is_string($work_file)) safe_unlink($work_file);
      throw new RuntimeException('Failed to copy replacement image.');
    }
    chmod($work_file, $permission);

    $extension = get_image_type((string)mime_content_type($work_file), $work_file);
    $new_image = $filename . $extension;
    $new_image_path = $image_dir . $new_image;
    $old_image_path = $image_dir . basename($old_image);
    if ($new_image === basename($old_image) || is_file($new_image_path)) {
      safe_unlink($work_file);
      throw new RuntimeException('Replacement image filename already exists.');
    }

    $animation_extension = '';
    foreach (['chi', 'spch', 'pch', 'tgkr'] as $candidate) {
      if (is_file($temp_dir . $filename . '.' . $candidate)) {
        $animation_extension = '.' . $candidate;
        break;
      }
    }
    $new_animation = $animation_extension !== '' ? $filename . $animation_extension : '';
    $new_animation_path = $animation_extension !== '' ? $image_dir . $new_animation : '';
    $animation_source = $animation_extension !== '' ? $temp_dir . $new_animation : '';
    $animation_work_file = '';
    if ($animation_extension !== '') {
      if ($new_animation === basename($old_animation) || is_file($new_animation_path)) {
        safe_unlink($work_file);
        throw new RuntimeException('Replacement animation filename already exists.');
      }
      $animation_work_file = tempnam($image_dir, '.noreita_animation_');
      if ($animation_work_file === false || !copy($animation_source, $animation_work_file)) {
        safe_unlink($work_file);
        if (is_string($animation_work_file)) safe_unlink($animation_work_file);
        throw new RuntimeException('Failed to stage replacement animation.');
      }
      chmod($animation_work_file, $permission);
    }

    $created_files = [];
    try {
      if (!rename($work_file, $new_image_path)) {
        throw new RuntimeException('Failed to publish replacement image.');
      }
      $created_files[] = $new_image_path;
      chmod($new_image_path, $permission);
      if ($animation_extension !== '') {
        if (!rename($animation_work_file, $new_animation_path)) {
          throw new RuntimeException('Failed to publish replacement animation.');
        }
        $created_files[] = $new_animation_path;
        chmod($new_animation_path, $permission);
      }
    } catch (Throwable $e) {
      safe_unlink($work_file);
      if ($animation_work_file !== '') safe_unlink($animation_work_file);
      foreach ($created_files as $created_file) safe_unlink($created_file);
      throw $e;
    }

    $old_files = [];
    if ($old_image !== '' && is_file($old_image_path)) $old_files[] = $old_image_path;
    $old_animation_path = $image_dir . basename($old_animation);
    if ($old_animation !== '' && is_file($old_animation_path)) $old_files[] = $old_animation_path;
    $temporary_files = [$source, $temp_dir . $filename . '.dat'];
    if ($animation_source !== '') $temporary_files[] = $animation_source;

    return [
      'picfile' => $new_image,
      'pchfile' => $new_animation,
      'created_files' => $created_files,
      'old_files' => $old_files,
      'temporary_files' => $temporary_files,
    ];
  }

  public static function rollbackPostedReplacement(array $replacement): void {
    foreach ($replacement['created_files'] ?? [] as $created_file) {
      safe_unlink((string)$created_file);
    }
  }

  public static function completePostedReplacement(array $replacement): void {
    foreach ($replacement['old_files'] ?? [] as $old_file) {
      safe_unlink((string)$old_file);
    }
    foreach ($replacement['temporary_files'] ?? [] as $temporary_file) {
      safe_unlink((string)$temporary_file);
    }
  }
}
