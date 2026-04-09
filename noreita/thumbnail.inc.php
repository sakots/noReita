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
// これでinput.pngを幅300pxにしてthumb_dirディレクトリにinput.webpと可能ならinput.avifに保存します。
// nsfwスイッチを1にすると、サムネイル画像をぼかします。省略すればぼかしません。

$thumbnail_ver = 20260406;

class Thumbnail {
  private string $image_url; // 入力画像URL
  private string $thumb_dir; // 出力ディレクトリ
  private int $thumb_width; // サムネイルの幅（高さは幅で決まります）
  private int $nsfw; // nsfwスイッチ
  private ?string $last_output_path = null;

  public function __construct(string $image_url, string $thumb_dir, int $thumb_width, int $nsfw = 0) {
    $this->image_url = $image_url;
    $this->thumb_dir = rtrim($thumb_dir, DIRECTORY_SEPARATOR);
    $this->thumb_width = $thumb_width;
    $this->nsfw = $nsfw;
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

  public function createThumbnail(): bool {
    // 入力画像の情報を取得
    $info = getimagesize($this->image_url);
    if ($info === false) {
      return false; // 画像情報の取得に失敗
    }

    $src_width = $info[0];
    $src_height = $info[1];
    $mime = $info['mime'];

    // 出力ファイルのベースネーム
    $path_info = pathinfo($this->image_url);
    $base_filename = $path_info['filename'];
    $output_base = $this->thumb_dir . DIRECTORY_SEPARATOR . $base_filename;

    if (!is_dir($this->thumb_dir) && !mkdir($this->thumb_dir, 0755, true)) {
      return false;
    }

    // 縦横比を維持してサムネイルの高さを計算
    $thumb_height = (int)($this->thumb_width * $src_height / $src_width);

    // 入力画像を読み込む
    switch ($mime) {
      case 'image/jpeg':
        $src_image = imagecreatefromjpeg($this->image_url);
        break;
      case 'image/png':
        $src_image = imagecreatefrompng($this->image_url);
        break;
      case 'image/webp':
        $src_image = imagecreatefromwebp($this->image_url);
        break;
      case 'image/avif':
        $src_image = imagecreatefromavif($this->image_url);
        break;
      case 'image/gif':
        $src_image = imagecreatefromgif($this->image_url);
        break;
      default:
        return false; // 対応していない画像形式
    }

    if ($src_image === false) {
      return false; // 画像の読み込みに失敗
    }

    // サムネイル用の空の画像を作成
    $thumb_image = imagecreatetruecolor($this->thumb_width, $thumb_height);
    if ($thumb_image === false) {
      imagedestroy($src_image);
      return false; // サムネイル画像の作成に失敗
    }

    // 画像をリサイズしてサムネイルにコピー
    if (!imagecopyresampled($thumb_image, $src_image, 0, 0, 0, 0, $this->thumb_width, $thumb_height, $src_width, $src_height)) {
      if(PHP_VERSION_ID < 80000) imagedestroy($src_image);
      if(PHP_VERSION_ID < 80000) imagedestroy($thumb_image);
      return false; // リサイズに失敗
    }
    // nsfwスイッチがオンならぼかす
    if ($this->nsfw) {
      // ぼかしの強さを調整するために、サムネイルをさらに縮小してから拡大する方法を取ります。
      $blur_strength = 10; // ぼかしの強さ（数値が大きいほどぼかしが強くなります）
      $small_width = (int)($this->thumb_width / $blur_strength);
      $small_height = (int)($thumb_height / $blur_strength);

      // 小さい画像を作成
      $small_image = imagecreatetruecolor($small_width, $small_height);
      imagecopyresampled($small_image, $thumb_image, 0, 0, 0, 0, $small_width, $small_height, $this->thumb_width, $thumb_height);

      // 小さい画像を元のサイズに拡大してぼかす
      imagecopyresampled($thumb_image, $small_image, 0, 0, 0, 0, $this->thumb_width, $thumb_height, $small_width, $small_height);

      if(PHP_VERSION_ID < 80000) imagedestroy($small_image);
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
      $result = imagejpeg($thumb_image, $filename_jpg, 80);
      if ($result) {
        $this->last_output_path = $filename_jpg;
      }
    }

    // サムネイルを保存した後にリサイズ画像を破棄
    if(PHP_VERSION_ID < 80000) imagedestroy($src_image);
    if(PHP_VERSION_ID < 80000) imagedestroy($thumb_image);
    return $result;
  }
}
