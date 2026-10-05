<?php

final class PostController {
  public static function register(ApplicationContext $context): void { regist($context); }
  public static function edit(ApplicationContext $context): void { editform($context); }
  public static function saveEdit(ApplicationContext $context): void { editexec($context); }
  public static function delete(ApplicationContext $context): void { delmode($context); }
  public static function replaceImage(ApplicationContext $context): void { picreplace($context); }

  // 投稿の認可を描画開始時のコードと所有者に結び付け、他の一時画像への流用を防ぐ。
  public static function replacementAuthorization(ApplicationContext $context, int $no, string $code): array {
    RequestSecurity::startSession();
    $authorization = $_SESSION['image_replacement_authorization'] ?? null;
    if (!is_array($authorization) || $no <= 0 || $code === ''
      || (int)($authorization['post_id'] ?? 0) !== $no
      || (string)($authorization['password'] ?? '') === ''
      || (int)($authorization['expires_at'] ?? 0) < time()
      || !hash_equals((string)($authorization['replacement_code'] ?? ''), $code)
      || !hash_equals((string)($authorization['user_code'] ?? ''), $context->usercode)) {
      render_error($context, $context->english ? 'Invalid image replacement authorization.' : '画像差し替えの認証が無効です。もう一度やり直してください。', 403);
    }
    return $authorization;
  }

  // GETで戻る描画ツールには確認フォームのみを表示し、画像や投稿は変更しない。
  public static function replacementForm(ApplicationContext $context): void {
    $no = (int)filter_input(INPUT_GET, 'no', FILTER_VALIDATE_INT);
    $code = (string)filter_input(INPUT_GET, 'repcode');
    self::replacementAuthorization($context, $no, $code);
    $context->data['othermode'] = 'picreplace';
    $context->data['replacement_no'] = $no;
    $context->data['replacement_code'] = $code;
    $context->data['replacement_label'] = $context->english ? 'Replace image' : '画像を差し替える';
    $context->data['stime'] = (int)filter_input(INPUT_GET, 'stime', FILTER_VALIDATE_INT);
    $context->data['token'] = RequestSecurity::csrfToken();
    echo $context->templates->render(OTHERFILE, $context->data);
  }
}
