<?php

final class PaintController {
  public static function paint(ApplicationContext $context, string $rep, ?int $replyTo): void { paint_form($context, $rep, $replyTo); }
  public static function continuePainting(ApplicationContext $context, string $type, ?int $replyTo): void {
    $no = (int)filter_input(INPUT_POST, 'no', FILTER_VALIDATE_INT);
    $image = (string)filter_input(INPUT_POST, 'img');
    $animation = (string)filter_input(INPUT_POST, 'pch');
    $ctype = (string)filter_input(INPUT_POST, 'ctype');
    try {
      $post = (new BoardRepository())->findPost($no);
    } catch (PDOException $e) {
      render_error($context, $context->english ? 'Failed to find the image.' : '画像の検索に失敗しました。', 500, $e);
      return;
    }
    // 確認画面を経由しないPOSTでも、公開投稿に属する画像・動画だけを使う。
    // ファイルへのアクセスや差し替え認可の発行より先に、番号とファイルの対応を確認する。
    $image_dir = Config::string('paths.images');
    if ($post === false || (int)($post['invz'] ?? 0) !== 0
      || !ImageService::isSafePostedImageFilename($image)
      || $image !== (string)$post['picfile']
      || !is_file($image_dir . $image) || !is_readable($image_dir . $image)
      || (in_array($ctype, ['pch', 'spch'], true) && $animation === '')
      // CHIは再生用動画ではなく、投稿画像と同名のChickenPaint作業ファイル。
      || ($animation !== '' && ((!ImageService::isSafeAnimationFilename($animation)
          && $animation !== pathinfo($image, PATHINFO_FILENAME) . '.chi')
        || $animation !== (string)$post['pchfile']
        || !is_file($image_dir . $animation) || !is_readable($image_dir . $animation)))) {
      render_error($context, $context->english ? 'The image does not exist.' : '画像が存在しません。', 404);
      return;
    }
    if (!in_array($type, ['new', 'rep'], true)) {
      render_error($context, $context->english ? 'Invalid continuation request.' : '続き描きのリクエストが不正です。', 400);
      return;
    }
    if (Config::bool('features.continue_password') || $type === 'rep') usrchk($context);
    self::paint($context, $type, $replyTo);
  }
  public static function temporary(ApplicationContext $context): void { paint_com($context, 'tmp'); }
  public static function editImage(ApplicationContext $context): void { paint_com($context, ''); }
  public static function animation(ApplicationContext $context): void { open_pch($context); }
  public static function continue(ApplicationContext $context): void { in_continue($context); }
  public static function uploadAnimation(ApplicationContext $context): void { animation_upload($context); }
  public static function temporaryImage(ApplicationContext $context): void { temporary_image($context); }
}
