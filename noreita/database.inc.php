<?php
// database.inc.php for noReita (C) sakots 2026 MIT License

require_once __DIR__ . '/filesystem_permissions.inc.php';

const DATABASE_INC_VER = 20260817;

final class AdminPostFilter {
  private const ENUMS = [
    'type' => ['all', 'thread', 'reply'],
    'image' => ['all', 'with', 'without'],
    'nsfw' => ['all', 'yes', 'no'],
    'visibility' => ['all', 'visible', 'hidden'],
    'isAdministrator' => ['all', 'yes', 'no'],
  ];

  public static function normalize(array $input): array {
    $filters = [
      'id' => '', 'q' => '', 'name' => '', 'host' => '', 'date_from' => '', 'date_to' => '',
      'type' => 'all', 'image' => 'all', 'nsfw' => 'all',
      'visibility' => 'all', 'isAdministrator' => 'all',
    ];
    foreach (['q', 'name', 'host'] as $key) {
      $value = trim((string)($input[$key] ?? ''));
      if (mb_strlen($value) > 200) throw new InvalidArgumentException("{$key} is too long.");
      $filters[$key] = $value;
    }
    $id = trim((string)($input['id'] ?? ''));
    if ($id !== '') {
      $validated = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
      if ($validated === false) throw new InvalidArgumentException('Invalid post ID.');
      $filters['id'] = (string)$validated;
    }
    foreach (['date_from', 'date_to'] as $key) {
      $value = trim((string)($input[$key] ?? ''));
      if ($value !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
          throw new InvalidArgumentException("Invalid {$key}.");
        }
      }
      $filters[$key] = $value;
    }
    if ($filters['date_from'] !== '' && $filters['date_to'] !== ''
      && $filters['date_from'] > $filters['date_to']) {
      throw new InvalidArgumentException('Invalid date range.');
    }
    foreach (self::ENUMS as $key => $allowed) {
      $value = (string)($input[$key] ?? 'all');
      if (!in_array($value, $allowed, true)) throw new InvalidArgumentException("Invalid {$key} filter.");
      $filters[$key] = $value;
    }
    return $filters;
  }

  public static function isActive(array $filters): bool {
    foreach ($filters as $key => $value) {
      if ($value !== '' && (!isset(self::ENUMS[$key]) || $value !== 'all')) return true;
    }
    return false;
  }

  /** @return array{sql:string,params:array<string,mixed>} */
  public static function rowCondition(array $filters, string $alias, string $prefix, bool $include_type = true): array {
    $conditions = [];
    $params = [];
    if ($filters['id'] !== '') {
      $conditions[] = "{$alias}.tid = :{$prefix}id";
      $params["{$prefix}id"] = (int)$filters['id'];
    }
    if ($filters['q'] !== '') {
      $conditions[] = "({$alias}.sub LIKE :{$prefix}q ESCAPE '\\' OR {$alias}.com LIKE :{$prefix}q ESCAPE '\\')";
      $params["{$prefix}q"] = '%' . self::escapeLike($filters['q']) . '%';
    }
    foreach (['name' => 'a_name', 'host' => 'host'] as $key => $column) {
      if ($filters[$key] === '') continue;
      $conditions[] = "{$alias}.{$column} LIKE :{$prefix}{$key} ESCAPE '\\'";
      $params["{$prefix}{$key}"] = '%' . self::escapeLike($filters[$key]) . '%';
    }
    if ($filters['date_from'] !== '') {
      $conditions[] = "date({$alias}.created) >= :{$prefix}date_from";
      $params["{$prefix}date_from"] = $filters['date_from'];
    }
    if ($filters['date_to'] !== '') {
      $conditions[] = "date({$alias}.created) <= :{$prefix}date_to";
      $params["{$prefix}date_to"] = $filters['date_to'];
    }
    if ($include_type && $filters['type'] !== 'all') {
      $conditions[] = "{$alias}.thread = :{$prefix}thread";
      $params["{$prefix}thread"] = $filters['type'] === 'thread' ? 1 : 0;
    }
    if ($filters['image'] !== 'all') {
      $conditions[] = $filters['image'] === 'with'
        ? "COALESCE({$alias}.picfile, '') != ''"
        : "COALESCE({$alias}.picfile, '') = ''";
    }
    foreach (['nsfw' => 'nsfw', 'visibility' => 'invz', 'isAdministrator' => 'admins'] as $key => $column) {
      if ($filters[$key] === 'all') continue;
      $conditions[] = "CAST(COALESCE({$alias}.{$column}, 0) AS INTEGER) = :{$prefix}{$key}";
      $params["{$prefix}{$key}"] = $filters[$key] === 'yes' || $filters[$key] === 'hidden' ? 1 : 0;
    }
    return ['sql' => $conditions === [] ? '1=1' : implode(' AND ', $conditions), 'params' => $params];
  }

  /** @return array{sql:string,params:array<string,mixed>} */
  public static function threadCondition(array $filters, string $alias = 't'): array {
    $thread_filters = self::rowCondition($filters, $alias, 'thread_', false);
    $reply_filters = self::rowCondition($filters, 'r', 'reply_', false);
    $exists = "EXISTS (SELECT 1 FROM board_log r WHERE r.thread=0 AND r.parent={$alias}.tid AND {$reply_filters['sql']})";
    if ($filters['type'] === 'thread') {
      return ['sql' => "{$alias}.thread=1 AND {$thread_filters['sql']}", 'params' => $thread_filters['params']];
    }
    if ($filters['type'] === 'reply') {
      return ['sql' => "{$alias}.thread=1 AND {$exists}", 'params' => $reply_filters['params']];
    }
    return [
      'sql' => "{$alias}.thread=1 AND (({$thread_filters['sql']}) OR {$exists})",
      'params' => $thread_filters['params'] + $reply_filters['params'],
    ];
  }

  public static function matches(array $post, array $filters): bool {
    if ($filters['id'] !== '' && (int)$post['tid'] !== (int)$filters['id']) return false;
    if ($filters['q'] !== '' && !self::contains((string)$post['sub'], $filters['q'])
      && !self::contains((string)$post['com'], $filters['q'])) return false;
    if ($filters['name'] !== '' && !self::contains((string)$post['a_name'], $filters['name'])) return false;
    if ($filters['host'] !== '' && !self::contains((string)$post['host'], $filters['host'])) return false;
    $created = substr((string)$post['created'], 0, 10);
    if ($filters['date_from'] !== '' && $created < $filters['date_from']) return false;
    if ($filters['date_to'] !== '' && $created > $filters['date_to']) return false;
    if ($filters['type'] === 'thread' && (int)$post['thread'] !== 1) return false;
    if ($filters['type'] === 'reply' && (int)$post['thread'] !== 0) return false;
    $has_image = (string)$post['picfile'] !== '';
    if (($filters['image'] === 'with' && !$has_image)
      || ($filters['image'] === 'without' && $has_image)) return false;
    foreach (['nsfw' => 'nsfw', 'visibility' => 'invz', 'isAdministrator' => 'admins'] as $key => $column) {
      if ($filters[$key] === 'all') continue;
      $expected = $filters[$key] === 'yes' || $filters[$key] === 'hidden' ? 1 : 0;
      if ((int)$post[$column] !== $expected) return false;
    }
    return true;
  }

  public static function query(array $filters): string {
    $values = array_filter($filters, static fn(string $value): bool => $value !== '' && $value !== 'all');
    return http_build_query($values, '', '&', PHP_QUERY_RFC3986);
  }

  private static function escapeLike(string $value): string {
    return strtr($value, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']);
  }

  private static function contains(string $haystack, string $needle): bool {
    return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
  }
}

final class Database {
  private const DEFAULT_BUSY_TIMEOUT_MS = 5000;
  private const MAX_BUSY_TIMEOUT_MS = 60000;

  public static function connect(?string $dsn = null, ?int $busy_timeout_ms = null): PDO {
    $db = new PDO($dsn ?? DB_PDO);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
      $timeout = $busy_timeout_ms ?? self::configuredBusyTimeout();
      if ($timeout < 0 || $timeout > self::MAX_BUSY_TIMEOUT_MS) {
        throw new InvalidArgumentException('SQLite busy timeout must be between 0 and 60000 milliseconds.');
      }
      $db->exec('PRAGMA busy_timeout = ' . $timeout);
      $db->exec('PRAGMA journal_mode=WAL;');
    }
    return $db;
  }

  private static function configuredBusyTimeout(): int {
    return Config::int('database.busy_timeout');
  }
}

final class PublicPostSearch {
  /** @return array<string,string> */
  public static function normalize(array $values): array {
    $query = trim((string)($values['query'] ?? ''));
    if (mb_strlen($query, 'UTF-8') > 100) {
      throw new InvalidArgumentException('Search query must not exceed 100 characters.');
    }
    return [
      'query' => $query,
      'target' => self::choice($values['target'] ?? null, ['author', 'subject', 'comment', 'all'], 'author'),
      'match' => self::choice($values['match'] ?? null, ['exact', 'partial'], 'partial'),
      'post_type' => self::choice($values['post_type'] ?? null, ['all', 'thread', 'reply'], 'all'),
      'image' => self::choice($values['image'] ?? null, ['any', 'with', 'without'], 'any'),
      'nsfw' => self::choice($values['nsfw'] ?? null, ['any', 'safe', 'nsfw'], 'any'),
      'sort' => self::choice($values['sort'] ?? null, ['newest', 'oldest'], 'newest'),
    ];
  }

  /** @param array<string,string> $criteria */
  public static function queryString(array $criteria): string {
    return http_build_query([
      'target' => $criteria['target'], 'match' => $criteria['match'], 'post_type' => $criteria['post_type'],
      'image' => $criteria['image'], 'nsfw' => $criteria['nsfw'], 'sort' => $criteria['sort'],
      'search' => $criteria['query'],
    ], '', '&', PHP_QUERY_RFC3986);
  }

  /** @param array<string,string> $criteria */
  public static function label(array $criteria): string {
    $targets = ['author' => '作者名', 'subject' => '題名', 'comment' => '本文', 'all' => 'すべて'];
    return $targets[$criteria['target']] ?? '検索';
  }

  /** @param mixed $value @param array<int,string> $allowed */
  private static function choice($value, array $allowed, string $default): string {
    return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
  }
}

final class BoardRepository {
  private PDO $db;

  public function __construct(?PDO $db = null) {
    $this->db = $db ?? Database::connect();
  }

  /** @return array|false */
  public function findPost(int $id) {
    $statement = $this->db->prepare('SELECT * FROM board_log WHERE tid = ?');
    $statement->execute([$id]);
    return $statement->fetch(PDO::FETCH_ASSOC);
  }

  public function searchComments(string $query): array {
    $statement = $this->db->prepare('SELECT * FROM board_log WHERE com LIKE ? AND invz=0 ORDER BY age DESC, tree DESC');
    $statement->execute(['%' . $query . '%']);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }

  public function searchAuthors(string $query, bool $partial = false): array {
    $statement = $this->db->prepare('SELECT * FROM board_log WHERE a_name LIKE ? AND invz=0 AND picfile > 0 ORDER BY age DESC, tree DESC');
    $statement->execute([$partial ? '%' . $query . '%' : $query]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }

  public function deletePost(int $id, bool $with_replies = false): void {
    $sql = $with_replies
      ? 'DELETE FROM board_log WHERE tid = ? OR parent = ?'
      : 'DELETE FROM board_log WHERE tid = ?';
    $statement = $this->db->prepare($sql);
    $statement->execute($with_replies ? [$id, $id] : [$id]);
  }

  public function findPostsForDeletion(int $id, bool $with_replies): array {
    $sql = $with_replies
      ? 'SELECT * FROM board_log WHERE tid = ? OR parent = ? ORDER BY thread DESC, tid ASC'
      : 'SELECT * FROM board_log WHERE tid = ?';
    $statement = $this->db->prepare($sql);
    $statement->execute($with_replies ? [$id, $id] : [$id]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }

  public function hasAnyMatchingPosts(array $posts): bool {
    $statement = $this->db->prepare('SELECT 1 FROM board_log WHERE tid = ? AND picfile = ? LIMIT 1');
    foreach ($posts as $post) {
      if (!is_array($post)) continue;
      $statement->execute([(int)($post['id'] ?? 0), (string)($post['picfile'] ?? '')]);
      if ($statement->fetchColumn() !== false) return true;
    }
    return false;
  }

  public function hidePost(int $id): void {
    $statement = $this->db->prepare('UPDATE board_log SET invz=1 WHERE tid = ?');
    $statement->execute([$id]);
  }

  public function setPostsVisibility(array $ids, bool $hidden): int {
    if ($ids === []) return 0;
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = $this->db->prepare(
      "UPDATE board_log SET invz = ? WHERE tid IN ({$placeholders}) AND CAST(COALESCE(invz, 0) AS INTEGER) != ?"
    );
    $value = $hidden ? 1 : 0;
    $statement->execute([$value, ...$ids, $value]);
    return $statement->rowCount();
  }

  /** @return mixed */
  public function transaction(callable $operation) {
    if ($this->db->inTransaction()) return $operation();
    $this->db->beginTransaction();
    try {
      $result = $operation();
      $this->db->commit();
      return $result;
    } catch (Throwable $e) {
      if ($this->db->inTransaction()) $this->db->rollBack();
      throw $e;
    }
  }

  public function findThreadIdByUuid(string $uuid): ?int {
    $statement = $this->db->prepare('SELECT tid, parent, thread FROM board_log WHERE uuid = ? AND invz = 0 LIMIT 1');
    $statement->execute([$uuid]);
    $post = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$post) return null;
    return (int)$post['thread'] === 1 ? (int)$post['tid'] : (int)$post['parent'];
  }

  public function findReplies(int $parent): array {
    $statement = $this->db->prepare('SELECT * FROM board_log WHERE parent = ? AND invz = 0 ORDER BY comid ASC');
    $statement->execute([$parent]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }

  public function findRepliesForAdmin(int $parent): array {
    $statement = $this->db->prepare('SELECT * FROM board_log WHERE parent = ? ORDER BY comid ASC');
    $statement->execute([$parent]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }

  public function incrementSodane(int $id): int {
    $statement = $this->db->prepare('UPDATE board_log SET sodane = CAST((CAST(sodane AS INTEGER) + 1) AS TEXT) WHERE tid = ?');
    $statement->execute([$id]);
    $statement = $this->db->prepare('SELECT CAST(sodane AS INTEGER) FROM board_log WHERE tid = ?');
    $statement->execute([$id]);
    return (int)$statement->fetchColumn();
  }

  public function updateContent(int $id, array $values): void {
    $sql = "UPDATE board_log SET modified = datetime('now', 'localtime'), a_name = :name, mail = :mail,
      sub = :sub, com = :com, a_url = :url, host = :host, sodane = :sodane, pwd = :pwdh,
      nsfw = :nsfw, thumbnail = :thumbnail WHERE tid = :id";
    $statement = $this->db->prepare($sql);
    $statement->execute([
      'name' => $values['name'], 'mail' => $values['mail'], 'sub' => $values['sub'], 'com' => $values['com'],
      'url' => $values['url'], 'host' => $values['host'], 'sodane' => $values['sodane'],
      'pwdh' => $values['pwdh'], 'nsfw' => $values['nsfw'], 'thumbnail' => $values['thumbnail'], 'id' => $id,
    ]);
  }

  public function updateImage(int $id, array $values): void {
    $sql = "UPDATE board_log SET modified = datetime('now', 'localtime'), host = :host, picfile = :picfile,
      pchfile = :pchfile, id = :author_id, psec = :psec, utime = :utime, nsfw = :nsfw,
      thumbnail = :thumbnail WHERE tid = :id AND picfile = :expected_picfile";
    $statement = $this->db->prepare($sql);
    $statement->execute([
      'host' => $values['host'], 'picfile' => $values['picfile'], 'pchfile' => $values['pchfile'],
      'author_id' => $values['author_id'], 'psec' => $values['psec'], 'utime' => $values['utime'],
      'nsfw' => $values['nsfw'], 'thumbnail' => $values['thumbnail'], 'id' => $id,
      'expected_picfile' => $values['expected_picfile'],
    ]);
    if ($statement->rowCount() !== 1) {
      throw new RuntimeException('The posted image changed before replacement completed.');
    }
  }

  /** @return array|false */
  public function latestThread() {
    return $this->db->query('SELECT * FROM board_log WHERE thread=1 ORDER BY tid DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
  }

  public function insertPost(array $post): int {
    $columns = ['thread','parent','comid','tree','a_name','sub','com','mail','a_url','picfile','pchfile','img_w','img_h','psec','utime','pwd','id','sodane','age','invz','host','tool','admins','shd','nsfw','ctype','uuid','thumbnail'];
    $sql = "INSERT INTO board_log (created, modified, " . implode(',', $columns) . ") VALUES (datetime('now','localtime'), datetime('now','localtime'), :" . implode(',:', $columns) . ')';
    $statement = $this->db->prepare($sql);
    $values = [];
    foreach ($columns as $column) $values[$column] = $post[$column] ?? null;
    $statement->execute($values);
    return (int)$this->db->lastInsertId();
  }

  public function bumpThread(int $id, int $age, int $tree): void {
    $statement = $this->db->prepare('UPDATE board_log SET age = ?, tree = ? WHERE tid = ?');
    $statement->execute([$age, $tree, $id]);
  }

  public function countThreads(bool $visible_only = false): int {
    $where = $visible_only ? ' WHERE invz=0' : '';
    return (int)$this->db->query('SELECT COALESCE(SUM(thread), 0) FROM board_log' . $where)->fetchColumn();
  }

  public function markOldThreads(int $count): void {
    if ($count <= 0) return;
    $statement = $this->db->prepare("UPDATE board_log SET shd='1' WHERE thread=1 AND shd='0' ORDER BY tid ASC LIMIT ?");
    $statement->bindValue(1, $count, PDO::PARAM_INT);
    $statement->execute();
  }

  public function listThreads(int $offset, int $limit): array {
    $statement = $this->db->prepare('SELECT * FROM board_log WHERE invz=0 AND thread=1 ORDER BY tree DESC LIMIT ?, ?');
    $statement->bindValue(1, $offset, PDO::PARAM_INT);
    $statement->bindValue(2, $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }

  public function countVisibleImages(): int {
    return (int)$this->db->query("SELECT COUNT(*) FROM board_log WHERE picfile != '' AND invz=0")->fetchColumn();
  }

  public function listCatalog(int $offset, int $limit): array {
    $statement = $this->db->prepare("SELECT * FROM board_log WHERE picfile != '' AND invz=0 ORDER BY age DESC, tree DESC LIMIT :start, :limit");
    $statement->bindValue(':start', $offset, PDO::PARAM_INT);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }

  /** @param array<string,string> $criteria */
  public function countPublicSearch(array $criteria): int {
    $condition = $this->publicSearchCondition($criteria);
    $statement = $this->db->prepare("SELECT COUNT(*) FROM board_log WHERE {$condition['sql']}");
    $statement->execute($condition['params']);
    return (int)$statement->fetchColumn();
  }

  /** @param array<string,string> $criteria */
  public function searchVisiblePosts(array $criteria, int $offset, int $limit): array {
    if ($offset < 0 || $limit < 1 || $limit > 200) throw new InvalidArgumentException('Invalid public search range.');
    $condition = $this->publicSearchCondition($criteria);
    $order = $criteria['sort'] === 'oldest' ? 'age ASC, tree ASC' : 'age DESC, tree DESC';
    $statement = $this->db->prepare(
      "SELECT * FROM board_log WHERE {$condition['sql']} ORDER BY {$order} LIMIT ? OFFSET ?"
    );
    $index = 1;
    foreach ($condition['params'] as $value) $statement->bindValue($index++, $value, PDO::PARAM_STR);
    $statement->bindValue($index++, $limit, PDO::PARAM_INT);
    $statement->bindValue($index, $offset, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }

  /** @param array<string,string> $criteria
   * @return array{sql:string,params:array<int,string>} */
  private function publicSearchCondition(array $criteria): array {
    $criteria = PublicPostSearch::normalize($criteria);
    // 空検索を全件検索に変換しない。正常な0件検索として扱う。
    if ($criteria['query'] === '') return ['sql' => '0 = 1', 'params' => []];
    $operator = $criteria['match'] === 'exact' ? '=' : 'LIKE';
    $value = $criteria['match'] === 'exact' ? $criteria['query'] : '%' . $criteria['query'] . '%';
    $sql = 'invz = 0';
    $params = [];
    if ($criteria['target'] === 'all') {
      $sql .= " AND (a_name {$operator} ? OR sub {$operator} ? OR com {$operator} ?)";
      $params = [$value, $value, $value];
    } else {
      $column = ['author' => 'a_name', 'subject' => 'sub', 'comment' => 'com'][$criteria['target']];
      $sql .= " AND {$column} {$operator} ?";
      $params[] = $value;
    }
    if ($criteria['post_type'] === 'thread') $sql .= ' AND thread = 1';
    if ($criteria['post_type'] === 'reply') $sql .= ' AND thread = 0';
    if ($criteria['image'] === 'with') $sql .= " AND picfile != ''";
    if ($criteria['image'] === 'without') $sql .= " AND picfile = ''";
    if ($criteria['nsfw'] === 'safe') $sql .= ' AND CAST(COALESCE(nsfw, 0) AS INTEGER) = 0';
    if ($criteria['nsfw'] === 'nsfw') $sql .= ' AND CAST(COALESCE(nsfw, 0) AS INTEGER) = 1';
    return compact('sql', 'params');
  }

  public function countAdminPosts(array $filters = []): int {
    $filters = $filters ?: AdminPostFilter::normalize([]);
    $condition = AdminPostFilter::rowCondition($filters, 'p', 'post_');
    $statement = $this->db->prepare("SELECT COUNT(*) FROM board_log p WHERE {$condition['sql']}");
    $statement->execute($condition['params']);
    return (int)$statement->fetchColumn();
  }

  public function countAdminThreads(array $filters = []): int {
    $filters = $filters ?: AdminPostFilter::normalize([]);
    $condition = AdminPostFilter::threadCondition($filters);
    $statement = $this->db->prepare("SELECT COUNT(*) FROM board_log t WHERE {$condition['sql']}");
    $statement->execute($condition['params']);
    return (int)$statement->fetchColumn();
  }

  public function adminDashboardStats(): array {
    $row = $this->db->query(
      "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN CAST(thread AS INTEGER) = 1 THEN 1 ELSE 0 END), 0) AS threads,
        COALESCE(SUM(CASE WHEN CAST(thread AS INTEGER) = 0 THEN 1 ELSE 0 END), 0) AS replies,
        COALESCE(SUM(CASE WHEN COALESCE(picfile, '') != '' THEN 1 ELSE 0 END), 0) AS images,
        COALESCE(SUM(CASE WHEN CAST(COALESCE(nsfw, 0) AS INTEGER) = 1 THEN 1 ELSE 0 END), 0) AS nsfw,
        COALESCE(SUM(CASE WHEN CAST(COALESCE(invz, 0) AS INTEGER) = 1 THEN 1 ELSE 0 END), 0) AS hidden,
        COALESCE(SUM(CASE WHEN CAST(COALESCE(admins, 0) AS INTEGER) = 1 THEN 1 ELSE 0 END), 0) AS administrators,
        COALESCE(SUM(CASE WHEN date(created) = date('now', 'localtime') THEN 1 ELSE 0 END), 0) AS today,
        COALESCE(SUM(CASE WHEN date(created) >= date('now', 'localtime', '-6 days') THEN 1 ELSE 0 END), 0) AS last_7_days,
        COALESCE(SUM(CASE WHEN date(created) >= date('now', 'localtime', '-29 days') THEN 1 ELSE 0 END), 0) AS last_30_days
      FROM board_log"
    )->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) throw new RuntimeException('Failed to aggregate administration statistics.');
    return array_map('intval', $row);
  }

  public function listAdminThreads(int $offset, int $limit, array $filters = []): array {
    $filters = $filters ?: AdminPostFilter::normalize([]);
    $condition = AdminPostFilter::threadCondition($filters);
    $statement = $this->db->prepare(
      "SELECT t.* FROM board_log t WHERE {$condition['sql']}
        ORDER BY t.age DESC, t.tree DESC, t.tid DESC LIMIT :limit OFFSET :offset"
    );
    foreach ($condition['params'] as $key => $value) $statement->bindValue(':' . $key, $value);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }

  public function listAdminReplies(array $parent_ids): array {
    $ids = array_values(array_unique(array_filter(
      array_map('intval', $parent_ids),
      static fn(int $id): bool => $id > 0
    )));
    if ($ids === []) return [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = $this->db->prepare(
      "SELECT * FROM board_log WHERE thread=0 AND parent IN ({$placeholders}) ORDER BY parent ASC, tree ASC, tid ASC"
    );
    $statement->execute($ids);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }

  /** @return array|false */
  public function oldestPost() {
    return $this->db->query('SELECT * FROM board_log ORDER BY tid LIMIT 1')->fetch(PDO::FETCH_ASSOC);
  }

  public function findPostsByImage(string $image_name): array {
    $statement = $this->db->prepare('SELECT * FROM board_log WHERE picfile = ? ORDER BY tree DESC');
    $statement->execute([$image_name]);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }
}

final class DatabaseMigrator {
  public const SCHEMA_VERSION = 1;

  private PDO $db;
  private string $database_file;
  private string $backup_dir;

  public function __construct(PDO $db, string $database_file, string $backup_dir) {
    $this->db = $db;
    $this->database_file = $database_file;
    $this->backup_dir = rtrim($backup_dir, DIRECTORY_SEPARATOR);
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  /**
   * DBを最新スキーマへ更新する。
   *
   * @return string|null 作成したバックアップのパス。新規DBならnull。
   */
  public function migrate(): ?string {
    $current_version = $this->schemaVersion();
    if ($current_version > self::SCHEMA_VERSION) {
      throw new RuntimeException("Database schema version {$current_version} is newer than supported version " . self::SCHEMA_VERSION . '.');
    }

    $tables = $this->tableNames();
    if (!$tables) {
      $this->transaction(function (): void {
        $this->createCurrentSchema();
        $this->setSchemaVersion(self::SCHEMA_VERSION);
      });
      return null;
    }

    if ($current_version === 0) {
      if (in_array('tlog', $tables, true) && !in_array('board_log', $tables, true)) {
        throw new RuntimeException('Version 2 database detected. Run noreita_db2_to_3.php before updating.');
      }
      if (!in_array('board_log', $tables, true)) {
        throw new RuntimeException('The board_log table was not found. The database was not modified.');
      }
      $this->assertCurrentColumns();
    }

    if ($current_version === self::SCHEMA_VERSION) {
      $this->assertCurrentColumns();
      return null;
    }

    $backup_path = $this->createBackup($current_version);
    $this->transaction(function () use ($current_version): void {
      for ($version = $current_version + 1; $version <= self::SCHEMA_VERSION; $version++) {
        $this->applyMigration($version);
        $this->setSchemaVersion($version);
      }
    });
    return $backup_path;
  }

  public function schemaVersion(): int {
    return (int)$this->db->query('PRAGMA user_version')->fetchColumn();
  }

  private function applyMigration(int $version): void {
    switch ($version) {
      case 1:
        // v3.0～v3.4のboard_logは現行スキーマなので、user_versionの登録だけを行う。
        $this->assertCurrentColumns();
        return;
      default:
        throw new RuntimeException("No migration is defined for schema version {$version}.");
    }
  }

  private function createCurrentSchema(): void {
    $this->db->exec("CREATE TABLE board_log (
      tid INTEGER PRIMARY KEY AUTOINCREMENT,
      created TIMESTAMP,
      modified TIMESTAMP,
      thread VARCHAR(1),
      parent INT,
      comid BIGINT,
      tree BIGINT,
      a_name TEXT,
      mail TEXT,
      sub TEXT,
      com TEXT,
      a_url TEXT,
      host TEXT,
      sodane TEXT,
      id TEXT,
      pwd TEXT,
      psec INT,
      utime TEXT,
      picfile TEXT,
      pchfile TEXT,
      img_w INT,
      img_h INT,
      age INT,
      invz VARCHAR(1),
      tool TEXT,
      admins VARCHAR(1),
      shd VARCHAR(1),
      nsfw TEXT,
      ctype TEXT,
      uuid TEXT,
      thumbnail TEXT
    )");
  }

  private function assertCurrentColumns(): void {
    $required = [
      'tid', 'created', 'modified', 'thread', 'parent', 'comid', 'tree', 'a_name', 'mail', 'sub',
      'com', 'a_url', 'host', 'sodane', 'id', 'pwd', 'psec', 'utime', 'picfile', 'pchfile',
      'img_w', 'img_h', 'age', 'invz', 'tool', 'admins', 'shd', 'nsfw', 'ctype', 'uuid', 'thumbnail',
    ];
    $columns = $this->db->query('PRAGMA table_info(board_log)')->fetchAll(PDO::FETCH_COLUMN, 1);
    $missing = array_values(array_diff($required, $columns));
    if ($missing) {
      throw new RuntimeException('The board_log schema is incompatible. Missing columns: ' . implode(', ', $missing));
    }
  }

  private function tableNames(): array {
    $statement = $this->db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");
    return $statement->fetchAll(PDO::FETCH_COLUMN);
  }

  private function setSchemaVersion(int $version): void {
    $this->db->exec('PRAGMA user_version = ' . $version);
  }

  private function transaction(callable $operation): void {
    $this->db->beginTransaction();
    try {
      $operation();
      $this->db->commit();
    } catch (Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }
      throw $e;
    }
  }

  private function createBackup(int $from_version): string {
    if (!is_dir($this->backup_dir) && !mkdir($this->backup_dir, 0700, true) && !is_dir($this->backup_dir)) {
      throw new RuntimeException('Could not create the database backup directory.');
    }

    $base_name = pathinfo($this->database_file, PATHINFO_FILENAME);
    $timestamp = date('Ymd-His');
    $backup_path = $this->backup_dir . DIRECTORY_SEPARATOR . "{$base_name}-schema{$from_version}-{$timestamp}.db";
    for ($suffix = 1; is_file($backup_path); $suffix++) {
      $backup_path = $this->backup_dir . DIRECTORY_SEPARATOR . "{$base_name}-schema{$from_version}-{$timestamp}-{$suffix}.db";
    }

    $previous_umask = umask(0077);
    try {
      if (version_compare((string)$this->db->query('SELECT sqlite_version()')->fetchColumn(), '3.27.0', '>=')) {
        $this->db->exec('VACUUM INTO ' . $this->db->quote($backup_path));
      } else {
        $this->createLegacyBackup($backup_path);
      }
    } finally {
      umask($previous_umask);
    }
    if (!FilesystemPermissions::modeChecksAreReliable()) return $backup_path;

    clearstatcache(true, $backup_path);
    $permission = fileperms($backup_path);
    if ($permission === false) throw new RuntimeException('Could not read database backup permissions.');
    if (($permission & 0777) !== 0600) {
      FilesystemPermissions::apply($backup_path, 0600);
      clearstatcache(true, $backup_path);
      $permission = fileperms($backup_path);
      if ($permission === false || (($permission & 0777) & 0077) !== 0) {
        throw new RuntimeException('Could not secure database backup permissions.');
      }
    }
    return $backup_path;
  }

  private function createLegacyBackup(string $backup_path): void {
    if (!class_exists('SQLite3')) {
      throw new RuntimeException('SQLite 3.27.0 or the PHP sqlite3 extension is required to back up the database.');
    }

    $source = new SQLite3($this->database_file, SQLITE3_OPEN_READONLY);
    $backup = new SQLite3($backup_path, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
    try {
      if (!$source->backup($backup)) {
        throw new RuntimeException('Could not back up the database.');
      }
    } finally {
      $backup->close();
      $source->close();
    }
  }
}
