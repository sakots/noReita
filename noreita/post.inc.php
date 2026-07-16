<?php
// post.inc.php for noReita (C) sakots 2026 MIT License

const POST_INC_VER = 20260716;

final class PostValidationException extends DomainException {}

final class PostValidator {
  public static function configuredRules(
    bool $en,
    string $request_method,
    string $host,
    array $blocked_hosts,
    string $admin_pass,
    bool $require_comment
  ): array {
    return [
      'en' => $en,
      'request_method' => $request_method,
      'host' => $host,
      'blocked_hosts' => $blocked_hosts,
      'require_name' => (bool)USE_NAME,
      'require_comment' => $require_comment,
      'require_subject' => (bool)USE_SUB,
      'max_comment' => (int)MAX_COM,
      'max_name' => (int)MAX_NAME,
      'max_email' => (int)MAX_EMAIL,
      'max_subject' => (int)MAX_SUB,
      'max_url' => (int)MAX_URL,
      'japanese_filter' => (bool)USE_JAPANESEFILTER,
      'deny_comment_urls' => (bool)DENY_COMMENTS_URL,
      'admin_pass' => $admin_pass,
      'bad_strings' => $GLOBALS['badstring'] ?? [],
      'bad_names' => $GLOBALS['badname'] ?? [],
      'bad_strings_a' => $GLOBALS['badstr_A'] ?? [],
      'bad_strings_b' => $GLOBALS['badstr_B'] ?? [],
    ];
  }

  public static function inputFromHttp(): array {
    return [
      'sub' => (string)filter_input(INPUT_POST, 'sub'),
      'name' => (string)filter_input(INPUT_POST, 'name'),
      'mail' => (string)filter_input(INPUT_POST, 'mail'),
      'url' => (string)filter_input(INPUT_POST, 'url'),
      'com' => (string)filter_input(INPUT_POST, 'com'),
      'picfile' => filter_input(INPUT_POST, 'picfile') ?: null,
      'invz' => trim((string)filter_input(INPUT_POST, 'invz')),
      'img_w' => (int)filter_input(INPUT_POST, 'img_w', FILTER_VALIDATE_INT),
      'img_h' => (int)filter_input(INPUT_POST, 'img_h', FILTER_VALIDATE_INT),
      'pwd' => trim((string)filter_input(INPUT_POST, 'pwd')),
      'sodane' => (int)filter_input(INPUT_POST, 'sodane', FILTER_VALIDATE_INT),
      'pal' => filter_input(INPUT_POST, 'palettes'),
      'nsfw_flag' => (string)filter_input(INPUT_POST, 'nsfw', FILTER_VALIDATE_INT),
      'rep' => (string)filter_input(INPUT_POST, 'rep'),
      'repcode' => (string)filter_input(INPUT_POST, 'repcode'),
      'id' => (string)filter_input(INPUT_POST, 'id'),
      'no' => (string)filter_input(INPUT_POST, 'no'),
      'enc_pwd' => (string)filter_input(INPUT_POST, 'enc_pwd'),
      'modid' => (string)filter_input(INPUT_POST, 'modid'),
      'resto' => (string)filter_input(INPUT_POST, 'resto'),
      'resedit' => trim((string)filter_input(INPUT_POST, 'resedit')),
      'e_no' => trim((string)filter_input(INPUT_POST, 'e_no')),
    ];
  }

  public static function validate(array $input, array $rules): void {
    $en = (bool)($rules['en'] ?? false);
    if (($rules['request_method'] ?? '') !== 'POST') {
      throw new PostValidationException(self::message($en, 'Invalid request method.', '不正なリクエスト方法です。'));
    }

    $com = (string)($input['com'] ?? '');
    $name = (string)($input['name'] ?? '');
    $mail = (string)($input['mail'] ?? '');
    $url = (string)($input['url'] ?? '');
    $sub = (string)($input['sub'] ?? '');
    $resto = (string)($input['resto'] ?? '');
    $pwd = (string)($input['pwd'] ?? '');
    $values = [
      preg_replace('/\s/u', '', $com) ?? '', preg_replace('/\s/u', '', $sub) ?? '',
      preg_replace('/\s/u', '', $name) ?? '', preg_replace('/\s/u', '', $mail) ?? '',
    ];

    if (!empty($rules['japanese_filter']) && $com !== '' && preg_match('/[ぁ-んァ-ヶー一-龠]+/u', $values[0]) !== 1) {
      throw new PostValidationException(self::message($en, 'Your comment must contain Japanese characters.', 'コメントには日本語を含めてください。'));
    }
    if (!empty($rules['deny_comment_urls']) && $pwd !== (string)($rules['admin_pass'] ?? '')
      && preg_match('/:\/\/|\.co|\.ly|\.gl|\.net|\.org|\.cc|\.ru|\.su|\.ua|\.gd/i', $com) === 1) {
      throw new PostValidationException(self::message($en, 'URLs are not allowed in comments.', 'コメントにはURLを含めることはできません。'));
    }
    if (is_ngword($rules['bad_strings'] ?? [], $values)) {
      throw new PostValidationException(self::message($en, 'Invalid characters found in comment.', 'コメントに無効な文字が含まれています。'));
    }
    if (is_ngword($rules['bad_names'] ?? [], $values[2])) {
      throw new PostValidationException(self::message($en, 'Invalid name provided.', '無効な名前が使用されています。'));
    }
    if (is_ngword($rules['bad_strings_a'] ?? [], $values) && is_ngword($rules['bad_strings_b'] ?? [], $values)) {
      throw new PostValidationException(self::message($en, 'Invalid combination of characters found in comment.', 'コメントに無効な文字の組み合わせが含まれています。'));
    }

    if (!empty($rules['require_name']) && $name === '') {
      throw new PostValidationException(self::message($en, 'Name is required.', '名前は必須です。'));
    }
    if (($resto !== '' || !empty($rules['require_comment'])) && $com === '') {
      throw new PostValidationException(self::message($en, 'Comment is required.', '本文は必須です。'));
    }
    if (!empty($rules['require_subject']) && $sub === '') {
      throw new PostValidationException(self::message($en, 'Subject is required.', 'タイトルは必須です。'));
    }

    $lengths = [
      ['com', 'max_comment', 'Comment is too long.', '本文が長すぎます。'],
      ['name', 'max_name', 'Name is too long.', '名前が長すぎます。'],
      ['mail', 'max_email', 'Email is too long.', 'メールアドレスが長すぎます。'],
      ['sub', 'max_subject', 'Subject is too long.', 'タイトルが長すぎます。'],
      ['url', 'max_url', 'URL is too long.', 'URLが長すぎます。'],
    ];
    foreach ($lengths as [$field, $rule, $english, $japanese]) {
      if (strlen((string)($input[$field] ?? '')) > (int)($rules[$rule] ?? PHP_INT_MAX)) {
        throw new PostValidationException(self::message($en, $english, $japanese));
      }
    }

    $host = (string)($rules['host'] ?? '');
    foreach (($rules['blocked_hosts'] ?? []) as $pattern) {
      if ($pattern !== '' && @preg_match('/' . $pattern . '$/i', $host) === 1) {
        throw new PostValidationException(self::message($en, 'Your host is blocked.', 'あなたのホストは拒絶されています。'));
      }
    }
  }

  private static function message(bool $en, string $english, string $japanese): string {
    return $en ? $english : $japanese;
  }
}
