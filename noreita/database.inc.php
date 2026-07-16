<?php
// database.inc.php for noReita (C) sakots 2026 MIT License

const DATABASE_INC_VER = 20260716;

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

    $this->db->exec('VACUUM INTO ' . $this->db->quote($backup_path));
    chmod($backup_path, 0600);
    return $backup_path;
  }
}
