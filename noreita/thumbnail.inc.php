<?php
// thumbnail.inc.php for noReita (C)さこつ @sakots 2026 MIT License
// https://oekakibbs.moe/
// 画像形式変換と縮小の両方を行うクラスです。GDを使います。nsfw対応。
// 縦横比は維持されます。
// png、gif、webp、avif対応。
// 出力は自動でavif、webpの優先順位で保存されます。
// 環境によってはjpgしか保存できないこともあります。

// 使い方
// $thumb = new Thumbnail('input.png', 'thumb_dir', 300, 1);
// $thumb->create();
// これでinput.pngを幅300pxにしてthumb_dirディレクトリに保存します。
// nsfwスイッチを1またはtrueにすると、サムネイル画像をぼかします。
// 省略またはfalse、0ならぼかしません。

const THUMBNAIL_VER = 20260820; //thumbnail.inc.phpのバージョン

class Thumbnail {
  private string $image_url; // 入力画像URL
  private string $thumb_dir; // 出力ディレクトリ
  private int $thumb_width; // サムネイルの幅（高さは幅で決まります）
  private bool $nsfw; // nsfwスイッチ
  private ?string $output_basename;
  private ?string $last_output_path = null;

  public function __construct(
    string $image_url,
    string $thumb_dir,
    int $thumb_width,
    bool $nsfw = false,
    ?string $output_basename = null
  ) {
    $this->image_url = $image_url;
    $this->thumb_dir = rtrim($thumb_dir, DIRECTORY_SEPARATOR);
    $this->thumb_width = $thumb_width;
    $this->nsfw = $nsfw;
    $this->output_basename = $output_basename;
  }

  public function getOutputPath(): ?string {
    return $this->last_output_path;
  }

  public function getOutputUrl(): ?string {
    return $this->last_output_path;
  }

  public function getOutputName(): ?string {
    return $this->last_output_path ? basename($this->last_output_path) : null;
  }

  /** @return GdImage|false */
  private static function createTransparentCanvas(int $width, int $height) {
    $image = imagecreatetruecolor($width, $height);
    if ($image === false) return false;
    imagealphablending($image, false);
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefill($image, 0, 0, $transparent);
    imagesavealpha($image, true);
    return $image;
  }

  public function createThumbnail(): bool {
    // 入力画像の情報を取得
    $info = getimagesize($this->image_url);
    if ($info === false) {
      return false; // 画像情報の取得に失敗
    }

    $src_width = $info[0];
    $src_height = $info[1];
    if ($src_width <= 0 || $src_height <= 0 || $this->thumb_width <= 0) {
      return false;
    }
    $mime = $info['mime'];

    // 出力ファイルのベースネーム
    $path_info = pathinfo($this->image_url);
    $base_filename = $this->output_basename ?? $path_info['filename'];
    $output_base = $this->thumb_dir . DIRECTORY_SEPARATOR . $base_filename;

    if (!is_dir($this->thumb_dir) && !mkdir($this->thumb_dir, 0755, true)) {
      return false;
    }

    // 縦横比を維持してサムネイルの高さを計算
    $thumb_height = (int)($this->thumb_width * $src_height / $src_width);

    // 入力画像を読み込む
    switch ($mime) {
      case 'image/jpeg':
        if (!function_exists('imagecreatefromjpeg')) return false;
        $src_image = imagecreatefromjpeg($this->image_url);
        break;
      case 'image/png':
        if (!function_exists('imagecreatefrompng')) return false;
        $src_image = imagecreatefrompng($this->image_url);
        break;
      case 'image/webp':
        if (!function_exists('imagecreatefromwebp')) return false;
        $src_image = imagecreatefromwebp($this->image_url);
        break;
      case 'image/avif':
        if (!function_exists('imagecreatefromavif')) return false;
        $src_image = imagecreatefromavif($this->image_url);
        break;
      case 'image/gif':
        if (!function_exists('imagecreatefromgif')) return false;
        $src_image = imagecreatefromgif($this->image_url);
        break;
      default:
        return false; // 対応していない画像形式
    }

    if ($src_image === false) {
      return false; // 画像の読み込みに失敗
    }

    // サムネイル用の空の画像を作成
    $thumb_image = self::createTransparentCanvas($this->thumb_width, $thumb_height);
    if ($thumb_image === false) {
      return false; // サムネイル画像の作成に失敗
    }

    // 画像をリサイズしてサムネイルにコピー
    if (!imagecopyresampled($thumb_image, $src_image, 0, 0, 0, 0, $this->thumb_width, $thumb_height, $src_width, $src_height)) {
      return false; // リサイズに失敗
    }
    // nsfwスイッチがオンならぼかす
    if ($this->nsfw) {
      // ぼかしの強さを調整するために、サムネイルをさらに縮小してから拡大する方法を取ります。
      $blur_strength = 10; // ぼかしの強さ（数値が大きいほどぼかしが強くなります）
      $small_width = (int)($this->thumb_width / $blur_strength);
      $small_height = (int)($thumb_height / $blur_strength);

      // 小さい画像を作成
      $small_image = self::createTransparentCanvas($small_width, $small_height);
      if ($small_image === false) return false;
      imagecopyresampled($small_image, $thumb_image, 0, 0, 0, 0, $small_width, $small_height, $this->thumb_width, $thumb_height);

      // 小さい画像を元のサイズに拡大してぼかす
      imagecopyresampled($thumb_image, $small_image, 0, 0, 0, 0, $this->thumb_width, $thumb_height, $small_width, $small_height);

    }

    // サムネイルを保存
    if (function_exists('imageavif')) {
      $filename_avif = $output_base . '.avif';
      $result = imageavif($thumb_image, $filename_avif, 70);
      if ($result) {
        $this->last_output_path = $filename_avif;
      }
    } elseif (function_exists('imagewebp')) {
      $filename_webp = $output_base . '.webp';
      $result = imagewebp($thumb_image, $filename_webp, 80);
      if ($result) {
        $this->last_output_path = $filename_webp;
      }
    } else {
      $filename_jpg = $output_base . '.jpg';
      // JPEGはアルファチャンネルを持たないため、透明部分を黒ではなく白で合成する。
      $jpeg_image = imagecreatetruecolor($this->thumb_width, $thumb_height);
      if ($jpeg_image === false) return false;
      imagefill($jpeg_image, 0, 0, imagecolorallocate($jpeg_image, 255, 255, 255));
      imagecopy($jpeg_image, $thumb_image, 0, 0, 0, 0, $this->thumb_width, $thumb_height);
      $result = imagejpeg($jpeg_image, $filename_jpg, 80);
      if ($result) {
        $this->last_output_path = $filename_jpg;
      }
    }

    return $result;
  }
}
