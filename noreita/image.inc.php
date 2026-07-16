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
}
