<?php
// initialization.inc.php for noReita (C) sakots 2026 MIT License

require_once __DIR__ . '/filesystem_permissions.inc.php';

const INITIALIZATION_INC_VER = 20260817;

final class ApplicationInitializer {
  private string $database_dsn;
  private string $database_file;
  private string $backup_dir;
  private string $application_root;
  private array $directories;
  private int $database_permission;
  private ?string $temporary_directory;
  private const TEMPORARY_ACCESS_DENY_RULE = <<<'HTACCESS'
# 投稿前の一時画像・動画・作業ファイルへの直接HTTPアクセスを拒否する。
# 表示は index.php?mode=temporary_image の権限確認を通す。
<IfModule mod_authz_core.c>
  Require all denied
</IfModule>

<IfModule !mod_authz_core.c>
  Order Allow,Deny
  Deny from all
</IfModule>
HTACCESS;

  public function __construct(
    string $database_dsn,
    string $database_file,
    string $backup_dir,
    string $application_root,
    array $directories,
    int $database_permission = 0600,
    ?string $temporary_directory = null
  ) {
    $this->database_dsn = $database_dsn;
    $this->database_file = $database_file;
    $this->backup_dir = $backup_dir;
    $this->application_root = $application_root;
    $this->directories = $directories;
    $this->database_permission = $database_permission;
    $this->temporary_directory = $temporary_directory;
  }

  public static function securityHeaders(): array {
    return [
      'X-Content-Type-Options: nosniff',
      'X-Frame-Options: DENY',
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
      $database = Database::connect($this->database_dsn);
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
    if ($this->temporary_directory !== null) {
      $this->installTemporaryAccessDenyRule($this->temporary_directory);
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
    // Windowsでは数値モードをACLへ正確に対応付けられない。存在と読み書き可否は呼び出し側で検証する。
    if (!FilesystemPermissions::modeChecksAreReliable()) return;

    clearstatcache(true, $path);
    $current = fileperms($path);
    if ($current === false) {
      throw new RuntimeException("Failed to read permissions: {$path}");
    }
    $current &= 0777;
    if ($current === $permission) return;

    if (FilesystemPermissions::apply($path, $permission)) {
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

  private function installTemporaryAccessDenyRule(string $directory): void {
    $directory = rtrim($directory, '/\\');
    $root = realpath($this->application_root);
    $real_directory = realpath($directory);
    if ($root === false || $real_directory === false || !is_dir($real_directory)
      || !str_starts_with($real_directory . DIRECTORY_SEPARATOR, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
      throw new RuntimeException('Temporary directory is outside the application directory.');
    }

    $rule_file = $real_directory . DIRECTORY_SEPARATOR . '.htaccess';
    if (is_link($rule_file)) {
      throw new RuntimeException('Temporary directory access rule must not be a symbolic link.');
    }
    // .htaccessはOSにかかわらずLFで生成し、既存ファイルはCRLF・CRもLFへそろえて比較する。
    $expected = self::TEMPORARY_ACCESS_DENY_RULE . "\n";
    $current = is_file($rule_file) ? @file_get_contents($rule_file) : false;
    if ($current === false) {
      $temporary = @tempnam($real_directory, '.temporary-access-');
      if ($temporary === false || @file_put_contents($temporary, $expected, LOCK_EX) === false
        || !FilesystemPermissions::apply($temporary, 0644) || !@rename($temporary, $rule_file)) {
        if (is_string($temporary) && is_file($temporary)) @unlink($temporary);
        throw new RuntimeException('Failed to install temporary directory access rule.');
      }
    } elseif (str_replace(["\r\n", "\r"], "\n", $current) !== $expected) {
      // 設置者の独自設定を上書きして公開状態に戻さない。安全な規則が必須であることを明示する。
      throw new RuntimeException('Temporary directory access rule is invalid.');
    }
    clearstatcache(true, $rule_file);
    if (!is_file($rule_file) || !is_readable($rule_file)) {
      throw new RuntimeException('Temporary directory access rule is not readable.');
    }
  }
}
