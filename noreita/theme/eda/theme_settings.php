<?php
// Theme-specific server-side settings for eda (C) sakots 2026 MIT License

final class EdaThemeSettings {
  private const SCHEMA_VERSION = 1;
  private const COLOR_SETTING_KEY = 'colors';

  private const DEFAULT_COLORS = [
    // Keep these in sync with css/mono/_eda_conf.scss, eda's base stylesheet.
    'pageBackground' => '#cccccc', 'pageBackgroundEnd' => '#cccccc',
    'text' => '#000000', 'link' => '#003366', 'linkVisited' => '#666666', 'linkAction' => '#ff0000',
    'surface' => '#eeeeee', 'border' => '#003366',
    'buttonBorder' => '#3366ff', 'buttonBorderInset' => '#003366',
    'button' => '#336699', 'buttonText' => '#ffffff',
    'inputBackground' => '#ffffff', 'inputText' => '#000000',
    'threadBackground' => '#99ccff', 'threadText' => '#000066',
    'noticeBackground' => '#ffcccc', 'replyText' => '#114411',
  ];

  private const DARK_COLORS = [
    // Keep these in sync with css/dark/_eda_conf.scss.
    'pageBackground' => '#111111', 'pageBackgroundEnd' => '#111111',
    'text' => '#fefefe', 'link' => '#6666ff', 'linkVisited' => '#999999', 'linkAction' => '#ff3333',
    'surface' => '#333333', 'border' => '#9999ff',
    'buttonBorder' => '#6666ff', 'buttonBorderInset' => '#000033',
    'button' => '#6699ff', 'buttonText' => '#ffffff',
    'inputBackground' => '#eeeeee', 'inputText' => '#000000',
    'threadBackground' => '#003366', 'threadText' => '#eeeecc',
    'noticeBackground' => '#112244', 'replyText' => '#44dd44',
  ];

  private const DEEP_COLORS = [
    'pageBackground' => '#111111', 'pageBackgroundEnd' => '#111111',
    'text' => '#fefefe', 'link' => '#fefefe', 'linkVisited' => '#999999', 'linkAction' => '#fefefe',
    'surface' => '#333333', 'border' => '#339966',
    'buttonBorder' => '#66cc99', 'buttonBorderInset' => '#006633',
    'button' => '#339966', 'buttonText' => '#ffffff',
    'inputBackground' => '#eeeeee', 'inputText' => '#000000',
    'threadBackground' => '#111111', 'threadText' => '#ffeecc',
    'noticeBackground' => '#332211', 'replyText' => '#999999',
  ];

  private const DEV_COLORS = [
    'pageBackground' => '#112211', 'pageBackgroundEnd' => '#000000',
    'text' => '#fefefe', 'link' => '#fefefe', 'linkVisited' => '#999999', 'linkAction' => '#fefefe',
    'surface' => '#182818', 'border' => '#339966',
    'buttonBorder' => '#66cc99', 'buttonBorderInset' => '#006633',
    'button' => '#339966', 'buttonText' => '#ffffff',
    'inputBackground' => '#eeeeee', 'inputText' => '#000000',
    'threadBackground' => '#181111', 'threadText' => '#ccccee',
    'noticeBackground' => '#332211', 'replyText' => '#999999',
  ];

  private const MAYO_COLORS = [
    'pageBackground' => '#d1ce9e', 'pageBackgroundEnd' => '#d1ce9e',
    'text' => '#000000', 'link' => '#0000ff', 'linkVisited' => '#666666', 'linkAction' => '#ff3333',
    'surface' => '#fefefe', 'border' => '#000000',
    'buttonBorder' => '#ff6666', 'buttonBorderInset' => '#330000',
    'button' => '#ff9966', 'buttonText' => '#ffffff',
    'inputBackground' => '#eeeeee', 'inputText' => '#000000',
    'threadBackground' => '#ff6666', 'threadText' => '#112233',
    'noticeBackground' => '#ccccff', 'replyText' => '#505544',
  ];

  private const POP_COLORS = [
    'pageBackground' => '#226633', 'pageBackgroundEnd' => '#3cb359',
    'text' => '#000000', 'link' => '#3344ff', 'linkVisited' => '#556644', 'linkAction' => '#ff3322',
    'surface' => '#ffeedd', 'border' => '#cc1111',
    'buttonBorder' => '#cc9966', 'buttonBorderInset' => '#663300',
    'button' => '#996633', 'buttonText' => '#ffffff',
    'inputBackground' => '#eeeeee', 'inputText' => '#000000',
    'threadBackground' => '#6666cc', 'threadText' => '#ffccaa',
    'noticeBackground' => '#dddd55', 'replyText' => '#114411',
  ];

  private const RED_COLORS = [
    'pageBackground' => '#cc9999', 'pageBackgroundEnd' => '#eedddd',
    'text' => '#000000', 'link' => '#000000', 'linkVisited' => '#666666', 'linkAction' => '#ff3322',
    'surface' => '#fedede', 'border' => '#ff3333',
    'buttonBorder' => '#99cc66', 'buttonBorderInset' => '#336600',
    'button' => '#669933', 'buttonText' => '#ffffff',
    'inputBackground' => '#eedddd', 'inputText' => '#000000',
    'threadBackground' => '#ffaaaa', 'threadText' => '#113300',
    'noticeBackground' => '#99bbaa', 'replyText' => '#114411',
  ];

  private const REITA_COLORS = [
    'pageBackground' => '#222244', 'pageBackgroundEnd' => '#000000',
    'text' => '#eeeeee', 'link' => '#4444ff', 'linkVisited' => '#999999', 'linkAction' => '#cc0000',
    'surface' => '#112222', 'border' => '#992222',
    'buttonBorder' => '#444466', 'buttonBorderInset' => '#222244',
    'button' => '#333355', 'buttonText' => '#ffffff',
    'inputBackground' => '#eeeeee', 'inputText' => '#000000',
    'threadBackground' => '#001122', 'threadText' => '#ddffee',
    'noticeBackground' => '#554433', 'replyText' => '#cc88cc',
  ];

  private const SQL_COLORS = [
    'pageBackground' => '#ffffef', 'pageBackgroundEnd' => '#ffffef',
    'text' => '#79160a', 'link' => '#0000ff', 'linkVisited' => '#79160a', 'linkAction' => '#d12d1a',
    'surface' => '#ffffef', 'border' => '#79160a',
    'buttonBorder' => '#000000', 'buttonBorderInset' => '#000000',
    'button' => '#e5e5e5', 'buttonText' => '#000000',
    'inputBackground' => '#ffffff', 'inputText' => '#000000',
    'threadBackground' => '#eee0d7', 'threadText' => '#2f7448',
    'noticeBackground' => '#79aa8a', 'replyText' => '#52745e',
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

  /** @return array<string,array<string,string>> */
  public static function colorPresets(): array {
    return [
      'mono' => self::DEFAULT_COLORS,
      'dark' => self::DARK_COLORS,
      'deep' => self::DEEP_COLORS,
      'dev' => self::DEV_COLORS,
      'mayo' => self::MAYO_COLORS,
      'pop' => self::POP_COLORS,
      'red' => self::RED_COLORS,
      'reita' => self::REITA_COLORS,
      'sql' => self::SQL_COLORS,
    ];
  }

  /** @return array<string,mixed> */
  public function templateData(): array {
    $colors = array_replace(self::DEFAULT_COLORS, $this->colors());
    $encoded = json_encode($colors, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $presets = self::colorPresets();
    $presets_encoded = json_encode($presets, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    return [
      'theme_colors' => $colors,
      'theme_colors_json' => is_string($encoded) ? $encoded : '{}',
      'theme_color_presets_json' => is_string($presets_encoded) ? $presets_encoded : '{}',
      'theme_color_preset_names' => array_keys($presets),
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
      return is_array($decoded) ? self::normalizeStoredColors($decoded) : [];
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

  /**
   * Accept settings created by an older eda theme version. New keys are filled
   * from the current defaults by templateData(), while invalid data is ignored.
   *
   * @param array<string,mixed> $colors
   * @return array<string,string>
   */
  private static function normalizeStoredColors(array $colors): array {
    if (array_diff(array_keys($colors), array_keys(self::DEFAULT_COLORS)) !== []) return [];
    $normalized = [];
    foreach ($colors as $key => $value) {
      if (!is_string($value) || preg_match('/\A#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})\z/D', $value) !== 1) {
        return [];
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
