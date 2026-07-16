<?php
// initialization.inc.php for noReita (C) sakots 2026 MIT License

const INITIALIZATION_INC_VER = 20260716;

final class ApplicationInitializer {
  public function __construct(
    private string $database_dsn,
    private string $database_file,
    private string $backup_dir,
    private string $application_root,
    private array $directories,
    private int $directory_permission,
    private int $database_permission = 0600
  ) {}

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
    $database = new PDO($this->database_dsn);
    (new DatabaseMigrator($database, $this->database_file, $this->backup_dir))->migrate();
  }

  public function prepareDirectories(): void {
    $root = realpath($this->application_root);
    if ($root === false || !is_dir($root) || !is_writable($root)) {
      throw new RuntimeException('Application directory is not writable.');
    }
    foreach ($this->directories as $directory) {
      if (!is_dir($directory)
        && !mkdir($directory, $this->directory_permission, true)
        && !is_dir($directory)) {
        throw new RuntimeException("Failed to create directory: {$directory}");
      }
      if (!chmod($directory, $this->directory_permission)
        || !is_readable($directory) || !is_writable($directory)) {
        throw new RuntimeException("Directory is not readable and writable: {$directory}");
      }
    }
  }

  public function secureDatabaseFile(): void {
    if (is_file($this->database_file) && !chmod($this->database_file, $this->database_permission)) {
      throw new RuntimeException('Failed to set database file permissions.');
    }
  }
}
