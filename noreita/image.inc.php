<?php
// image.inc.php for noReita (C) sakots 2026 MIT License

const IMAGE_INC_VER = 20260716;

final class ImageService {
  private const RELATED_EXTENSIONS = ['png', 'jpg', 'webp', 'avif', 'pch', 'spch', 'dat', 'chi', 'tgkr'];
  private const PLAYABLE_ANIMATION_EXTENSIONS = ['pch', 'spch', 'tgkr'];

  public static function isSafeAnimationFilename(string $filename): bool {
    if ($filename === '' || strlen($filename) > 255 || basename($filename) !== $filename) {
      return false;
    }
    if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]{0,127}\.([A-Za-z0-9]+)\z/D', $filename, $matches) !== 1) {
      return false;
    }
    return in_array(strtolower($matches[1]), self::PLAYABLE_ANIMATION_EXTENSIONS, true);
  }

  public static function parseTemporaryMetadata(string $metadata_file): ?array {
    if (!is_file($metadata_file) || !is_readable($metadata_file)
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
      if ($metadata === null || !is_file($temp_dir . $metadata['filename'])) continue;
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
      && $image_info[0] <= PMAX_W && $image_info[1] <= PMAX_H;
  }

  public static function deleteRelatedFiles(string $image_dir, string $image_name): void {
    if ($image_name === '') return;
    $base_name = pathinfo(basename($image_name), PATHINFO_FILENAME);
    foreach (self::RELATED_EXTENSIONS as $extension) {
      safe_unlink(rtrim($image_dir, '/\\') . DIRECTORY_SEPARATOR . $base_name . '.' . $extension);
    }
  }

  public static function createThumbnail(string $source, string $destination, int $width, bool $nsfw = false): string {
    $thumbnail = new Thumbnail($source, $destination, $width, $nsfw);
    return $thumbnail->createThumbnail() ? (string)$thumbnail->getOutputName() : '';
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

    if (!self::validateUpload($source)) {
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
    int $temporary_name,
    string $old_image,
    string $old_animation,
    int $permission
  ): array {
    $temp_dir = rtrim($temp_dir, '/\\') . DIRECTORY_SEPARATOR;
    $image_dir = rtrim($image_dir, '/\\') . DIRECTORY_SEPARATOR;
    $source = $temp_dir . $filename . $image_extension;
    $work_file = $image_dir . $temporary_name . '.tmp';
    if (!copy($source, $work_file) || !is_file($work_file)) {
      throw new RuntimeException('Failed to copy replacement image.');
    }
    chmod($work_file, $permission);
    safe_unlink($image_dir . $old_image);

    $extension = get_image_type((string)mime_content_type($work_file), $work_file);
    $new_image = $filename . $extension;
    if (!rename($work_file, $image_dir . $new_image)) {
      safe_unlink($work_file);
      throw new RuntimeException('Failed to move replacement image.');
    }
    chmod($image_dir . $new_image, $permission);
    safe_unlink($source);
    safe_unlink($temp_dir . $filename . '.dat');

    $animation_extension = '';
    foreach (['chi', 'spch', 'pch', 'tgkr'] as $candidate) {
      if (is_file($temp_dir . $filename . '.' . $candidate)) {
        $animation_extension = '.' . $candidate;
        break;
      }
    }
    safe_unlink($image_dir . $old_animation);
    $new_animation = $filename . $animation_extension;
    if ($animation_extension !== '') {
      $animation_source = $temp_dir . $new_animation;
      $animation_destination = $image_dir . $new_animation;
      if (!copy($animation_source, $animation_destination)) {
        throw new RuntimeException('Failed to copy replacement animation.');
      }
      chmod($animation_destination, $permission);
      safe_unlink($animation_source);
    }
    return ['picfile' => $new_image, 'pchfile' => $new_animation];
  }
}
