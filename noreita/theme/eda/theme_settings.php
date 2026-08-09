<?php
// Theme-specific server-side settings for eda (C) sakots 2026 MIT License

final class EdaThemeSettings {
  private const SCHEMA_VERSION = 1;
  private const COLOR_SETTING_KEY = 'colors';

  private const DEFAULT_COLORS = [
    'pageBackground' => '#222244', 'pageBackgroundEnd' => '#000000',
    'text' => '#eeeeee', 'link' => '#eeeeee', 'linkVisited' => '#999999', 'linkAction' => '#cc0000',
    'surface' => '#112222', 'border' => '#992222', 'button' => '#333355', 'buttonText' => '#ffffff',
    'inputBackground' => '#eeeeee', 'inputText' => '#000000',
    'threadBackground' => '#001122', 'threadText' => '#ddffee',
    'noticeBackground' => '#554433', 'replyText' => '#cc88cc',
  ];

  private PDO $database;
  private string $database_file;

  public function __construct(string $theme_directory, int $busy_timeout = 5000, int $permission = 0600) {
    $theme_directory = rtrim($theme_directory, '/\\');
    if (!is_dir($theme_directory) || !is_writable($theme_directory)) {
      throw new RuntimeException('Theme directory is not writable.');
    }
    if ($busy_timeout < 0 || $busy_timeout > 60000) {
      throw new InvalidArgumentException('Invalid theme settings database timeout.');
    }
    $this->database_file = $theme_directory . DIRECTORY_SEPARATOR . 'theme_settings.db';
    $this->database = new PDO('sqlite:' . $this->database_file);
    $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->database->exec('PRAGMA busy_timeout = ' . $busy_timeout);
    // Settings are written infrequently. DELETE avoids leaving a public -wal file beside the theme.
    $this->database->exec('PRAGMA journal_mode=DELETE');
    $this->migrate();
    $this->secureFile($permission);
  }

  /** @return array<string,string> */
  public static function defaults(): array {
    return self::DEFAULT_COLORS;
  }

  /** @return array<string,mixed> */
  public function templateData(): array {
    $colors = $this->colors();
    $encoded = json_encode($colors, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    return [
      'theme_colors' => $colors,
      'theme_colors_json' => is_string($encoded) ? $encoded : '{}',
    ];
  }

  /** @return array<string,string> */
  public function colors(): array {
    $statement = $this->database->prepare('SELECT value FROM theme_settings WHERE setting_key = ?');
    $statement->execute([self::COLOR_SETTING_KEY]);
    $stored = $statement->fetchColumn();
    if (!is_string($stored) || $stored === '') return [];
    try {
      $decoded = json_decode($stored, true, 32, JSON_THROW_ON_ERROR);
      return is_array($decoded) ? self::normalizeColors($decoded) : [];
    } catch (Throwable $e) {
      return [];
    }
  }

  /** @param array<string,mixed> $settings */
  public function save(array $settings): void {
    $colors = $settings['colors'] ?? null;
    if (!is_array($colors)) throw new InvalidArgumentException('Invalid theme color settings.');
    $this->saveColors($colors);
  }

  /** @param array<string,mixed> $colors */
  public function saveColors(array $colors): void {
    $normalized = self::normalizeColors($colors);
    $encoded = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $statement = $this->database->prepare(
      'INSERT INTO theme_settings (setting_key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP) '
      . 'ON CONFLICT(setting_key) DO UPDATE SET value=excluded.value, updated_at=CURRENT_TIMESTAMP'
    );
    $statement->execute([self::COLOR_SETTING_KEY, $encoded]);
  }

  public function reset(): void {
    $this->resetColors();
  }

  public function resetColors(): void {
    $statement = $this->database->prepare('DELETE FROM theme_settings WHERE setting_key = ?');
    $statement->execute([self::COLOR_SETTING_KEY]);
  }

  /** @param array<string,mixed> $colors
   * @return array<string,string> */
  public static function normalizeColors(array $colors): array {
    if (array_diff(array_keys($colors), array_keys(self::DEFAULT_COLORS)) !== []) {
      throw new InvalidArgumentException('Unknown theme color setting.');
    }
    $normalized = [];
    foreach (self::DEFAULT_COLORS as $key => $default) {
      $value = $colors[$key] ?? null;
      if (!is_string($value) || preg_match('/\A#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})\z/D', $value) !== 1) {
        throw new InvalidArgumentException('Invalid theme color setting.');
      }
      $value = strtolower($value);
      $normalized[$key] = strlen($value) === 4
        ? '#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3]
        : $value;
    }
    return $normalized;
  }

  public function databaseFile(): string {
    return $this->database_file;
  }

  private function migrate(): void {
    $version = (int)$this->database->query('PRAGMA user_version')->fetchColumn();
    if ($version > self::SCHEMA_VERSION) {
      throw new RuntimeException('Theme settings database is newer than this noReita version.');
    }
    if ($version === 0) {
      $this->database->beginTransaction();
      try {
        $this->database->exec(
          'CREATE TABLE IF NOT EXISTS theme_settings ('
          . 'setting_key TEXT PRIMARY KEY, value TEXT NOT NULL, updated_at TEXT NOT NULL)'
        );
        $this->database->exec('PRAGMA user_version = ' . self::SCHEMA_VERSION);
        $this->database->commit();
      } catch (Throwable $e) {
        if ($this->database->inTransaction()) $this->database->rollBack();
        throw $e;
      }
    }
    $exists = $this->database->query(
      "SELECT 1 FROM sqlite_master WHERE type='table' AND name='theme_settings'"
    )->fetchColumn();
    if ($exists === false) throw new RuntimeException('Theme settings table is missing.');
  }

  private function secureFile(int $permission): void {
    if ($permission < 0600 || $permission > 0640 || ($permission & 0007) !== 0) {
      throw new InvalidArgumentException('Invalid theme settings database permission.');
    }
    clearstatcache(true, $this->database_file);
    $current = fileperms($this->database_file);
    if ($current === false) throw new RuntimeException('Could not read theme settings database permissions.');
    if (($current & 0777) !== $permission) {
      @chmod($this->database_file, $permission);
      clearstatcache(true, $this->database_file);
      $current = fileperms($this->database_file);
      if ($current === false || ($current & 0777) !== $permission) {
        throw new RuntimeException('Could not secure theme settings database permissions.');
      }
    }
  }
}
