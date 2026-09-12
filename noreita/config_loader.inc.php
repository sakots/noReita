<?php
// noReita v4 configuration loader (C) sakots 2026 MIT License

final class ConfigException extends RuntimeException {
}

final class Config {
  public const FORMAT_VERSION = 4;

  /** @var array<string,mixed>|null */
  private static ?array $values = null;
  private static ?string $root = null;

  public static function load(string $root): void {
    $resolved_root = realpath($root);
    if ($resolved_root === false || !is_dir($resolved_root)) {
      throw new ConfigException('Configuration root does not exist.');
    }
    if (self::$values !== null) {
      if (self::$root !== $resolved_root) {
        throw new ConfigException('Configuration has already been loaded from another root.');
      }
      return;
    }

    $default_file = $resolved_root . DIRECTORY_SEPARATOR . 'config.php';
    if (!is_file($default_file) || !is_readable($default_file)) {
      throw new ConfigException('config.php is missing or unreadable.');
    }
    $defaults = require $default_file;
    if (!is_array($defaults)) {
      throw new ConfigException('config.php must return an array.');
    }

    $overrides = [];
    $local_file = $resolved_root . DIRECTORY_SEPARATOR . 'config.local.php';
    if (is_file($local_file)) {
      if (!is_readable($local_file)) {
        throw new ConfigException('config.local.php is unreadable.');
      }
      $overrides = require $local_file;
      if (!is_array($overrides)) {
        throw new ConfigException('config.local.php must return an array.');
      }
    }

    self::$values = self::resolve($defaults, $overrides);
    self::$root = $resolved_root;
  }

  /**
   * @param array<string,mixed> $defaults
   * @param array<string,mixed> $overrides
   * @return array<string,mixed>
   */
  public static function resolve(array $defaults, array $overrides): array {
    if (($defaults['_version'] ?? null) !== self::FORMAT_VERSION) {
      throw new ConfigException('config.php has an incompatible format version.');
    }
    if (array_key_exists('_version', $overrides)) {
      throw new ConfigException('config.local.php must not override _version.');
    }
    $merged = self::merge($defaults, $overrides, '');
    self::validateTypes($defaults, $merged, '');
    self::validateValues($merged);
    return $merged;
  }

  /** @return mixed */
  public static function get(string $key) {
    if (self::$values === null) {
      throw new LogicException('Configuration has not been loaded.');
    }
    return self::valueAt(self::$values, $key);
  }

  public static function string(string $key): string {
    $value = self::get($key);
    if (!is_string($value)) throw new LogicException("Configuration value {$key} is not a string.");
    return $value;
  }

  public static function int(string $key): int {
    $value = self::get($key);
    if (!is_int($value)) throw new LogicException("Configuration value {$key} is not an integer.");
    return $value;
  }

  public static function bool(string $key): bool {
    $value = self::get($key);
    if (!is_bool($value)) throw new LogicException("Configuration value {$key} is not a boolean.");
    return $value;
  }

  public static function array(string $key): array {
    $value = self::get($key);
    if (!is_array($value)) throw new LogicException("Configuration value {$key} is not an array.");
    return $value;
  }

  /** @return array<string,mixed> */
  public static function all(): array {
    if (self::$values === null) throw new LogicException('Configuration has not been loaded.');
    return self::$values;
  }

  public static function isLoaded(): bool {
    return self::$values !== null;
  }

  /**
   * Test processes may load one isolated configuration at a time.
   * @param array<string,mixed> $defaults
   * @param array<string,mixed> $overrides
   */
  public static function initializeForTesting(array $defaults, array $overrides = []): void {
    self::$values = self::resolve($defaults, $overrides);
    self::$root = '[test]';
  }

  /** Test processes may load one isolated configuration at a time. */
  public static function resetForTesting(): void {
    self::$values = null;
    self::$root = null;
  }

  /**
   * @param array<string,mixed> $defaults
   * @param array<string,mixed> $overrides
   * @return array<string,mixed>
   */
  private static function merge(array $defaults, array $overrides, string $prefix): array {
    foreach ($overrides as $key => $value) {
      if (!is_string($key) || !array_key_exists($key, $defaults)) {
        $path = $prefix === '' ? (string)$key : $prefix . '.' . $key;
        throw new ConfigException("Unknown configuration key: {$path}");
      }
      $path = $prefix === '' ? $key : $prefix . '.' . $key;
      if (is_array($defaults[$key]) && is_array($value)
        && !self::isList($defaults[$key]) && !self::isList($value)) {
        $defaults[$key] = self::merge($defaults[$key], $value, $path);
      } else {
        $defaults[$key] = $value;
      }
    }
    return $defaults;
  }

  /**
   * @param array<string,mixed> $defaults
   * @param array<string,mixed> $values
   */
  private static function validateTypes(array $defaults, array $values, string $prefix): void {
    foreach ($defaults as $key => $expected) {
      $path = $prefix === '' ? (string)$key : $prefix . '.' . $key;
      if (!array_key_exists($key, $values)) {
        throw new ConfigException("Required configuration key is missing: {$path}");
      }
      $actual = $values[$key];
      if (gettype($actual) !== gettype($expected)) {
        throw new ConfigException("Invalid type for configuration key: {$path}");
      }
      if (is_array($expected) && !self::isList($expected)) {
        self::validateTypes($expected, $actual, $path);
      }
    }
  }

  /** @param array<string,mixed> $values */
  private static function validateValues(array $values): void {
    $required_strings = [
      'admin.password', 'admin.name', 'site.base_url', 'site.title', 'site.timezone',
      'site.script_name', 'database.name', 'identity.seed', 'security.paint_password',
      'security.session_name', 'paths.theme', 'paths.images', 'paths.thumbnails',
      'paths.temporary', 'paths.palette',
    ];
    foreach ($required_strings as $key) {
      if (trim((string)self::valueAt($values, $key)) === '') {
        throw new ConfigException("Configuration value must not be empty: {$key}");
      }
    }
    if (self::valueAt($values, 'admin.password') === 'admin_pass') {
      throw new ConfigException('admin.password must be changed in config.local.php.');
    }

    $base_url = self::valueAt($values, 'site.base_url');
    $scheme = is_string($base_url) ? parse_url($base_url, PHP_URL_SCHEME) : null;
    if (!filter_var($base_url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
      throw new ConfigException('site.base_url must be an absolute HTTP or HTTPS URL.');
    }
    if ($base_url === 'https://example.com/noreita/') {
      throw new ConfigException('site.base_url must be changed in config.local.php.');
    }
    if (substr($base_url, -1) !== '/') {
      throw new ConfigException('site.base_url must end with a slash.');
    }
    try {
      new DateTimeZone((string)self::valueAt($values, 'site.timezone'));
    } catch (Throwable $e) {
      throw new ConfigException('site.timezone is invalid.');
    }

    $plain_names = ['database.name', 'paths.theme'];
    foreach ($plain_names as $key) {
      $value = (string)self::valueAt($values, $key);
      if (basename($value) !== $value || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/D', $value) !== 1) {
        throw new ConfigException("Configuration value must be a plain safe name: {$key}");
      }
    }
    foreach (['paths.neo', 'paths.chickenpaint', 'paths.klecks', 'paths.tegaki', 'paths.axnos',
      'paths.images', 'paths.thumbnails', 'paths.temporary'] as $key) {
      self::validateRelativePath((string)self::valueAt($values, $key), $key, true);
    }
    self::validateRelativePath((string)self::valueAt($values, 'paths.palette'), 'paths.palette', false);

    $ranges = [
      'database.busy_timeout' => [0, 60000],
      'admin.session_lifetime' => [60, 31536000],
      'admin.login.max_failures' => [1, 1000],
      'admin.login.window' => [1, 31536000],
      'admin.login.lockout' => [1, 31536000],
      'admin.threads_per_page' => [1, 100],
      'admin.temporary_images_per_page' => [1, 100],
      'security.session_file_lifetime' => [60, 31536000],
      'identity.cycle' => [0, 4],
      'board.log_warning_percent' => [0, 100],
      'board.catalog_size' => [1, 200],
      'limits.paint_max_width' => [300, 10000],
      'limits.paint_max_height' => [300, 10000],
      'limits.paint_default_width' => [1, 10000],
      'limits.paint_default_height' => [1, 10000],
      // ルート.htaccessのLimitRequestBody（32MiB）を超える値は設定できない。
      'limits.paint_image_kb' => [1, 32768],
      'limits.paint_work_kb' => [1, 32768],
      'limits.paint_request_kb' => [1, 32768],
      'error_log.max_bytes' => [1024, 1073741824],
      'error_log.max_files_per_day' => [1, 1000],
      'audit_log.max_bytes' => [1024, 1073741824],
      'audit_log.max_files_per_day' => [1, 1000],
      'spam.comment_score_threshold' => [0, 1000000],
    ];
    foreach ($ranges as $key => $range) {
      $value = self::valueAt($values, $key);
      if ($value < $range[0] || $value > $range[1]) {
        throw new ConfigException("Configuration value is out of range: {$key}");
      }
    }
    foreach ([
      'board.max_threads', 'board.page_size', 'board.replies_shown',
      'board.cookie_days', 'limits.external_thumbnail_days', 'limits.upload_kb',
      'limits.image_width', 'limits.image_height', 'limits.upload_resize_width', 'limits.upload_resize_height', 'limits.name_length', 'limits.email_length',
      'limits.subject_length', 'limits.url_length', 'limits.comment_length', 'limits.temporary_days',
      'limits.undo', 'limits.undo_group', 'error_log.retention_days', 'audit_log.retention_days',
      'maintenance.delete_quarantine_days',
    ] as $key) {
      if (self::valueAt($values, $key) < 0) {
        throw new ConfigException("Configuration value must not be negative: {$key}");
      }
    }
    if (self::valueAt($values, 'limits.paint_default_width') > self::valueAt($values, 'limits.paint_max_width')
      || self::valueAt($values, 'limits.paint_default_height') > self::valueAt($values, 'limits.paint_max_height')) {
      throw new ConfigException('Default paint dimensions must not exceed maximum paint dimensions.');
    }
    if ((self::valueAt($values, 'limits.upload_resize_width') > 0
        && self::valueAt($values, 'limits.upload_resize_width') > self::valueAt($values, 'limits.image_width'))
      || (self::valueAt($values, 'limits.upload_resize_height') > 0
        && self::valueAt($values, 'limits.upload_resize_height') > self::valueAt($values, 'limits.image_height'))) {
      throw new ConfigException('Upload resize dimensions must not exceed upload dimensions.');
    }
    if (self::valueAt($values, 'limits.paint_request_kb') < self::valueAt($values, 'limits.paint_image_kb')
      || self::valueAt($values, 'limits.paint_request_kb') < self::valueAt($values, 'limits.paint_work_kb')) {
      throw new ConfigException('Paint request limit must cover each paint upload file limit.');
    }

    foreach (['permissions.public_file', 'permissions.private_file'] as $key) {
      $permission = self::valueAt($values, $key);
      if ($permission < 0400 || $permission > 0664 || ($permission & 0022) !== 0) {
        throw new ConfigException("Unsafe file permission configuration: {$key}");
      }
    }
    foreach (['permissions.public_directory', 'permissions.private_directory'] as $key) {
      $permission = self::valueAt($values, $key);
      if ($permission < 0700 || $permission > 0755 || ($permission & 0022) !== 0) {
        throw new ConfigException("Unsafe directory permission configuration: {$key}");
      }
    }

    foreach (['social.servers', 'social.misskey_servers'] as $key) {
      self::validatePairs(self::valueAt($values, $key), $key, true);
    }
    self::validatePairs(self::valueAt($values, 'drawing.palettes'), 'drawing.palettes', false);
    self::validateTrustedProxies(self::valueAt($values, 'security.trusted_proxies'));
    foreach (['spam.bad_strings', 'spam.bad_names', 'spam.bad_strings_a', 'spam.bad_strings_b',
      'spam.bad_files', 'spam.bad_hosts', 'board.additional_info', 'site.head_scripts'] as $key) {
      foreach (self::valueAt($values, $key) as $value) {
        if (!is_string($value)) throw new ConfigException("Configuration list must contain strings: {$key}");
      }
    }
    foreach (self::valueAt($values, 'spam.comment_score_rules') as $rule) {
      if (!is_array($rule) || count($rule) !== 2 || !is_string($rule[0] ?? null)
        || $rule[0] === '' || !is_int($rule[1] ?? null) || $rule[1] < 1) {
        throw new ConfigException('Configuration value must contain [pattern, positive score] pairs: spam.comment_score_rules');
      }
      if (@preg_match('/' . $rule[0] . '/ui', '') === false) {
        throw new ConfigException('Configuration value contains an invalid regular expression: spam.comment_score_rules');
      }
    }
  }

  private static function validateRelativePath(string $path, string $key, bool $directory): void {
    $normalized = str_replace('\\', '/', $path);
    if ($normalized === '' || $normalized[0] === '/' || preg_match('/\A[A-Za-z]:\//', $normalized)
      || in_array('..', explode('/', trim($normalized, '/')), true)
      || ($directory && substr($normalized, -1) !== '/')) {
      throw new ConfigException("Configuration value must be a safe relative path: {$key}");
    }
  }

  /** @param array<int, array<int, string>> $pairs */
  private static function validatePairs(array $pairs, string $key, bool $url): void {
    foreach ($pairs as $pair) {
      if (!is_array($pair) || count($pair) !== 2 || !is_string($pair[0] ?? null)
        || !is_string($pair[1] ?? null) || trim($pair[0]) === '' || trim($pair[1]) === '') {
        throw new ConfigException("Configuration value must contain string pairs: {$key}");
      }
      if ($url) {
        $scheme = parse_url($pair[1], PHP_URL_SCHEME);
        if (!filter_var($pair[1], FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
          throw new ConfigException("Configuration value contains an invalid URL: {$key}");
        }
      } else {
        self::validateRelativePath($pair[1], $key, false);
      }
    }
  }

  /** @param array<int,mixed> $proxies */
  private static function validateTrustedProxies(array $proxies): void {
    if (!self::isList($proxies)) {
      throw new ConfigException('security.trusted_proxies must be a list.');
    }
    if (count($proxies) > 256) {
      throw new ConfigException('security.trusted_proxies contains too many entries.');
    }
    foreach ($proxies as $proxy) {
      if (!is_string($proxy) || $proxy === '' || trim($proxy) !== $proxy) {
        throw new ConfigException('security.trusted_proxies must contain IP addresses or CIDR ranges.');
      }
      $parts = explode('/', $proxy, 2);
      $packed = @inet_pton($parts[0]);
      if ($packed === false) {
        throw new ConfigException('security.trusted_proxies contains an invalid address.');
      }
      if (!isset($parts[1])) continue;
      if (preg_match('/\A(?:0|[1-9][0-9]{0,2})\z/D', $parts[1]) !== 1) {
        throw new ConfigException('security.trusted_proxies contains an invalid CIDR prefix.');
      }
      $prefix = (int)$parts[1];
      $bits = strlen($packed) * 8;
      if ($prefix < 1 || $prefix > $bits) {
        throw new ConfigException('security.trusted_proxies contains an unsafe CIDR range.');
      }
    }
  }

  /**
   * @param array<string,mixed> $values
   * @return mixed
   */
  private static function valueAt(array $values, string $key) {
    $current = $values;
    foreach (explode('.', $key) as $segment) {
      if (!is_array($current) || !array_key_exists($segment, $current)) {
        throw new LogicException("Unknown configuration key: {$key}");
      }
      $current = $current[$segment];
    }
    return $current;
  }

  /** @param array<mixed> $value */
  private static function isList(array $value): bool {
    $index = 0;
    foreach ($value as $key => $_) {
      if ($key !== $index++) return false;
    }
    return true;
  }
}

/** Admin-only editor for the trusted local configuration override. */
final class ConfigEditor {
  /** @return array<string,mixed> */
  public static function defaults(string $root): array {
    $root = self::root($root);
    $file = $root . DIRECTORY_SEPARATOR . 'config.php';
    if (!is_file($file) || is_link($file)) throw new RuntimeException('Default configuration is unavailable.');
    $defaults = require $file;
    if (!is_array($defaults)) throw new RuntimeException('Default configuration is invalid.');
    return $defaults;
  }

  public static function editablePhp(): string {
    $values = Config::all();
    unset($values['_version']);
    return "<?php\n\nreturn " . self::export($values) . ";\n";
  }

  public static function save(string $root, string $source): void {
    if (strlen($source) > 1048576) throw new InvalidArgumentException('Configuration is too large.');
    $submitted = (new ConfigArrayParser($source))->parse();
    if (!is_array($submitted) || array_is_list($submitted)) {
      throw new InvalidArgumentException('Configuration must return an associative array.');
    }
    $defaults = self::defaults($root);
    $validated = Config::resolve($defaults, $submitted);
    $overrides = self::differences($defaults, $validated);
    self::writeLocal($root, $overrides);
  }

  /** @param array<string,mixed> $defaults @param array<string,mixed> $values @return array<string,mixed> */
  private static function differences(array $defaults, array $values): array {
    $differences = [];
    foreach ($defaults as $key => $default) {
      if ($key === '_version') continue;
      $value = $values[$key] ?? null;
      if (is_array($default) && is_array($value) && !self::isList($default) && !self::isList($value)) {
        $nested = self::differences($default, $value);
        if ($nested !== []) $differences[$key] = $nested;
      } elseif ($value !== $default) {
        $differences[$key] = $value;
      }
    }
    return $differences;
  }

  /** @param array<mixed> $value */
  private static function isList(array $value): bool {
    $index = 0;
    foreach ($value as $key => $_) {
      if ($key !== $index++) return false;
    }
    return true;
  }

  /** @param mixed $value */
  private static function export($value, int $indent = 0): string {
    if (!is_array($value)) return var_export($value, true);
    if ($value === []) return '[]';
    $lines = ['['];
    $padding = str_repeat('  ', $indent + 1);
    $list = self::isList($value);
    foreach ($value as $key => $item) {
      $prefix = $list ? '' : var_export($key, true) . ' => ';
      $lines[] = $padding . $prefix . self::export($item, $indent + 1) . ',';
    }
    $lines[] = str_repeat('  ', $indent) . ']';
    return implode("\n", $lines);
  }

  /** @param array<string,mixed> $overrides */
  private static function writeLocal(string $root, array $overrides): void {
    $root = self::root($root);
    if (!is_writable($root)) throw new RuntimeException('Application directory is not writable.');
    $target = $root . DIRECTORY_SEPARATOR . 'config.local.php';
    if (is_link($target)) throw new RuntimeException('config.local.php must not be a symbolic link.');
    $content = "<?php\n// Generated by the noReita administration configuration editor.\n\nreturn "
      . self::export($overrides) . ";\n";
    $temporary = tempnam($root, '.config-local-');
    if ($temporary === false) throw new RuntimeException('Failed to prepare config.local.php.');
    try {
      if (file_put_contents($temporary, $content, LOCK_EX) === false || !chmod($temporary, 0600)
        || !rename($temporary, $target)) {
        throw new RuntimeException('Failed to save config.local.php.');
      }
    } finally {
      if (is_file($temporary)) @unlink($temporary);
    }
  }

  private static function root(string $root): string {
    $resolved = realpath($root);
    if ($resolved === false || !is_dir($resolved) || is_link($resolved)) {
      throw new RuntimeException('Application directory is unavailable.');
    }
    return $resolved;
  }
}

/** Restricted parser for a PHP configuration array. It never evaluates submitted code. */
final class ConfigArrayParser {
  /** @var array<int,array{0:int,1:string,2:int}|string> */
  private array $tokens;
  private int $position = 0;

  public function __construct(string $source) {
    $source = preg_replace('/\A\s*<\?php\b/', '', $source) ?? $source;
    $tokens = token_get_all('<?php ' . $source);
    $this->tokens = array_values(array_filter($tokens, static function ($token): bool {
      return !is_array($token) || !in_array($token[0], [T_OPEN_TAG, T_CLOSE_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    }));
  }

  /** @return array<string,mixed> */
  public function parse(): array {
    $this->expect(T_RETURN);
    $value = $this->value();
    $this->expect(';');
    if ($this->current() !== null) throw new InvalidArgumentException('Only one return array is allowed.');
    if (!is_array($value) || array_is_list($value)) {
      throw new InvalidArgumentException('Configuration must return an associative array.');
    }
    return $value;
  }

  /** @return mixed */
  private function value() {
    $token = $this->current();
    if ($this->matches($token, '[')) return $this->arrayValue();
    if ($this->matches($token, '-')) {
      $this->position++;
      $number = $this->value();
      if (!is_int($number) && !is_float($number)) throw new InvalidArgumentException('Only numeric values may be negative.');
      return -$number;
    }
    $this->position++;
    if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) return $this->stringValue($token[1]);
    if (is_array($token) && $token[0] === T_LNUMBER) return (int)$token[1];
    if (is_array($token) && $token[0] === T_DNUMBER) return (float)$token[1];
    if (is_array($token) && $token[0] === T_STRING) {
      return match (strtolower($token[1])) {
        'true' => true, 'false' => false, 'null' => null,
        default => throw new InvalidArgumentException('Only arrays and scalar configuration values are allowed.'),
      };
    }
    throw new InvalidArgumentException('Invalid configuration value.');
  }

  /** @return array<mixed> */
  private function arrayValue(): array {
    $this->expect('[');
    $values = [];
    $next_key = 0;
    if ($this->matches($this->current(), ']')) {
      $this->position++;
      return $values;
    }
    while (true) {
      $first = $this->value();
      if ($this->matches($this->current(), T_DOUBLE_ARROW)) {
        if (!is_int($first) && !is_string($first)) throw new InvalidArgumentException('Array keys must be strings or integers.');
        $this->position++;
        $key = $first;
        $value = $this->value();
      } else {
        $key = $next_key++;
        $value = $first;
      }
      $values[$key] = $value;
      if (is_int($key) && $key >= $next_key) $next_key = $key + 1;
      if ($this->matches($this->current(), ']')) {
        $this->position++;
        return $values;
      }
      $this->expect(',');
      if ($this->matches($this->current(), ']')) {
        $this->position++;
        return $values;
      }
    }
  }

  private function stringValue(string $literal): string {
    $quote = $literal[0] ?? '';
    $value = substr($literal, 1, -1);
    if ($quote === '"') return stripcslashes($value);
    if ($quote !== "'") throw new InvalidArgumentException('Invalid string value.');
    $result = '';
    for ($index = 0, $length = strlen($value); $index < $length; $index++) {
      if ($value[$index] === '\\' && $index + 1 < $length && ($value[$index + 1] === '\\' || $value[$index + 1] === "'")) {
        $index++;
      }
      $result .= $value[$index];
    }
    return $result;
  }

  /** @param int|string $expected */
  private function expect($expected): void {
    if (!$this->matches($this->current(), $expected)) throw new InvalidArgumentException('Invalid PHP array configuration syntax.');
    $this->position++;
  }

  /** @param array{0:int,1:string,2:int}|string|null $token @param int|string $expected */
  private function matches($token, $expected): bool {
    return is_array($token) ? $token[0] === $expected : $token === $expected;
  }

  /** @return array{0:int,1:string,2:int}|string|null */
  private function current() {
    return $this->tokens[$this->position] ?? null;
  }
}

/** Mapping used only by the v3-to-v4 configuration converter. */
final class LegacyConfigMap {
  /** @return array<string,string> legacy constant => v4 key */
  public static function constantMap(): array {
    return [
      'MAX_THREAD' => 'board.max_threads', 'THEME_DIR' => 'paths.theme',
      'BASE' => 'site.base_url', 'TITLE' => 'site.title', 'HOME' => 'site.home_url',
      'USE_CHICKENPAINT' => 'features.chickenpaint', 'USE_KLECKS' => 'features.klecks',
      'USE_TEGAKI' => 'features.tegaki', 'USE_AXNOS' => 'features.axnos',
      'DB_NAME' => 'database.name', 'DB_BUSY_TIMEOUT' => 'database.busy_timeout',
      'USE_OEKAKI_REPLY' => 'features.oekaki_reply', 'SHARE_BUTTON' => 'features.share_button',
      'SWITCH_SNS' => 'features.share_details', 'SNS_WINDOW_WIDTH' => 'social.window_width',
      'SNS_WINDOW_HEIGHT' => 'social.window_height', 'USE_MISSKEY_NOTE' => 'features.misskey_note',
      'EXTERNAL_IMAGE_THUMB' => 'features.external_image_thumbnail',
      'EXTERNAL_IMAGE_THUMB_DAYS' => 'limits.external_thumbnail_days', 'USE_NSFW' => 'features.nsfw',
      'DISP_ID' => 'features.display_id', 'ID_SEED' => 'identity.seed', 'ID_CYCLE' => 'identity.cycle',
      'ADMIN_CAP' => 'admin.cap', 'USE_JAPANESEFILTER' => 'features.japanese_filter',
      'DENY_COMMENTS_URL' => 'features.deny_comment_urls', 'ELAPSED_DAYS' => 'board.elapsed_reply_days',
      'CRYPT_PASS' => 'security.paint_password', 'LANG' => 'site.language',
      'DEFAULT_TIMEZONE' => 'site.timezone', 'USER_DEL' => 'board.user_delete',
      'SESSION_NAME' => 'security.session_name', 'SESSION_FILE_LIFETIME' => 'security.session_file_lifetime',
      'ADMIN_SESSION_LIFETIME' => 'admin.session_lifetime',
      'ADMIN_LOGIN_MAX_FAILURES' => 'admin.login.max_failures', 'ADMIN_LOGIN_WINDOW' => 'admin.login.window',
      'ADMIN_LOGIN_LOCKOUT' => 'admin.login.lockout', 'ADMIN_THREADS_PER_PAGE' => 'admin.threads_per_page',
      'NEO_DIR' => 'paths.neo', 'CHICKEN_DIR' => 'paths.chickenpaint', 'KLECKS_DIR' => 'paths.klecks',
      'TEGAKI_DIR' => 'paths.tegaki', 'AXNOS_DIR' => 'paths.axnos', 'UNDO' => 'limits.undo',
      'UNDO_IN_MG' => 'limits.undo_group', 'SECURITY_CLICK' => 'security.click_count',
      'SECURITY_TIMER' => 'security.timer', 'SECURITY_URL' => 'security.failure_url',
      'C_SECURITY_CLICK' => 'security.continue_click_count', 'C_SECURITY_TIMER' => 'security.continue_timer',
      'IMG_DIR' => 'paths.images', 'THUMB_DIR' => 'paths.thumbnails', 'MAX_KB' => 'limits.upload_kb',
      'MAX_W' => 'limits.image_width', 'MAX_H' => 'limits.image_height',
      'MAX_NAME' => 'limits.name_length', 'MAX_EMAIL' => 'limits.email_length',
      'MAX_SUB' => 'limits.subject_length', 'MAX_URL' => 'limits.url_length',
      'MAX_COM' => 'limits.comment_length', 'PAGE_DEF' => 'board.page_size',
      'DSP_RES' => 'board.replies_shown', 'LOG_LIMIT' => 'board.log_warning_percent',
      'CATALOG_N' => 'board.catalog_size', 'SAVE_COOKIE' => 'board.cookie_days',
      'DATE_FORMAT' => 'board.date_format', 'MAX_RES' => 'board.force_sage_replies',
      'AUTOLINK' => 'features.autolink', 'USE_NAME' => 'features.require_name',
      'DEF_NAME' => 'board.default_name', 'USE_COM' => 'features.require_comment',
      'DEF_COM' => 'board.default_comment', 'USE_SUB' => 'features.require_subject',
      'DEF_SUB' => 'board.default_subject', 'USE_RESUB' => 'features.reply_subject',
      'USE_HASHTAG' => 'features.hashtag', 'TEMP_DIR' => 'paths.temporary',
      'TEMP_LIMIT' => 'limits.temporary_days', 'PMAX_W' => 'limits.paint_max_width',
      'PMAX_H' => 'limits.paint_max_height', 'PDEF_W' => 'limits.paint_default_width',
      'PDEF_H' => 'limits.paint_default_height', 'DSP_PAINTTIME' => 'features.display_paint_time',
      'PALETTEFILE' => 'paths.palette', 'USE_SELECT_PALETTES' => 'features.select_palettes',
      'USE_ANIME' => 'features.animation', 'DEF_ANIME' => 'features.animation_default',
      'PCH_SPEED' => 'drawing.animation_speed', 'USE_CONTINUE' => 'features.continue_drawing',
      'CONTINUE_PASS' => 'features.continue_password', 'PERMISSION_FOR_DEST' => 'permissions.public_file',
      'PERMISSION_FOR_LOG' => 'permissions.private_file', 'PERMISSION_FOR_DIR' => 'permissions.public_directory',
      'PERMISSION_FOR_PRIVATE_DIR' => 'permissions.private_directory',
      'ERROR_LOG_RETENTION_DAYS' => 'error_log.retention_days', 'ERROR_LOG_MAX_BYTES' => 'error_log.max_bytes',
      'ERROR_LOG_MAX_FILES_PER_DAY' => 'error_log.max_files_per_day',
      'DELETE_QUARANTINE_RETENTION_DAYS' => 'maintenance.delete_quarantine_days',
      'CHECK_CSRF_TOKEN' => 'features.csrf', 'PHP_SELF' => 'site.script_name',
    ];
  }

  /** @return array<string,string> legacy variable => v4 key */
  public static function variableMap(): array {
    return [
      'admin_pass' => 'admin.password', 'admin_name' => 'admin.name',
      'servers' => 'social.servers', 'misskey_servers' => 'social.misskey_servers',
      'badstring' => 'spam.bad_strings', 'badname' => 'spam.bad_names',
      'badstr_A' => 'spam.bad_strings_a', 'badstr_B' => 'spam.bad_strings_b',
      'badfile' => 'spam.bad_files', 'badip' => 'spam.bad_hosts',
      'addinfo' => 'board.additional_info', 'pallets_dat' => 'drawing.palettes',
    ];
  }

}
