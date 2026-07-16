<?php
// image.inc.php for noReita (C) sakots 2026 MIT License

const IMAGE_INC_VER = 20260716;

final class ImageService {
  private const RELATED_EXTENSIONS = ['png', 'jpg', 'webp', 'avif', 'pch', 'spch', 'dat', 'chi', 'tgkr'];

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
