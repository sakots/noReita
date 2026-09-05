<?php
// post.inc.php for noReita (C) sakots 2026 MIT License

const POST_INC_VER = 20260807;

final class PostValidationException extends DomainException {}
final class PostNotFoundException extends RuntimeException {}
final class PostAuthorizationException extends RuntimeException {}
final class DuplicatePostException extends RuntimeException {}

final class DiaryPostPolicy {
  public static function allows(bool $diary_mode, bool $allow_public_replies, bool $is_admin, bool $is_reply): bool {
    return !$diary_mode || $is_admin || ($is_reply && $allow_public_replies);
  }
}

interface AdminPostManagementService {
  /** @param array<int, int|string> $post_ids */
  public function deleteManyAsAdmin(array $post_ids): int;
  /** @param array<int, int|string> $post_ids */
  public function setVisibilityManyAsAdmin(array $post_ids, bool $hidden): int;
}

final class PostService implements AdminPostManagementService {
  private BoardRepository $repository;
  private string $image_dir;
  private int $thumbnail_width;
  private int $file_permission;
  private string $deletion_staging_dir;
  private string $deletion_quarantine_dir;
  private int $deletion_quarantine_retention_days;

  public function __construct(
    BoardRepository $repository,
    string $image_dir,
    int $thumbnail_width = 0,
    int $file_permission = 0600,
    string $deletion_staging_dir = '',
    string $deletion_quarantine_dir = '',
    int $deletion_quarantine_retention_days = 30
  ) {
    $this->repository = $repository;
    $this->image_dir = $image_dir;
    $this->thumbnail_width = $thumbnail_width;
    $this->file_permission = $file_permission;
    $this->deletion_staging_dir = $deletion_staging_dir !== ''
      ? $deletion_staging_dir
      : dirname(rtrim($image_dir, '/\\')) . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'delete-staging';
    $this->deletion_quarantine_dir = $deletion_quarantine_dir !== ''
      ? $deletion_quarantine_dir
      : dirname($this->deletion_staging_dir) . DIRECTORY_SEPARATOR . 'delete-quarantine';
    $this->deletion_quarantine_retention_days = max(1, min(3650, $deletion_quarantine_retention_days));
  }

  /** @return array<string,mixed> */
  public function authorize(int $post_id, string $password, bool $authorize_as_admin = false): array {
    $post = $this->repository->findPost($post_id);
    if (empty($post)) throw new PostNotFoundException('Post was not found.');
    if ($authorize_as_admin) {
      return ['post' => $post, 'role' => 'admin'];
    }
    if (password_verify($password, (string)$post['pwd'])) {
      return ['post' => $post, 'role' => 'owner'];
    }
    throw new PostAuthorizationException('Invalid password.');
  }

  /** @param array<string,mixed> $values */
  public function edit(int $post_id, string $password, array $values, bool $edit_as_admin = false): string {
    $authorization = $this->authorize($post_id, $password, $edit_as_admin);
    $post = $authorization['post'];
    $submitted_name = (string)($values['name'] ?? '');
    $values['name'] = hash_equals((string)$post['a_name'], $submitted_name)
      ? $submitted_name
      : generate_trip($submitted_name);
    $values['pwdh'] = (string)$post['pwd'];
    // 「そうだね」は閲覧者の評価であり、投稿編集・画像差し替えでは変更しない。
    $values['sodane'] = (int)$post['sodane'];
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
    return $authorization['role'];
  }

  public static function nameForEdit(string $stored_name, string $saved_name, bool $is_owner): string {
    if (!$is_owner || $saved_name === '') return $stored_name;
    return hash_equals($stored_name, generate_trip($saved_name)) ? $saved_name : $stored_name;
  }

  public function delete(int $post_id, string $password, bool $delete_as_admin): string {
    $authorization = $this->authorize($post_id, $password, $delete_as_admin);
    // A thread's replies cannot remain visible without its parent. Deleting a
    // parent therefore removes the complete thread, irrespective of whether
    // its owner or an administrator initiated the deletion.
    $with_replies = (int)($authorization['post']['thread'] ?? 0) === 1;
    $posts = $this->repository->findPostsForDeletion($post_id, $with_replies);
    $this->deletePostsAtomically($posts, function () use ($post_id, $with_replies): void {
      $this->repository->deletePost($post_id, $with_replies);
    });
    return 'deleted';
  }

  /**
   * @param array<int, int|string> $post_ids
   */
  public function deleteManyAsAdmin(array $post_ids): int {
    $ids = self::normalizePostIds($post_ids);

    $deleted_ids = [];
    $posts = [];
    $existing_ids = [];
    foreach ($ids as $id) {
      if ($this->repository->findPost($id) === false) continue;
      $existing_ids[] = $id;
      foreach ($this->repository->findPostsForDeletion($id, true) as $post) {
        $deleted_ids[(int)$post['tid']] = true;
        $posts[(int)$post['tid']] = $post;
      }
    }
    if ($deleted_ids === []) throw new PostNotFoundException('Posts were not found.');
    $this->deletePostsAtomically(array_values($posts), function () use ($existing_ids): void {
      foreach ($existing_ids as $id) $this->repository->deletePost($id, true);
    });
    return count($deleted_ids);
  }

  /**
   * @param array<int,array<string,mixed>> $posts
   * @param callable():void $delete_database_rows
   */
  private function deletePostsAtomically(array $posts, callable $delete_database_rows): void {
    $image_names = array_map(static fn(array $post): string => (string)($post['picfile'] ?? ''), $posts);
    $staged = ImageService::stageRelatedFilesForDeletion(
      $this->image_dir, $this->deletion_staging_dir, $image_names, $posts
    );
    try {
      $this->repository->transaction($delete_database_rows);
    } catch (Throwable $e) {
      try {
        ImageService::rollbackStagedDeletion($staged);
      } catch (Throwable $rollback_error) {
        throw new RuntimeException(
          $e->getMessage() . ' Related image rollback also failed: ' . $rollback_error->getMessage(),
          0,
          $e
        );
      }
      throw $e;
    }
    ImageService::completeStagedDeletion($staged);
  }

  /** @return array<string,int> */
  public function recoverInterruptedDeletions(): array {
    return ImageService::recoverStagedDeletions(
      $this->image_dir,
      $this->deletion_staging_dir,
      function (array $posts): bool {
        return $this->repository->hasAnyMatchingPosts($posts);
      },
      $this->deletion_quarantine_dir,
      $this->deletion_quarantine_retention_days
    );
  }

  /** @param array<int, int|string> $post_ids */
  public function setVisibilityManyAsAdmin(array $post_ids, bool $hidden): int {
    $ids = self::normalizePostIds($post_ids);
    $existing = 0;
    foreach ($ids as $id) {
      if ($this->repository->findPost($id) !== false) $existing++;
    }
    if ($existing === 0) throw new PostNotFoundException('Posts were not found.');
    $this->repository->setPostsVisibility(array_values($ids), $hidden);
    return $existing;
  }

  /**
   * @param array<int,int|string> $post_ids
   * @return array<int,int>
   */
  private static function normalizePostIds(array $post_ids): array {
    $ids = [];
    foreach ($post_ids as $post_id) {
      $id = filter_var($post_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
      if ($id !== false) $ids[(int)$id] = (int)$id;
    }
    if ($ids === []) throw new InvalidArgumentException('No posts were selected.');
    return $ids;
  }

  /**
   * @param array<string,mixed> $input
   * @param array<string,mixed> $settings
   * @return array<string,mixed>
   */
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
    $is_admin = !empty($settings['is_admin']);
    if ($name === $admin_name && !$is_admin) {
      $name .= (string)$settings['admin_cap'];
    }
    return array_merge($input, [
      'name' => $name, 'com' => $comment, 'sub' => $subject, 'host' => $host,
      'pwdh' => password_hash($password, PASSWORD_DEFAULT),
      'admins' => ($is_admin && $name === $admin_name) ? 1 : 0,
    ]);
  }

  /**
   * @param array<string,mixed> $post
   * @param array<string,mixed> $image
   */
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

  /** @param array<string,mixed> $sources */
  public static function resolveCtype(array $sources): string {
    return self::firstCtype($sources) ?? 'new';
  }

  /** @param array<string,mixed> $sources */
  private static function firstCtype(array $sources): ?string {
    $direct = self::validCtype($sources['direct'] ?? null);
    if ($direct !== null) return $direct;

    $usercode = self::ctypeFromQuery($sources['usercode'] ?? null);
    if ($usercode !== null) return $usercode;

    $send_header = $sources['send_header'] ?? null;
    if (is_string($send_header) && $send_header !== '') {
      $header_values = [];
      parse_str($send_header, $header_values);
      $header_usercode = $header_values['usercode'] ?? null;
      $ctype = self::ctypeFromQuery(is_string($header_usercode) ? $header_usercode : null);
      if ($ctype !== null) return $ctype;
    }

    $http_ctype = self::ctypeFromQuery($sources['http_usercode'] ?? null);
    if ($http_ctype !== null) return $http_ctype;
    return self::ctypeFromQuery($sources['session_usercode'] ?? null);
  }

  /** @param mixed $query */
  private static function ctypeFromQuery($query): ?string {
    if (!is_string($query) || $query === '') return null;
    $values = [];
    parse_str($query, $values);
    return self::validCtype($values['ctype'] ?? null);
  }

  /** @param mixed $ctype */
  private static function validCtype($ctype): ?string {
    return is_string($ctype) && in_array($ctype, self::CTYPES, true) ? $ctype : null;
  }
}

final class PostValidator {
  /**
   * @param array<int,string> $blocked_hosts
   * @return array<string,mixed>
   */
  public static function configuredRules(
    bool $en,
    string $request_method,
    string $host,
    array $blocked_hosts,
    bool $is_admin,
    bool $require_comment
  ): array {
    return [
      'en' => $en,
      'request_method' => $request_method,
      'host' => $host,
      'blocked_hosts' => $blocked_hosts,
      'require_name' => Config::bool('features.require_name'),
      'require_comment' => $require_comment,
      'require_subject' => Config::bool('features.require_subject'),
      'max_comment' => Config::int('limits.comment_length'),
      'max_name' => Config::int('limits.name_length'),
      'max_email' => Config::int('limits.email_length'),
      'max_subject' => Config::int('limits.subject_length'),
      'max_url' => Config::int('limits.url_length'),
      'japanese_filter' => Config::bool('features.japanese_filter'),
      'deny_comment_urls' => Config::bool('features.deny_comment_urls'),
      'is_admin' => $is_admin,
      'bad_strings' => Config::array('spam.bad_strings'),
      'bad_names' => Config::array('spam.bad_names'),
      'bad_strings_a' => Config::array('spam.bad_strings_a'),
      'bad_strings_b' => Config::array('spam.bad_strings_b'),
      'comment_score_rules' => Config::array('spam.comment_score_rules'),
      'comment_score_threshold' => Config::int('spam.comment_score_threshold'),
    ];
  }

  /** @return array<string,mixed> */
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
      'modid' => (string)filter_input(INPUT_POST, 'modid'),
      'resto' => (string)filter_input(INPUT_POST, 'resto'),
      'resedit' => trim((string)filter_input(INPUT_POST, 'resedit')),
      'e_no' => trim((string)filter_input(INPUT_POST, 'e_no')),
    ];
  }

  /**
   * @param array<string,mixed> $input
   * @param array<string,mixed> $rules
   */
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
    // UTF-8正規表現のエラーを「一致なし」と扱ってスパム判定を回避させない。
    foreach ([$com, $name, $mail, $url, $sub] as $text) {
      if (preg_match('//u', $text) !== 1) {
        throw new PostValidationException(self::message(
          $en, 'Input contains invalid character encoding.', '入力に不正な文字コードが含まれています。'
        ));
      }
    }
    $values = [
      preg_replace('/\s/u', '', $com) ?? '', preg_replace('/\s/u', '', $sub) ?? '',
      preg_replace('/\s/u', '', $name) ?? '', preg_replace('/\s/u', '', $mail) ?? '',
    ];

    if (!empty($rules['japanese_filter']) && $com !== '' && preg_match('/[ぁ-んァ-ヶー一-龠]+/u', $values[0]) !== 1) {
      throw new PostValidationException(self::message($en, 'Your comment must contain Japanese characters.', 'コメントには日本語を含めてください。'));
    }
    if (!empty($rules['deny_comment_urls']) && empty($rules['is_admin'])
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
    $score_threshold = (int)($rules['comment_score_threshold'] ?? 0);
    if ($score_threshold > 0
      && self::commentRejectionScore($com, $rules['comment_score_rules'] ?? [], $score_threshold) >= $score_threshold) {
      throw new PostValidationException(self::message($en, 'Your comment was rejected as spam.', 'コメントはスパムとして拒否されました。'));
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

  /** @param array<int, array{0:string, 1:int}> $rules */
  private static function commentRejectionScore(string $comment, array $rules, int $threshold): int {
    $score = 0;
    foreach ($rules as $rule) {
      if (preg_match('/' . $rule[0] . '/ui', $comment) === 1) {
        // 加算前に残り点数と比較し、整数オーバーフローを防ぐ。
        if ($rule[1] >= $threshold - $score) return $threshold;
        $score += $rule[1];
      }
    }
    return $score;
  }

  private static function message(bool $en, string $english, string $japanese): string {
    return $en ? $english : $japanese;
  }
}
