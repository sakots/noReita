<?php
// post.inc.php for noReita (C) sakots 2026 MIT License

const POST_INC_VER = 20260722;

final class PostValidationException extends DomainException {}
final class PostNotFoundException extends RuntimeException {}
final class PostAuthorizationException extends RuntimeException {}
final class DuplicatePostException extends RuntimeException {}

final class PostService {
  public function __construct(
    private BoardRepository $repository,
    private string $admin_pass,
    private string $image_dir,
    private int $thumbnail_width = 0,
    private int $file_permission = 0600,
  ) {}

  public function authorize(int $post_id, string $password): array {
    $post = $this->repository->findPost($post_id);
    if (empty($post)) throw new PostNotFoundException('Post was not found.');
    if (password_verify($password, (string)$post['pwd'])) {
      return ['post' => $post, 'role' => 'owner'];
    }
    if ($this->admin_pass !== '' && hash_equals($this->admin_pass, $password)) {
      return ['post' => $post, 'role' => 'admin'];
    }
    throw new PostAuthorizationException('Invalid password.');
  }

  public function edit(int $post_id, string $password, array $values): void {
    $authorization = $this->authorize($post_id, $password);
    $post = $authorization['post'];
    $values['pwdh'] = (string)$post['pwd'];
    $values['nsfw'] = (int)$post['nsfw'];
    $values['thumbnail'] = (string)($post['thumbnail'] ?? '');
    if (array_key_exists('edit_nsfw', $values) && (string)$post['picfile'] !== '') {
      $nsfw = (bool)$values['edit_nsfw'];
      if ($nsfw !== (bool)$post['nsfw']) {
        $values['thumbnail'] = ImageService::refreshNsfwThumbnail(
          $this->image_dir, (string)$post['picfile'], $values['thumbnail'], $nsfw,
          $this->thumbnail_width, $this->file_permission
        );
      }
      $values['nsfw'] = (int)$nsfw;
    }
    $this->repository->updateContent($post_id, $values);
  }

  public function delete(int $post_id, string $password, bool $delete_as_admin): string {
    $authorization = $this->authorize($post_id, $password);
    if ($authorization['role'] === 'admin' && !$delete_as_admin) {
      $this->repository->hidePost($post_id);
      return 'hidden';
    }
    ImageService::deleteRelatedFiles($this->image_dir, (string)$authorization['post']['picfile']);
    $this->repository->deletePost($post_id, $authorization['role'] === 'admin');
    return 'deleted';
  }

  public function prepareNewPost(array $input, string $host, array $settings): array {
    $comment_was_present = (string)($input['com'] ?? '') !== '';
    $name = generate_trip((string)($input['name'] ?? ''));
    $name = $name !== '' ? $name : (string)$settings['default_name'];
    $comment = (string)($input['com'] ?? '');
    $comment = $comment !== '' ? $comment : (string)$settings['default_comment'];
    $subject = (string)($input['sub'] ?? '');
    $subject = $subject !== '' ? $subject : (string)$settings['default_subject'];

    $latest = $this->repository->latestThread();
    if (!empty($latest)) {
      $same_text = $comment_was_present && $comment === (string)$latest['com']
        && $host === (string)$latest['host'] && $subject === (string)$latest['sub'];
      $same_image = (string)($input['modid'] ?? '') !== '' && (string)$latest['picfile'] !== ''
        && (string)($input['picfile'] ?? '') === (string)$latest['picfile'];
      if ($same_text || $same_image) throw new DuplicatePostException('Duplicate post.');
    }

    $password = (string)($input['pwd'] ?? '');
    $admin_name = (string)$settings['admin_name'];
    if ($name === $admin_name && $password !== $this->admin_pass) {
      $name .= (string)$settings['admin_cap'];
    }
    return array_merge($input, [
      'name' => $name, 'com' => $comment, 'sub' => $subject, 'host' => $host,
      'pwdh' => password_hash($password, PASSWORD_DEFAULT),
      'admins' => ($password === $this->admin_pass && $name === $admin_name) ? 1 : 0,
    ]);
  }

  public function createPreparedPost(array $post, array $image): int {
    $now = time();
    $resto = (string)($post['resto'] ?? '');
    $thread = $resto === '' ? 1 : 0;
    $parent = $thread === 1 ? null : (int)$resto;
    $tree = $now * 100000000;
    $comid = null;
    $age = 0;

    if ($parent !== null) {
      $parent_post = $this->repository->findPost($parent);
      if (empty($parent_post)) throw new PostNotFoundException('Parent post was not found.');
      $tree = $now - $parent - (int)$parent_post['tid'];
      $comid = $tree + $now;
      $age = (int)$parent_post['age'];
      if (!str_contains((string)($post['mail'] ?? ''), 'sage')) {
        $age++;
        $this->repository->bumpThread($parent, $age, $age + ($now * 100000000));
      }
    }

    return $this->repository->insertPost([
      'thread' => $thread, 'parent' => $parent, 'comid' => $comid, 'tree' => $tree,
      'a_name' => $post['name'], 'sub' => $post['sub'],
      'com' => preg_replace('/(\n|\r|\r\n){3,}/us', "\n\n", (string)$post['com']),
      'mail' => $post['mail'], 'a_url' => $post['url'], 'picfile' => $post['picfile'],
      'pchfile' => $image['pchfile'], 'img_w' => $image['img_w'], 'img_h' => $image['img_h'],
      'psec' => $image['psec'], 'utime' => $image['utime'], 'pwd' => $post['pwdh'],
      'id' => gen_id((string)$post['host'], (string)$now), 'sodane' => $post['sodane'],
      'age' => $age, 'invz' => $post['invz'], 'host' => $post['host'], 'tool' => $image['tool'],
      'admins' => $post['admins'], 'shd' => 0, 'nsfw' => $image['nsfw'], 'ctype' => $image['ctype'],
      'uuid' => generate_uuid(), 'thumbnail' => $image['thumbnail'],
    ]);
  }
}

final class PostInput {
  private const CTYPES = ['new', 'img', 'pch', 'spch'];

  public static function ctypeFromHttp(): string {
    $sources = [
      'direct' => filter_input(INPUT_POST, 'ctype'),
      'usercode' => filter_input(INPUT_POST, 'usercode'),
      'send_header' => filter_input(INPUT_POST, 'send_header'),
      'http_usercode' => filter_input(INPUT_SERVER, 'HTTP_X_USERCODE'),
    ];
    $ctype = self::firstCtype($sources);
    if ($ctype !== null) return $ctype;

    RequestSecurity::startSession();
    return self::resolveCtype(['session_usercode' => $_SESSION['usercode'] ?? null]);
  }

  public static function resolveCtype(array $sources): string {
    return self::firstCtype($sources) ?? 'new';
  }

  private static function firstCtype(array $sources): ?string {
    $direct = self::validCtype($sources['direct'] ?? null);
    if ($direct !== null) return $direct;

    $usercode = self::ctypeFromQuery($sources['usercode'] ?? null);
    if ($usercode !== null) return $usercode;

    $send_header = $sources['send_header'] ?? null;
    if (is_string($send_header) && $send_header !== '') {
      parse_str($send_header, $header_values);
      $header_usercode = $header_values['usercode'] ?? null;
      $ctype = self::ctypeFromQuery(is_string($header_usercode) ? $header_usercode : null);
      if ($ctype !== null) return $ctype;
    }

    $http_ctype = self::ctypeFromQuery($sources['http_usercode'] ?? null);
    if ($http_ctype !== null) return $http_ctype;
    return self::ctypeFromQuery($sources['session_usercode'] ?? null);
  }

  private static function ctypeFromQuery(mixed $query): ?string {
    if (!is_string($query) || $query === '') return null;
    parse_str($query, $values);
    return self::validCtype($values['ctype'] ?? null);
  }

  private static function validCtype(mixed $ctype): ?string {
    return is_string($ctype) && in_array($ctype, self::CTYPES, true) ? $ctype : null;
  }
}

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
        throw new PostValidationException(
          self::message($en, 'Your host is blocked.', 'あなたのホストは拒絶されています。'),
          403
        );
      }
    }
  }

  private static function message(bool $en, string $english, string $japanese): string {
    return $en ? $english : $japanese;
  }
}
