<?php
require_once __DIR__ . '/request_security.inc.php';
require_once __DIR__ . '/database.inc.php';
require_once __DIR__ . '/post.inc.php';

/** Misskey連携で使う投稿認可を、フォーム値と切り離して管理する。 */
final class MisskeyPostAuthorization {
  private const SESSION_KEY = 'misskey_authorized_post';

  public static function assertFeatureEnabled(ApplicationContext $context): void {
    if (!Config::bool('features.misskey_note')) {
      render_error($context, $context->english ? 'Misskey sharing is disabled.' : 'Misskey連携は無効です。', 404);
    }
  }

  public static function isAdministrator(): bool {
    return AdminAuth::isAuthenticated(
      Config::string('admin.password'), Config::int('admin.session_lifetime')
    );
  }

  /** @return array{post: array<string,mixed>, role: string}|null */
  public static function authorize(int $post_id, string $password): ?array {
    try {
      $service = new PostService(new BoardRepository(), Config::string('paths.images'));
      return $service->authorize($post_id, $password, self::isAdministrator());
    } catch (PostNotFoundException|PostAuthorizationException) {
      return null;
    }
  }

  /** @param array{post: array<string,mixed>, role: string} $authorization */
  public static function remember(array $authorization, string $usercode): void {
    RequestSecurity::startSession();
    $post = $authorization['post'];
    // 別の投稿の認可で、以前の送信待ちデータを引き継がない。
    unset($_SESSION['misskey_note_data'], $_SESSION['sns_api_val'], $_SESSION['sns_api_session_id']);
    $_SESSION[self::SESSION_KEY] = [
      'tid' => (int)$post['tid'],
      'picfile' => (string)$post['picfile'],
      'usercode' => $usercode,
      'post_state' => self::postState($post),
      'role' => $authorization['role'],
    ];
  }

  /** @return array<string,mixed>|null */
  public static function authorizedPost(int $post_id, string $usercode): ?array {
    RequestSecurity::startSession();
    $grant = $_SESSION[self::SESSION_KEY] ?? null;
    // 管理者による認可は、現在も有効な管理者セッションに限る。
    if (!is_array($grant) || !in_array($grant['role'] ?? null, ['admin', 'owner'], true)
      || ($grant['role'] === 'admin' && !self::isAdministrator())) {
      return null;
    }
    if ($usercode === '' || (int)($grant['tid'] ?? 0) !== $post_id
      || !is_string($grant['usercode'] ?? null)
      || !hash_equals($usercode, $grant['usercode'])) {
      return null;
    }
    $post = (new BoardRepository())->findPost($post_id);
    if (!is_array($post) || !hash_equals((string)($grant['picfile'] ?? ''), (string)$post['picfile'])
      || !hash_equals((string)($grant['post_state'] ?? ''), self::postState($post))) {
      self::forget();
      return null;
    }
    return $post;
  }

  /** 認可時から共有対象の内容が変わっていないことを確認する。そうだねの加算は対象外。 */
  private static function postState(array $post): string {
    $values = [];
    foreach (['picfile', 'pwd', 'invz', 'nsfw', 'modified', 'sub', 'com', 'image_alt',
      'tool', 'psec', 'img_w', 'img_h'] as $key) {
      $values[] = (string)($post[$key] ?? '');
    }
    return hash('sha256', serialize($values));
  }

  public static function forget(): void {
    RequestSecurity::startSession();
    unset($_SESSION[self::SESSION_KEY], $_SESSION['misskey_note_data'],
      $_SESSION['sns_api_val'], $_SESSION['sns_api_session_id']);
  }
}
