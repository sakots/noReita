<?php
// image.inc.php for noReita (C) sakots 2026 MIT License

const IMAGE_INC_VER = 20260818;

final class ImageUploadException extends RuntimeException {
}

final class ImageService {
  private const RELATED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'pch', 'spch', 'dat', 'chi', 'psd', 'tgkr'];
  private const TEMPORARY_RELATED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'pch', 'spch', 'dat', 'chi', 'psd', 'tgkr'];
  private const PLAYABLE_ANIMATION_EXTENSIONS = ['pch', 'spch', 'tgkr'];
  private const UPLOADABLE_ANIMATION_EXTENSIONS = ['pch', 'tgkr'];
  /** @var array<string,array{extension:string,label:string,decoder:string,encoder:string}> */
  private const UPLOAD_IMAGE_TYPES = [
    'image/png' => ['extension' => 'png', 'label' => 'PNG', 'decoder' => 'imagecreatefrompng', 'encoder' => 'imagepng'],
    'image/jpeg' => ['extension' => 'jpg', 'label' => 'JPEG', 'decoder' => 'imagecreatefromjpeg', 'encoder' => 'imagejpeg'],
    'image/gif' => ['extension' => 'gif', 'label' => 'GIF', 'decoder' => 'imagecreatefromgif', 'encoder' => 'imagegif'],
    'image/webp' => ['extension' => 'webp', 'label' => 'WebP', 'decoder' => 'imagecreatefromwebp', 'encoder' => 'imagewebp'],
    'image/avif' => ['extension' => 'avif', 'label' => 'AVIF', 'decoder' => 'imagecreatefromavif', 'encoder' => 'imageavif'],
  ];

  /**
   * @param callable(string):bool|null $function_exists
   * @return array<string,array{extension:string,label:string}>
   */
  public static function supportedUploadFormats(?callable $function_exists = null): array {
    $function_exists ??= static fn(string $function): bool => function_exists($function);
    $supported = [];
    foreach (self::UPLOAD_IMAGE_TYPES as $mime => $format) {
      if (!$function_exists($format['decoder']) || !$function_exists($format['encoder'])) continue;
      $supported[$mime] = ['extension' => $format['extension'], 'label' => $format['label']];
    }
    return $supported;
  }

  /** @param callable(string):bool|null $function_exists */
  public static function uploadAccept(?callable $function_exists = null): string {
    return implode(',', array_keys(self::supportedUploadFormats($function_exists)));
  }

  /** @param callable(string):bool|null $function_exists */
  public static function uploadFormatLabel(?callable $function_exists = null): string {
    return implode(' / ', array_column(self::supportedUploadFormats($function_exists), 'label'));
  }

  private static function isDecodableImage(string $file_path, string $mime_type): bool {
    $decoder = self::UPLOAD_IMAGE_TYPES[$mime_type]['decoder'] ?? null;
    if (!is_string($decoder) || !function_exists($decoder)) return false;
    try {
      $image = @call_user_func($decoder, $file_path);
      // GD 2.x cannot decode animated WebP as a complete image. Accept it only when
      // an ANMF frame can be extracted and decoded independently.
      if ($image === false) return $mime_type === 'image/webp' && self::hasDecodableAnimatedWebpFrame($file_path);
      unset($image);
      return true;
    } catch (Throwable) {
      return $mime_type === 'image/webp' && self::hasDecodableAnimatedWebpFrame($file_path);
    }
  }

  /**
   * Decode and re-encode a direct upload before it reaches the public image directory.
   * GD output does not retain EXIF or other container metadata from the original file.
   * Animated raster uploads are stored as their first frame because GD has no animation encoder.
   *
   * @param string $source
   * @param string $destination
   * @param string $mime_type
   * @return void
   */
  private static function reencodeUploadedImage(string $source, string $destination, string $mime_type): void {
    $format = self::UPLOAD_IMAGE_TYPES[$mime_type] ?? null;
    if ($format === null || !function_exists($format['decoder']) || !function_exists($format['encoder'])) {
      throw new ImageUploadException('The detected image format is not supported by this server.', 415);
    }

    $image = @call_user_func($format['decoder'], $source);
    $frame = '';
    if ($image === false && $mime_type === 'image/webp') {
      $frame = tempnam(sys_get_temp_dir(), 'noreita_webp_frame_') ?: '';
      if ($frame !== '' && self::extractAnimatedWebpFirstFrame($source, $frame)) {
        $image = @imagecreatefromwebp($frame);
      }
    }
    if ($image === false) {
      if ($frame !== '') safe_unlink($frame);
      throw new ImageUploadException('The uploaded image could not be processed.', 422);
    }

    try {
      if ($mime_type === 'image/png' || $mime_type === 'image/webp' || $mime_type === 'image/avif') {
        imagealphablending($image, false);
        imagesavealpha($image, true);
      }
      $saved = match ($mime_type) {
        'image/png' => @imagepng($image, $destination, 6),
        'image/jpeg' => @imagejpeg($image, $destination, 90),
        'image/gif' => @imagegif($image, $destination),
        'image/webp' => @imagewebp($image, $destination, 90),
        'image/avif' => @imageavif($image, $destination, 70),
        default => false,
      };
      if (!$saved || !is_file($destination) || (filesize($destination) ?: 0) < 1) {
        throw new ImageUploadException('The uploaded image could not be processed.', 422);
      }
    } finally {
      if ($frame !== '') safe_unlink($frame);
      unset($image);
    }
  }

  /**
   * Check the bounded RIFF chunk layout used by animated WebP files.
   *
   * @param string $file_path
   * @return bool
   */
  private static function isAnimatedWebp(string $file_path): bool {
    $data = @file_get_contents($file_path);
    if (!is_string($data) || strlen($data) < 20 || substr($data, 0, 4) !== 'RIFF' || substr($data, 8, 4) !== 'WEBP') {
      return false;
    }
    $declared_size = unpack('Vsize', substr($data, 4, 4));
    if (!is_array($declared_size) || (int)($declared_size['size'] ?? 0) !== strlen($data) - 8) return false;

    $offset = 12;
    $has_animation_flag = false;
    $has_animation_header = false;
    $has_animation_frame = false;
    $length = strlen($data);
    while ($offset + 8 <= $length) {
      $chunk = substr($data, $offset, 4);
      $size_data = unpack('Vsize', substr($data, $offset + 4, 4));
      $size = is_array($size_data) ? (int)($size_data['size'] ?? -1) : -1;
      $offset += 8;
      if ($size < 0 || $size > $length - $offset) return false;
      if ($chunk === 'VP8X' && $size >= 1 && (ord($data[$offset]) & 0x02) !== 0) $has_animation_flag = true;
      if ($chunk === 'ANIM' && $size === 6) $has_animation_header = true;
      if ($chunk === 'ANMF' && self::animatedWebpFrameData($data, $offset, $size) !== null) $has_animation_frame = true;
      $offset += $size + ($size % 2);
    }
    return $offset === $length && $has_animation_flag && $has_animation_header && $has_animation_frame;
  }

  /**
   * Extract the first ANMF frame as a standalone WebP that GD can decode.
   *
   * @param string $file_path
   * @param string $destination
   * @return bool
   */
  private static function extractAnimatedWebpFirstFrame(string $file_path, string $destination): bool {
    $data = @file_get_contents($file_path);
    if (!is_string($data) || !self::isAnimatedWebp($file_path)) return false;
    $offset = 12;
    $length = strlen($data);
    while ($offset + 8 <= $length) {
      $chunk = substr($data, $offset, 4);
      $size_data = unpack('Vsize', substr($data, $offset + 4, 4));
      $size = is_array($size_data) ? (int)($size_data['size'] ?? -1) : -1;
      $payload_offset = $offset + 8;
      if ($size < 0 || $size > $length - $payload_offset) return false;
      if ($chunk === 'ANMF') {
        $frame = self::animatedWebpFrameData($data, $payload_offset, $size);
        if ($frame === null) {
          $offset = $payload_offset + $size + ($size % 2);
          continue;
        }
        return @file_put_contents($destination, $frame, LOCK_EX) === strlen($frame)
          && self::isDecodableImage($destination, 'image/webp');
      }
      $offset = $payload_offset + $size + ($size % 2);
    }
    return false;
  }

  /**
   * Return a standalone WebP from one valid ANMF frame, or null when its nested chunk layout is invalid.
   *
   * @param string $data
   * @param int $payload_offset
   * @param int $size
   * @return string|null
   */
  private static function animatedWebpFrameData(string $data, int $payload_offset, int $size): ?string {
    if ($size <= 16 || $payload_offset < 0 || $payload_offset + $size > strlen($data)) return null;
    $frame_chunks = substr($data, $payload_offset + 16, $size - 16);
    $offset = 0;
    $length = strlen($frame_chunks);
    $has_image_chunk = false;
    while ($offset + 8 <= $length) {
      $chunk = substr($frame_chunks, $offset, 4);
      $size_data = unpack('Vsize', substr($frame_chunks, $offset + 4, 4));
      $chunk_size = is_array($size_data) ? (int)($size_data['size'] ?? -1) : -1;
      $offset += 8;
      if ($chunk_size < 0 || $chunk_size > $length - $offset) return null;
      if ($chunk === 'VP8 ' || $chunk === 'VP8L') $has_image_chunk = true;
      $offset += $chunk_size + ($chunk_size % 2);
    }
    if ($offset !== $length || !$has_image_chunk) return null;
    return 'RIFF' . pack('V', 4 + $length) . 'WEBP' . $frame_chunks;
  }

  /** @param string $file_path */
  private static function hasDecodableAnimatedWebpFrame(string $file_path): bool {
    $temporary = tempnam(sys_get_temp_dir(), 'noreita_webp_frame_');
    if ($temporary === false) return false;
    try {
      return self::extractAnimatedWebpFirstFrame($file_path, $temporary);
    } finally {
      if (is_file($temporary)) @unlink($temporary);
    }
  }

  /**
   * Return the largest image extent declared by ispe boxes under AVIF's meta/iprp/ipco hierarchy.
   *
   * @param string $file_path
   * @param int $primary_width
   * @param int $primary_height
   * @return array{width:int,height:int}|null
   */
  private static function animatedAvifDimensions(string $file_path, int $primary_width, int $primary_height): ?array {
    $data = @file_get_contents($file_path);
    if (!is_string($data) || strlen($data) < 24 || substr($data, 4, 4) !== 'ftyp') return null;
    $maximum_width = $primary_width;
    $maximum_height = $primary_height;
    $maximum_allowed_width = Config::int('limits.paint_max_width');
    $maximum_allowed_height = Config::int('limits.paint_max_height');
    foreach (self::isoBoxes($data, 0, strlen($data)) as $meta) {
      if ($meta['type'] !== 'meta' || $meta['payload_start'] + 4 > $meta['end']) continue;
      foreach (self::isoBoxes($data, $meta['payload_start'] + 4, $meta['end']) as $property_reference) {
        if ($property_reference['type'] !== 'iprp') continue;
        foreach (self::isoBoxes($data, $property_reference['payload_start'], $property_reference['end']) as $property_container) {
          if ($property_container['type'] !== 'ipco') continue;
          foreach (self::isoBoxes($data, $property_container['payload_start'], $property_container['end']) as $property) {
            // ispe is a FullBox: 4 bytes version/flags, then 4-byte width and height.
            if ($property['type'] !== 'ispe' || $property['payload_start'] + 12 > $property['end']) continue;
            $width_data = unpack('Nwidth', substr($data, $property['payload_start'] + 4, 4));
            $height_data = unpack('Nheight', substr($data, $property['payload_start'] + 8, 4));
            $width = is_array($width_data) ? (int)($width_data['width'] ?? 0) : 0;
            $height = is_array($height_data) ? (int)($height_data['height'] ?? 0) : 0;
            if ($width > 0 && $height > 0 && $width <= $maximum_allowed_width && $height <= $maximum_allowed_height) {
              $maximum_width = max($maximum_width, $width);
              $maximum_height = max($maximum_height, $height);
            }
          }
        }
      }
    }
    return ($maximum_width !== $primary_width || $maximum_height !== $primary_height)
      ? ['width' => $maximum_width, 'height' => $maximum_height]
      : null;
  }

  /**
   * Read complete 32-bit ISO BMFF boxes from one bounded container. Extended and malformed boxes are rejected.
   *
   * @param string $data
   * @param int $start
   * @param int $end
   * @return array<int,array{type:string,payload_start:int,end:int}>
   */
  private static function isoBoxes(string $data, int $start, int $end): array {
    if ($start < 0 || $end < $start || $end > strlen($data)) return [];
    $boxes = [];
    $offset = $start;
    while ($offset + 8 <= $end) {
      $size_data = unpack('Nsize', substr($data, $offset, 4));
      $size = is_array($size_data) ? (int)($size_data['size'] ?? 0) : 0;
      if ($size < 8 || $size === 1 || $size > $end - $offset) return [];
      $boxes[] = [
        'type' => substr($data, $offset + 4, 4),
        'payload_start' => $offset + 8,
        'end' => $offset + $size,
      ];
      $offset += $size;
    }
    return $offset === $end ? $boxes : [];
  }

  /**
   * Put AVIF's decodable first frame on the full animation canvas so the thumbnail keeps its display ratio.
   *
   * @param string $source
   * @param string $directory
   * @param int $thumbnail_width
   * @param int $canvas_width
   * @param int $canvas_height
   * @return string
   */
  private static function animatedAvifThumbnailSource(
    string $source,
    string $directory,
    int $thumbnail_width,
    int $canvas_width,
    int $canvas_height
  ): string {
    if (!function_exists('imagecreatefromavif') || $thumbnail_width < 1 || $canvas_width < 1 || $canvas_height < 1) return '';
    $frame = @imagecreatefromavif($source);
    if ($frame === false) return '';
    $target_height = max(1, (int)round($thumbnail_width * $canvas_height / $canvas_width));
    $canvas = imagecreatetruecolor($thumbnail_width, $target_height);
    if ($canvas === false) return '';
    imagealphablending($canvas, false);
    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    imagefill($canvas, 0, 0, $transparent);
    imagesavealpha($canvas, true);
    $frame_width = imagesx($frame);
    $frame_height = imagesy($frame);
    if ($frame_width < 1 || $frame_height < 1) return '';
    // Cover the animation canvas so the blurred thumbnail has no content-revealing margins.
    $scale = max($thumbnail_width / $frame_width, $target_height / $frame_height);
    $draw_width = max(1, (int)round($frame_width * $scale));
    $draw_height = max(1, (int)round($frame_height * $scale));
    if (!imagecopyresampled(
      $canvas, $frame, (int)floor(($thumbnail_width - $draw_width) / 2), (int)floor(($target_height - $draw_height) / 2),
      0, 0, $draw_width, $draw_height, $frame_width, $frame_height
    )) return '';
    $path = $directory . DIRECTORY_SEPARATOR . 'animated-avif-first-frame.png';
    $saved = imagepng($canvas, $path);
    unset($frame, $canvas);
    return $saved ? $path : '';
  }

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
   * Return a pending image only when it belongs to the requesting user or an administrator.
   * Replay and work files are deliberately never returned by this method.
   *
   * @return array{path:string,mime_type:string}|null
   */
  public static function authorizedTemporaryImage(
    string $temp_dir, string $filename, string $user_code, bool $is_administrator
  ): ?array {
    if (!self::isSafePostedImageFilename($filename)) return null;

    $temp_dir = rtrim($temp_dir, '/\\') . DIRECTORY_SEPARATOR;
    $base_name = pathinfo($filename, PATHINFO_FILENAME);
    $metadata = self::parseTemporaryMetadata($temp_dir . $base_name . '.dat');
    if ($metadata === null || !hash_equals((string)$metadata['filename'], $filename)) return null;
    if (!$is_administrator && ($user_code === ''
      || !hash_equals((string)$metadata['user_code'], $user_code))) {
      return null;
    }

    $path = $temp_dir . $filename;
    if (is_link($path) || !is_file($path) || !is_readable($path)) return null;
    $mime_types = [
      'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
      'webp' => 'image/webp', 'avif' => 'image/avif',
    ];
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!isset($mime_types[$extension])) return null;

    return ['path' => $path, 'mime_type' => $mime_types[$extension]];
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
      $expired_upload = preg_match('/\Apchup-.*-tmp\.(?:s?pch|tgkr)\z/iD', $file) === 1 && $age > 300;
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

  /** @return array<string,mixed> */
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

  /** @param array<int,string> $allowed_types */
  public static function validateUpload(string $file_path, array $allowed_types = ['image/jpeg', 'image/png', 'image/gif']): bool {
    if (!is_file($file_path) || !is_readable($file_path)) return false;
    $file_size = filesize($file_path);
    if ($file_size === false || $file_size === 0 || $file_size > Config::int('limits.paint_image_kb') * 1024) return false;

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file_path);
    if (!in_array($mime_type, $allowed_types, true)) return false;

    $image_info = @getimagesize($file_path);
    return $image_info !== false
      && $image_info[0] > 0 && $image_info[1] > 0
      && $image_info[0] <= Config::int('limits.paint_max_width') && $image_info[1] <= Config::int('limits.paint_max_height')
      && self::isDecodableImage($file_path, $mime_type);
  }

  /**
   * Stores a browser-uploaded image using the same time + microsecond basename
   * used by the drawing applications. The client filename is never retained.
   *
   * @param array<string,mixed> $upload
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

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary_file);
    $types = self::supportedUploadFormats();
    if (!is_string($mime) || !isset(self::UPLOAD_IMAGE_TYPES[$mime])) {
      throw new ImageUploadException('Unsupported image format.', 415);
    }
    if (!isset($types[$mime])) {
      throw new ImageUploadException('The detected image format is not supported by this server.', 415);
    }
    $image = @getimagesize($temporary_file);
    if ($image === false || ($image['mime'] ?? null) !== $mime) {
      throw new ImageUploadException('Unsupported image format.', 400);
    }
    $width = (int)$image[0];
    $height = (int)$image[1];
    if ($max_width < 1 || $max_height < 1 || $width < 1 || $height < 1
      || $width > $max_width || $height > $max_height) {
      throw new ImageUploadException('The uploaded image dimensions exceed the limit.', 400);
    }
    // GDが画素データを展開する前に、ヘッダーの寸法を制限する。
    if (!self::isDecodableImage($temporary_file, $mime)) {
      throw new ImageUploadException('Unsupported image format.', 400);
    }

    $image_dir = rtrim($image_dir, '/\\') . DIRECTORY_SEPARATOR;
    if (!is_dir($image_dir) || !is_writable($image_dir)) {
      throw new RuntimeException('Image directory is not writable.');
    }
    $extension = $types[$mime]['extension'];
    $filename = self::newOekakiImageFilename($image_dir, $extension);
    $destination = $image_dir . $filename;
    $staged_source = tempnam($image_dir, '.noreita_upload_source_');
    $staged_image = tempnam($image_dir, '.noreita_upload_image_');
    if ($staged_source === false || $staged_image === false) {
      if (is_string($staged_source)) safe_unlink($staged_source);
      if (is_string($staged_image)) safe_unlink($staged_image);
      throw new RuntimeException('Failed to prepare uploaded image.');
    }

    try {
      if (!move_uploaded_file($temporary_file, $staged_source)) {
        throw new RuntimeException('Failed to store uploaded image.');
      }
      self::reencodeUploadedImage($staged_source, $staged_image, $mime);
      safe_unlink($staged_source);
      if (is_file($destination) || !rename($staged_image, $destination)) {
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
      safe_unlink($staged_source);
      safe_unlink($staged_image);
      self::deleteRelatedFiles($image_dir, $filename);
      throw $e;
    }
  }

  /**
   * Identify a browser-uploaded NEO/Tegaki replay from both its extension and header.
   * The returned NEO dimensions are used to ensure that the browser-generated PNG
   * actually belongs to the uploaded replay.
   *
   * @return array{extension:string,tool:string,width:int,height:int}
   */
  public static function animationUploadInfo(string $file_path, string $client_name): array {
    if (!is_file($file_path) || !is_readable($file_path)) {
      throw new ImageUploadException('Invalid animation upload.', 400);
    }
    $extension = strtolower(pathinfo(basename($client_name), PATHINFO_EXTENSION));
    if (!in_array($extension, self::UPLOADABLE_ANIMATION_EXTENSIONS, true)) {
      throw new ImageUploadException('Unsupported animation format.', 415);
    }
    $header = @file_get_contents($file_path, false, null, 0, 12);
    if (!is_string($header) || strlen($header) !== 12) {
      throw new ImageUploadException('Invalid animation upload.', 422);
    }

    if ($extension === 'pch') {
      if (substr($header, 0, 3) !== 'NEO') {
        throw new ImageUploadException('Only PaintBBS NEO PCH files can be uploaded.', 422);
      }
      $width = ord($header[4]) + ord($header[5]) * 256;
      $height = ord($header[6]) + ord($header[7]) * 256;
      if ($width < 1 || $height < 1) {
        throw new ImageUploadException('Invalid animation dimensions.', 422);
      }
      return ['extension' => 'pch', 'tool' => 'neo', 'width' => $width, 'height' => $height];
    }

    if (substr($header, 0, 3) !== 'TGK' || !in_array(ord($header[3]), [0, 1], true)) {
      throw new ImageUploadException('Invalid Tegaki replay.', 422);
    }
    // TegakiBinWriter uses DataView's default big-endian byte order.
    $unpacked_size = unpack('Nsize', substr($header, 4, 4));
    $data_size = is_array($unpacked_size) ? (int)($unpacked_size['size'] ?? 0) : 0;
    if ($data_size < 1 || $data_size > 134217728) {
      throw new ImageUploadException('Invalid Tegaki replay size.', 422);
    }
    return ['extension' => 'tgkr', 'tool' => 'tegaki', 'width' => 0, 'height' => 0];
  }

  /**
   * Store a replay and the final PNG rendered from it by the browser as one pending drawing.
   * The .dat metadata is renamed last and therefore acts as the commit marker seen by readers.
   *
   * @param array<string,mixed> $picture_upload
   * @param array<string,mixed> $animation_upload
   * @return array{picfile:string,preview:string}
   */
  public static function storeUploadedAnimation(
    array $picture_upload,
    array $animation_upload,
    string $temp_dir,
    string $user_code,
    int $resto,
    int $image_max_bytes,
    int $animation_max_bytes,
    int $max_width,
    int $max_height,
    int $public_permission,
    int $private_permission
  ): array {
    $picture = self::checkedUploadedFile($picture_upload, $image_max_bytes, 'image');
    $animation = self::checkedUploadedFile($animation_upload, $animation_max_bytes, 'animation');
    $animation_info = self::animationUploadInfo(
      $animation['path'], (string)($animation_upload['name'] ?? '')
    );

    $image_info = @getimagesize($picture['path']);
    if (!is_array($image_info) || ($image_info['mime'] ?? '') !== 'image/png') {
      throw new ImageUploadException('The generated animation image is invalid.', 422);
    }
    $width = (int)$image_info[0];
    $height = (int)$image_info[1];
    if ($max_width < 1 || $max_height < 1 || $width < 1 || $height < 1
      || $width > $max_width || $height > $max_height) {
      throw new ImageUploadException('The generated animation image dimensions exceed the limit.', 413);
    }
    if ($animation_info['extension'] === 'pch'
      && ($width !== $animation_info['width'] || $height !== $animation_info['height'])) {
      throw new ImageUploadException('The generated image does not match the PCH file.', 422);
    }
    // Reject excessive dimensions before GD allocates a decoded image buffer.
    if (!self::isDecodableImage($picture['path'], 'image/png')) {
      throw new ImageUploadException('The generated animation image is invalid.', 422);
    }

    $temp_dir = rtrim($temp_dir, '/\\') . DIRECTORY_SEPARATOR;
    if (!is_dir($temp_dir) || !is_writable($temp_dir)) {
      throw new RuntimeException('Temporary image directory is not writable.');
    }
    $image_name = self::newOekakiImageFilename($temp_dir, 'png');
    $base_name = pathinfo($image_name, PATHINFO_FILENAME);
    $animation_name = $base_name . '.' . $animation_info['extension'];
    $metadata_name = $base_name . '.dat';
    $image_stage = tempnam($temp_dir, '.noreita_animation_image_');
    $animation_stage = tempnam($temp_dir, '.noreita_animation_work_');
    $metadata_stage = tempnam($temp_dir, '.noreita_animation_metadata_');
    if ($image_stage === false || $animation_stage === false || $metadata_stage === false) {
      foreach ([$image_stage, $animation_stage, $metadata_stage] as $stage) {
        if (is_string($stage)) safe_unlink($stage);
      }
      throw new RuntimeException('Failed to prepare animation upload.');
    }

    $final_paths = [
      $temp_dir . $image_name,
      $temp_dir . $animation_name,
      $temp_dir . $metadata_name,
    ];
    $now = time();
    $ip = RequestInfo::clientIp();
    $host = $ip !== '' ? gethostbyaddr($ip) : '';
    $agent = t((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $metadata = implode("\t", [
      $ip, $host, $agent, '.png', trim($user_code), '', (string)$now, (string)$now,
      $resto > 0 ? (string)$resto : '', $animation_info['tool'], '',
    ]) . "\n";

    try {
      if (!move_uploaded_file($picture['path'], $image_stage)
        || !move_uploaded_file($animation['path'], $animation_stage)
        || file_put_contents($metadata_stage, $metadata, LOCK_EX) !== strlen($metadata)) {
        throw new RuntimeException('Failed to stage animation upload.');
      }
      @chmod($image_stage, $public_permission);
      @chmod($animation_stage, $private_permission);
      @chmod($metadata_stage, $private_permission);
      if (is_file($final_paths[0]) || !rename($image_stage, $final_paths[0])) {
        throw new RuntimeException('Failed to save the generated animation image.');
      }
      if (is_file($final_paths[1]) || !rename($animation_stage, $final_paths[1])) {
        throw new RuntimeException('Failed to save the animation file.');
      }
      // Publish metadata last so incomplete pairs never appear in the temporary image list.
      if (is_file($final_paths[2]) || !rename($metadata_stage, $final_paths[2])) {
        throw new RuntimeException('Failed to save animation metadata.');
      }
      @chmod($final_paths[0], $public_permission);
      @chmod($final_paths[1], $private_permission);
      @chmod($final_paths[2], $private_permission);
      return ['picfile' => $image_name, 'preview' => $image_name];
    } catch (Throwable $e) {
      foreach ([$image_stage, $animation_stage, $metadata_stage] as $stage) safe_unlink($stage);
      foreach ($final_paths as $path) safe_unlink($path);
      throw $e;
    }
  }

  /**
   * @param array<string,mixed> $upload
   * @return array{path:string,size:int}
   */
  private static function checkedUploadedFile(array $upload, int $max_bytes, string $label): array {
    $error = $upload['error'] ?? UPLOAD_ERR_NO_FILE;
    $path = $upload['tmp_name'] ?? null;
    $declared_size = $upload['size'] ?? null;
    if ((!is_int($error) && !ctype_digit((string)$error)) || (int)$error !== UPLOAD_ERR_OK
      || !is_string($path) || !is_uploaded_file($path)
      || (!is_int($declared_size) && !ctype_digit((string)$declared_size))) {
      throw new ImageUploadException("Invalid {$label} upload.", 400);
    }
    $actual_size = @filesize($path);
    $size = max((int)$declared_size, $actual_size === false ? 0 : (int)$actual_size);
    if ($max_bytes < 1 || $size < 1 || $size > $max_bytes) {
      throw new ImageUploadException("The {$label} upload exceeds the size limit.", 413);
    }
    return ['path' => $path, 'size' => $size];
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

  /**
   * @param array<int,string> $image_names
   * @param array<int,array<string,mixed>> $posts
   * @return array<string,mixed>
   */
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

  /** @param array<string,mixed> $staged */
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

  /** @param array<string,mixed> $staged */
  public static function completeStagedDeletion(array $staged): void {
    foreach ($staged['files'] ?? [] as $file) {
      safe_unlink((string)($file['staged'] ?? ''));
    }
    self::removeDeletionStagingOperation($staged);
  }

  /**
   * @param callable(array<int,array{id:int,picfile:string}>):bool $should_restore
   * @return array<string,int>
   */
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

  /** @param array<int,string> $manifest_files */
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

  /** @param array<int,string> $image_names */
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

  /** @param array<int,array<string,mixed>> $posts */
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

  /** @param array<string,mixed> $manifest */
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

  /** @param array<string,mixed> $staged */
  private static function removeDeletionStagingOperation(array $staged): void {
    $directory = (string)($staged['directory'] ?? '');
    if ($directory === '') return;
    @unlink($directory . DIRECTORY_SEPARATOR . 'manifest.json');
    @unlink($directory . DIRECTORY_SEPARATOR . '.manifest.tmp');
    self::releaseDeletionLock($staged);
    @unlink($directory . DIRECTORY_SEPARATOR . '.lock');
    if (is_dir($directory)) @rmdir($directory);
  }

  /** @param array<string,mixed> $staged */
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
        // GD 2.x cannot read animated WebP directly. Extract its first ANMF frame before
        // thumbnailing; if that fails, never fall back to the original NSFW image.
        $animated_webp = (($size['mime'] ?? '') === 'image/webp') && self::isAnimatedWebp($source);
        $animated_avif_size = (($size['mime'] ?? '') === 'image/avif')
          ? self::animatedAvifDimensions($source, (int)$size[0], (int)$size[1])
          : null;
        $thumbnail_source = $source;
        if ($animated_webp) {
          $first_frame = $temporary_dir . DIRECTORY_SEPARATOR . 'animated-webp-first-frame.webp';
          if (self::extractAnimatedWebpFirstFrame($source, $first_frame)) {
            $thumbnail_source = $first_frame;
          } elseif (!$nsfw) {
            $current_thumbnail = basename($current_thumbnail);
            if ($delete_current && $current_thumbnail !== '' && $current_thumbnail !== $image_name) {
              safe_unlink($image_dir . $current_thumbnail);
            }
            return '';
          }
        }
        if ($animated_avif_size !== null && $nsfw) {
          $normalized_avif = self::animatedAvifThumbnailSource(
            $source, $temporary_dir, $thumbnail_width, $animated_avif_size['width'], $animated_avif_size['height']
          );
          if ($normalized_avif !== '') $thumbnail_source = $normalized_avif;
        }
        $temporary_thumbnail = $animated_avif_size !== null && $nsfw
          ? ($thumbnail_source !== $source
            ? self::createThumbnail($thumbnail_source, $temporary_dir, $thumbnail_width, true)
            : self::createNsfwPlaceholderThumbnail(
              $temporary_dir, $thumbnail_width, $animated_avif_size['width'], $animated_avif_size['height']
            ))
          : ($thumbnail_source === $source && $animated_webp
          ? ''
          : self::createThumbnail($thumbnail_source, $temporary_dir, $thumbnail_width, $nsfw));
        if ($temporary_thumbnail === '' && $nsfw) {
          $temporary_thumbnail = self::createNsfwPlaceholderThumbnail(
            $temporary_dir, $thumbnail_width, (int)$size[0], (int)$size[1]
          );
        }
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

  /**
   * Create a non-revealing thumbnail when GD cannot decode an otherwise valid animated image.
   *
   * @param string $directory
   * @param int $width
   * @param int $source_width
   * @param int $source_height
   * @return string
   */
  private static function createNsfwPlaceholderThumbnail(string $directory, int $width, int $source_width, int $source_height): string {
    if ($width < 1 || $source_width < 1 || $source_height < 1) return '';
    $height = max(1, (int)floor($width * $source_height / $source_width));
    $image = imagecreatetruecolor($width, $height);
    if ($image === false) return '';
    imagefill($image, 0, 0, imagecolorallocate($image, 0, 0, 0));
    $base = $directory . DIRECTORY_SEPARATOR . 'nsfw-placeholder';
    $result = false;
    $name = '';
    if (function_exists('imageavif')) {
      $name = 'nsfw-placeholder.avif';
      $result = imageavif($image, $base . '.avif', 70);
    } elseif (function_exists('imagewebp')) {
      $name = 'nsfw-placeholder.webp';
      $result = imagewebp($image, $base . '.webp', 80);
    } else {
      $name = 'nsfw-placeholder.jpg';
      $result = imagejpeg($image, $base . '.jpg', 80);
    }
    unset($image);
    return $result ? $name : '';
  }

  public static function toolDisplayName(string $tool): string {
    $tool_names = [
      'neo' => 'PaintBBS NEO', 'shi' => 'Shi Painter', 'chicken' => 'litaChix', 'chi' => 'litaChix',
      'klecks' => 'Klecks', 'tegaki' => 'Tegaki.js', 'axnos' => 'AxnosPaint',
    ];
    return $tool_names[$tool] ?? '???';
  }

  /**
   * @param callable(array<string,mixed>):void|null $save_post Save the database row before removing temporary files.
   * @return array<string,mixed>
   */
  public static function finalizeNewPost(
    string $temp_dir,
    string $image_dir,
    string $image_name,
    string $ctype,
    bool $show_paint_time,
    int $thumbnail_width,
    bool $nsfw,
    int $permission,
    ?callable $save_post = null
  ): array {
    $published = [];
    $temporary = [];
    try {
      $result = self::publishNewPostFiles(
        $temp_dir, $image_dir, $image_name, $ctype, $show_paint_time,
        $thumbnail_width, $nsfw, $permission, $published, $temporary
      );
      if ($save_post !== null) {
        $save_post($result);
      }
    } catch (Throwable $e) {
      foreach (array_reverse($published) as $path) {
        safe_unlink($path);
      }
      throw $e;
    }
    foreach ($temporary as $path) {
      safe_unlink($path);
    }
    return $result;
  }

  /**
   * @param list<string> $published Files created by this attempt, for rollback.
   * @param list<string> $temporary Files to remove only after the database commit.
   * @return array<string,mixed>
   */
  private static function publishNewPostFiles(
    string $temp_dir, string $image_dir, string $image_name, string $ctype,
    bool $show_paint_time, int $thumbnail_width, bool $nsfw, int $permission,
    array &$published, array &$temporary
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
    self::copyNewPostFile($source, $destination, $permission, $published, $temporary);

    $size = getimagesize($destination);
    if ($size === false) {
      throw new RuntimeException('Failed to read image dimensions.');
    }
    $paint_seconds = ($show_paint_time && $start_time > 0) ? max(0, $posted_time - $start_time) : 0;
    $paint_time = $paint_seconds > 0 ? calcPtime($paint_seconds) : '';
    $thumbnail = '';
    if ((int)$size[0] > $thumbnail_width || $nsfw) {
      // Keep thumbnails separate from their source. Using the source basename directly can
      // overwrite an animated WebP/AVIF when GD selects the same output format.
      $thumbnail = self::refreshNsfwThumbnail(
        $image_dir, $image_name, '', $nsfw, $thumbnail_width, $permission
      );
      if ($thumbnail !== '') {
        $published[] = $image_dir . $thumbnail;
      }
    }

    $animation = '';
    if ($ctype !== 'img') {
      foreach (['pch', 'spch', 'chi', 'tgkr'] as $extension) {
        $candidate = $base_name . '.' . $extension;
        if (is_file($temp_dir . $candidate)) {
          self::copyNewPostFile($temp_dir . $candidate, $image_dir . $candidate, $permission, $published, $temporary);
          $animation = $candidate;
          break;
        }
      }
    }
    $psd = $base_name . '.psd';
    if (is_file($temp_dir . $psd)) {
      self::copyNewPostFile($temp_dir . $psd, $image_dir . $psd, $permission, $published, $temporary);
    }
    $temporary[] = $metadata_file;

    return [
      'img_w' => (int)$size[0], 'img_h' => (int)$size[1], 'pchfile' => $animation,
      'psec' => $paint_seconds, 'utime' => $paint_time, 'tool' => self::toolDisplayName($tool),
      'thumbnail' => $thumbnail, 'nsfw' => $nsfw,
    ];
  }

  /**
   * @param list<string> $published
   * @param list<string> $temporary
   */
  private static function copyNewPostFile(
    string $source, string $destination, int $permission,
    array &$published, array &$temporary
  ): void {
    // Exclusive creation avoids overwriting files from another post or concurrent attempt.
    $output = @fopen($destination, 'xb');
    if ($output === false) {
      throw new RuntimeException('Failed to create posted file.');
    }
    $published[] = $destination;
    $input = null;
    try {
      $input = @fopen($source, 'rb');
      if ($input === false) {
        throw new RuntimeException('Failed to read temporary file.');
      }
      $size = fstat($input);
      $copied = stream_copy_to_stream($input, $output);
      if ($size === false || $copied === false || $copied !== $size['size'] || !fflush($output)) {
        throw new RuntimeException('Failed to copy posted file.');
      }
      if (!chmod($destination, $permission)) {
        throw new RuntimeException('Failed to set posted file permissions.');
      }
      $temporary[] = $source;
    } finally {
      if (is_resource($input)) fclose($input);
      fclose($output);
    }
  }

  /** @return array<string,mixed> */
  public static function replacePostedFiles(
    string $temp_dir,
    string $image_dir,
    string $filename,
    string $image_extension,
    int $_temporary_name,
    string $old_image,
    string $old_animation,
    int $permission,
    bool $english = false
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

    $size = getimagesize($work_file);
    if ($size === false) {
      safe_unlink($work_file);
      throw new RuntimeException('Failed to read replacement image dimensions.');
    }
    $extension = get_image_type((string)mime_content_type($work_file));
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
    $temporary_files = [$source, $temp_dir . $filename . '.dat'];
    if ($animation_source !== '') $temporary_files[] = $animation_source;
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
      $psd_source = $temp_dir . $filename . '.psd';
      if (is_file($psd_source)) {
        self::copyNewPostFile(
          $psd_source, $image_dir . $filename . '.psd', $permission, $created_files, $temporary_files
        );
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
    $old_psd_path = $image_dir . pathinfo(basename($old_image), PATHINFO_FILENAME) . '.psd';
    if ($old_image !== '' && is_file($old_psd_path)) $old_files[] = $old_psd_path;

    return [
      'picfile' => $new_image,
      'pchfile' => $new_animation,
      'img_w' => (int)$size[0],
      'img_h' => (int)$size[1],
      'created_files' => $created_files,
      'old_files' => $old_files,
      'temporary_files' => $temporary_files,
    ];
  }

  /** @param array<string,mixed> $replacement */
  public static function rollbackPostedReplacement(array $replacement): void {
    foreach ($replacement['created_files'] ?? [] as $created_file) {
      safe_unlink((string)$created_file);
    }
  }

  /** @param array<string,mixed> $replacement */
  public static function completePostedReplacement(array $replacement): void {
    foreach ($replacement['old_files'] ?? [] as $old_file) {
      safe_unlink((string)$old_file);
    }
    foreach ($replacement['temporary_files'] ?? [] as $temporary_file) {
      safe_unlink((string)$temporary_file);
    }
  }
}
