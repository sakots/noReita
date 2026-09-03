<?php
// Public JSON API for React and other same-origin clients.

final class PublicApiException extends RuntimeException {
  private int $status;

  public function __construct(string $message, int $status = 400) {
    parent::__construct($message);
    $this->status = $status;
  }

  public function status(): int {
    return $this->status;
  }
}

final class PublicApi {
  private const VERSION = 'v1';
  private const MAX_PAGE_SIZE = 100;

  /** @param array<string,mixed> $query
   * @return array<string,mixed> */
  public static function dispatch(BoardRepository $repository, array $query): array {
    $mode = self::stringValue($query['mode'] ?? 'threads');
    return match ($mode) {
      'threads' => self::threads($repository, $query),
      'thread' => self::thread($repository, $query),
      'catalog' => self::catalog($repository, $query),
      'search' => self::search($repository, $query),
      default => throw new PublicApiException('Unknown API mode.', 404),
    };
  }

  /** @param array<string,mixed> $query
   * @return array<string,mixed> */
  private static function threads(BoardRepository $repository, array $query): array {
    $page_size = self::pageSize($query, Config::int('board.page_size'));
    $pagination = self::pagination($repository->countThreads(true), $query, $page_size);
    $posts = $repository->listThreads($pagination['offset'], $page_size);
    return self::collection('threads', array_map([self::class, 'post'], $posts), $pagination);
  }

  /** @param array<string,mixed> $query
   * @return array<string,mixed> */
  private static function thread(BoardRepository $repository, array $query): array {
    $id = self::positiveInt($query['id'] ?? null, 'id');
    $thread = $repository->findPost($id);
    if (!is_array($thread) || (int)($thread['invz'] ?? 0) !== 0 || (int)($thread['thread'] ?? 0) !== 1) {
      throw new PublicApiException('Thread not found.', 404);
    }
    return [
      'api_version' => self::VERSION,
      'mode' => 'thread',
      'thread' => self::post($thread),
      'replies' => array_map([self::class, 'post'], $repository->findReplies($id)),
    ];
  }

  /** @param array<string,mixed> $query
   * @return array<string,mixed> */
  private static function catalog(BoardRepository $repository, array $query): array {
    $page_size = self::pageSize($query, Config::int('board.catalog_size'));
    $pagination = self::pagination($repository->countVisibleImages(), $query, $page_size);
    $posts = $repository->listCatalog($pagination['offset'], $page_size);
    return self::collection('catalog', array_map([self::class, 'post'], $posts), $pagination);
  }

  /** @param array<string,mixed> $query
   * @return array<string,mixed> */
  private static function search(BoardRepository $repository, array $query): array {
    $criteria = PublicPostSearch::normalize([
      'query' => $query['q'] ?? $query['search'] ?? '',
      'target' => $query['target'] ?? null,
      'match' => $query['match'] ?? null,
      'post_type' => $query['post_type'] ?? null,
      'image' => $query['image'] ?? null,
      'nsfw' => $query['nsfw'] ?? null,
      'sort' => $query['sort'] ?? null,
    ]);
    $page_size = self::pageSize($query, Config::int('board.catalog_size'));
    $pagination = self::pagination($repository->countPublicSearch($criteria), $query, $page_size);
    $posts = $repository->searchVisiblePosts($criteria, $pagination['offset'], $page_size);
    $response = self::collection('search', array_map([self::class, 'post'], $posts), $pagination);
    $response['criteria'] = $criteria;
    return $response;
  }

  /** @param array<int,array<string,mixed>> $items
   * @param array{page:int,per_page:int,total:int,total_pages:int,offset:int} $pagination
   * @return array<string,mixed> */
  private static function collection(string $mode, array $items, array $pagination): array {
    unset($pagination['offset']);
    return ['api_version' => self::VERSION, 'mode' => $mode, 'items' => $items, 'pagination' => $pagination];
  }

  /** @return array<string,mixed> */
  private static function post(array $post): array {
    $id = (int)($post['tid'] ?? 0);
    $image_name = basename((string)($post['picfile'] ?? ''));
    $thumbnail_name = basename((string)($post['thumbnail'] ?? ''));
    $image_url = $image_name === '' ? null : self::imageUrl($image_name);
    $thumbnail_url = $thumbnail_name !== '' ? self::imageUrl($thumbnail_name) : $image_url;
    return [
      'id' => $id,
      'thread_id' => (int)($post['thread'] ?? 0) === 1 ? $id : (int)($post['parent'] ?? 0),
      'post_type' => (int)($post['thread'] ?? 0) === 1 ? 'thread' : 'reply',
      'author' => (string)($post['a_name'] ?? ''),
      'subject' => (string)($post['sub'] ?? ''),
      'comment' => (string)($post['com'] ?? ''),
      'url' => self::threadUrl((int)($post['thread'] ?? 0) === 1 ? $id : (int)($post['parent'] ?? 0)),
      'created_at' => (string)($post['created'] ?? ''),
      'modified_at' => (string)($post['modified'] ?? ''),
      'sodane' => (int)($post['sodane'] ?? 0),
      'image' => $image_url === null ? null : [
        'url' => $image_url,
        'thumbnail_url' => $thumbnail_url,
        'width' => (int)($post['img_w'] ?? 0),
        'height' => (int)($post['img_h'] ?? 0),
        'nsfw' => (bool)($post['nsfw'] ?? false),
      ],
    ];
  }

  /** @param array<string,mixed> $query
   * @return array{page:int,per_page:int,total:int,total_pages:int,offset:int} */
  private static function pagination(int $total, array $query, int $page_size): array {
    $total = max(0, $total);
    $total_pages = max(1, (int)ceil($total / $page_size));
    $page = self::positiveInt($query['page'] ?? 1, 'page');
    $page = min($page, $total_pages);
    return ['page' => $page, 'per_page' => $page_size, 'total' => $total,
      'total_pages' => $total_pages, 'offset' => ($page - 1) * $page_size];
  }

  /** @param array<string,mixed> $query */
  private static function pageSize(array $query, int $default): int {
    $requested = $query['per_page'] ?? $default;
    return min(self::positiveInt($requested, 'per_page'), self::MAX_PAGE_SIZE);
  }

  /** @param mixed $value */
  private static function positiveInt($value, string $name): int {
    if (is_int($value)) $number = $value;
    elseif (is_string($value) && preg_match('/\A[1-9][0-9]*\z/D', $value) === 1) $number = (int)$value;
    else throw new PublicApiException("Invalid {$name}.");
    if ($number < 1) throw new PublicApiException("Invalid {$name}.");
    return $number;
  }

  /** @param mixed $value */
  private static function stringValue($value): string {
    return is_string($value) ? $value : '';
  }

  private static function imageUrl(string $filename): string {
    return Config::string('site.base_url') . Config::string('paths.images') . rawurlencode($filename);
  }

  private static function threadUrl(int $id): string {
    return Config::string('site.base_url') . Config::string('site.script_name') . '?resno=' . $id;
  }
}
