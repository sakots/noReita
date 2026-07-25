<?php
// initialization.inc.php for noReita (C) sakots 2026 MIT License

const INITIALIZATION_INC_VER = 20260725;

final class ApplicationInitializer {
  private string $database_dsn;
  private string $database_file;
  private string $backup_dir;
  private string $application_root;
  private array $directories;
  private int $database_permission;

  public function __construct(
    string $database_dsn,
    string $database_file,
    string $backup_dir,
    string $application_root,
    array $directories,
    int $database_permission = 0600
  ) {
    $this->database_dsn = $database_dsn;
    $this->database_file = $database_file;
    $this->backup_dir = $backup_dir;
    $this->application_root = $application_root;
    $this->directories = $directories;
    $this->database_permission = $database_permission;
  }

  public static function securityHeaders(): array {
    return [
      'X-Content-Type-Options: nosniff',
      'X-Frame-Options: DENY',
      'X-XSS-Protection: 1; mode=block',
      'Referrer-Policy: strict-origin-when-cross-origin',
      'Permissions-Policy: geolocation=(), microphone=(), camera=()',
    ];
  }

  public function sendSecurityHeaders(): void {
    foreach (self::securityHeaders() as $header) header($header);
  }

  public function migrateDatabase(): void {
    $previous_umask = umask(0077);
    try {
      $database = new PDO($this->database_dsn);
      (new DatabaseMigrator($database, $this->database_file, $this->backup_dir))->migrate();
    } finally {
      umask($previous_umask);
    }
  }

  public function prepareDirectories(): void {
    $root = realpath($this->application_root);
    if ($root === false || !is_dir($root)) {
      throw new RuntimeException('Application directory does not exist.');
    }
    foreach ($this->directories as $directory => $permission) {
      if (!is_string($directory) || !is_int($permission)
        || $permission < 0700 || $permission > 0755 || ($permission & 0022) !== 0) {
        throw new RuntimeException('Invalid directory permission configuration.');
      }
      if (!is_dir($directory)
        && !mkdir($directory, $permission, true)
        && !is_dir($directory)) {
        throw new RuntimeException("Failed to create directory: {$directory}");
      }
      $this->applyPermission($directory, $permission, true);
      if (!is_readable($directory) || !is_writable($directory)) {
        throw new RuntimeException("Directory is not readable and writable: {$directory}");
      }
    }
  }

  public function secureDatabaseFile(): void {
    if (!is_file($this->database_file)) return;
    $this->applyPermission($this->database_file, $this->database_permission, false);
    if (!is_readable($this->database_file) || !is_writable($this->database_file)) {
      throw new RuntimeException('Database file is not readable and writable.');
    }
  }

  private function applyPermission(string $path, int $permission, bool $directory): void {
    clearstatcache(true, $path);
    $current = fileperms($path);
    if ($current === false) {
      throw new RuntimeException("Failed to read permissions: {$path}");
    }
    $current &= 0777;
    if ($current === $permission) return;

    if (@chmod($path, $permission)) {
      clearstatcache(true, $path);
      $updated = fileperms($path);
      if ($updated !== false && ($updated & 0777) === $permission) return;
    }

    // chmodが禁止された環境でも、現在値が要求より狭く安全なら継続する。
    // 要求にない余分な権限が付いている場合は安全のため拒否する。
    $unexpected = $current & ((~$permission) & 0777);
    if ($unexpected !== 0) {
      $kind = $directory ? 'directory' : 'file';
      throw new RuntimeException("Failed to secure {$kind} permissions: {$path}");
    }
  }
}
