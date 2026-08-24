<?php
declare(strict_types=1);

const PTIME_D = '日';
const PTIME_H = '時間';
const PTIME_M = '分';
const PTIME_S = '秒';

require_once dirname(__DIR__) . '/noreita/config_loader.inc.php';
require_once dirname(__DIR__) . '/noreita/bootstrap.php';
require_once dirname(__DIR__) . '/noreita/vendor/autoload.php';
$config_defaults = require dirname(__DIR__) . '/noreita/config.php';
Config::initializeForTesting($config_defaults, [
  'admin' => ['password' => 'smoke-test-admin'],
  'site' => ['base_url' => 'https://smoke.example/'],
  'identity' => ['cycle' => 0, 'seed' => 'smoke-test-seed'],
]);

require_once dirname(__DIR__) . '/noreita/functions.php';
require_once dirname(__DIR__) . '/noreita/error_handler.inc.php';
require_once dirname(__DIR__) . '/noreita/request_security.inc.php';
require_once dirname(__DIR__) . '/noreita/request_info.inc.php';
require_once dirname(__DIR__) . '/noreita/save.inc.php';
require_once dirname(__DIR__) . '/noreita/thumbnail.inc.php';
require_once dirname(__DIR__) . '/noreita/external_image.inc.php';
require_once dirname(__DIR__) . '/noreita/database.inc.php';
require_once dirname(__DIR__) . '/noreita/initialization.inc.php';
require_once dirname(__DIR__) . '/noreita/theme/eda/theme_settings.php';
require_once dirname(__DIR__) . '/noreita/image.inc.php';
require_once dirname(__DIR__) . '/noreita/post.inc.php';
require_once dirname(__DIR__) . '/noreita/share.inc.php';
require_once dirname(__DIR__) . '/noreita/misskey_security.inc.php';
require_once dirname(__DIR__) . '/noreita/template_engine.inc.php';
require_once dirname(__DIR__) . '/noreita/theme_manifest.inc.php';
require_once dirname(__DIR__) . '/plugins/check-image-consistency.php';
require_once dirname(__DIR__) . '/scripts/migrate-config-v3.php';

$passed = 0;
$failed = 0;

function smoke_test(string $name, callable $test): void {
  global $passed, $failed;

  try {
    if ($test() !== true) {
      throw new RuntimeException('test returned false');
    }
    echo "PASS: {$name}\n";
    $passed++;
  } catch (Throwable $e) {
    echo "FAIL: {$name} ({$e->getMessage()})\n";
    $failed++;
  }
}

smoke_test('minimum PHP version is 8.1', static function (): bool {
  return NOREITA_MIN_PHP_VERSION === '8.1.0'
    && NOREITA_MIN_PHP_VERSION_ID === 80100
    && PHP_VERSION_ID >= NOREITA_MIN_PHP_VERSION_ID;
});

smoke_test('PHP 8.5 deprecated imagedestroy is not used', static function (): bool {
  $save = file_get_contents(dirname(__DIR__) . '/noreita/save.inc.php');
  return is_string($save) && !preg_match('/\bimagedestroy\s*\(/i', $save);
});

smoke_test('post image candidates replace each other in the post form', static function (): bool {
  $script = file_get_contents(dirname(__DIR__) . '/noreita/animation-upload.js');
  return is_string($script)
    && str_contains($script, "select[name=\"picfile\"]")
    && str_contains($script, 'clearTemporaryImage')
    && str_contains($script, "directUpload.addEventListener('change'")
    && str_contains($script, "input.value = '';")
    && str_contains($script, "directUpload.value = '';");
});

smoke_test('BladeOne and Twig render through the template engine abstraction', static function (): bool {
  $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_templates_' . bin2hex(random_bytes(8));
  $views = $root . DIRECTORY_SEPARATOR . 'views';
  $overrides = $root . DIRECTORY_SEPARATOR . 'overrides';
  $cache = $root . DIRECTORY_SEPARATOR . 'cache';
  if (!mkdir($views, 0700, true) || !mkdir($overrides, 0700, true) || !mkdir($cache, 0700, true)) return false;
  try {
    if (file_put_contents($views . DIRECTORY_SEPARATOR . 'sample.blade.php', 'Hello {{ $name }}') === false
      || file_put_contents($views . DIRECTORY_SEPARATOR . 'sample.twig', 'Hello {{ name }}') === false
      || file_put_contents($views . DIRECTORY_SEPARATOR . 'fallback.blade.php', 'Fallback {{ $name }}') === false
      || file_put_contents($overrides . DIRECTORY_SEPARATOR . 'sample.twig', 'Override {{ name }}') === false) return false;
    $blade = TemplateEngineFactory::create('blade', [$overrides, $views], $cache);
    $twig = TemplateEngineFactory::create('twig', [$overrides, $views], $cache);
    try {
      TemplateEngineFactory::create('unknown', $views, $cache);
      return false;
    } catch (InvalidArgumentException $e) {
      // Expected: only configured engines may be selected.
    }
    return $blade->render('sample', ['name' => 'BladeOne']) === 'Hello BladeOne'
      && $twig->render('sample', ['name' => 'Twig']) === 'Override Twig'
      && $twig->render('fallback', ['name' => 'BladeOne']) === 'Fallback BladeOne';
  } finally {
    if (is_dir($root)) {
      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
      );
      foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
      }
      rmdir($root);
    }
  }
});

smoke_test('eda Twig theme templates compile', static function (): bool {
  $views = dirname(__DIR__) . '/noreita/theme/eda';
  $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_eda_twig_' . bin2hex(random_bytes(8));
  $cache = $root . DIRECTORY_SEPARATOR . 'cache';
  if (!is_dir($views) || !mkdir($cache, 0700, true)) return false;
  try {
    $engine = TemplateEngineFactory::create('twig', $views, $cache);
    if (!$engine instanceof TwigTemplateEngine) return false;
    $count = 0;
    $prefix = strlen($views) + 1;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($views, FilesystemIterator::SKIP_DOTS)) as $file) {
      $path = $file->getPathname();
      if (!$file->isFile() || !str_ends_with($path, '.twig')) continue;
      $engine->validate(substr($path, $prefix, -5));
      $count++;
    }
    return $count > 0;
  } finally {
    if (is_dir($root)) {
      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
      );
      foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
      }
      rmdir($root);
    }
  }
});

smoke_test('asynchronous paint replacements display the returned edit form', static function (): bool {
  $root = dirname(__DIR__) . '/noreita/theme';
  $templates = [
    'eda/components/eda_paintKlecks.twig',
    'eda/components/eda_paintTegaki.twig',
    'eda/components/eda_paintAxnos.twig',
    'monoreita/components/monoreita_paintKlecks.blade.php',
    'monoreita/components/monoreita_paintTegaki.blade.php',
    'monoreita/components/monoreita_paintAxnos.blade.php',
  ];
  foreach ($templates as $template) {
    $source = file_get_contents($root . DIRECTORY_SEPARATOR . $template);
    if (!is_string($source)
      || !str_contains($source, 'formData.append("mode", "picrep")')
      || !str_contains($source, 'document.write(text)')) {
      return false;
    }
  }
  return true;
});

smoke_test('PaintBBS NEO loads from GitHub with the configured mirror as fallback', static function (): bool {
  $root = dirname(__DIR__) . '/noreita/theme';
  $loaders = [
    'eda/js/neoLoader.js',
    'monoreita/js/neoLoader.js',
  ];
  foreach ($loaders as $loader) {
    $source = file_get_contents($root . DIRECTORY_SEPARATOR . $loader);
    if (!is_string($source)
      || !str_contains($source, 'api.github.com/repos/funige/neo/contents/dist/')
      || !str_contains($source, "?ref=master")
      || !str_contains($source, "fetchSource('neo.css')")
      || !str_contains($source, "fetchSource('neo.js')")
      || !str_contains($source, 'loadFallback')) {
      return false;
    }
  }

  $templates = [
    'eda/eda_paint.twig', 'eda/eda_anime.twig',
    'monoreita/monoreita_paint.blade.php', 'monoreita/monoreita_anime.blade.php',
  ];
  foreach ($templates as $template) {
    $source = file_get_contents($root . DIRECTORY_SEPARATOR . $template);
    if (!is_string($source) || !str_contains($source, 'loadPaintBbsNeo(')) return false;
  }
  return true;
});

smoke_test('simple theme stylesheets load after page-specific styles', static function (): bool {
  $themes = [
    [
      'directory' => dirname(__DIR__) . '/noreita/theme/eda',
      'pattern' => '*.twig',
      'head' => 'components/eda_headCss.twig',
      'custom' => 'components/eda_customCss.twig',
    ],
    [
      'directory' => dirname(__DIR__) . '/noreita/theme/monoreita',
      'pattern' => '*.blade.php',
      'head' => 'components.monoreita_headCss',
      'custom' => 'components.monoreita_customCss',
    ],
  ];
  $checked = 0;
  foreach ($themes as $theme) {
    foreach (glob($theme['directory'] . DIRECTORY_SEPARATOR . $theme['pattern']) ?: [] as $template) {
      $source = file_get_contents($template);
      if (!is_string($source) || !str_contains($source, $theme['head'])) continue;
      $head_end = stripos($source, '</head>');
      $custom_position = strpos($source, $theme['custom']);
      if ($head_end === false || $custom_position === false || $custom_position >= $head_end) return false;
      $head = substr($source, 0, $head_end);
      $last_link = strripos($head, '<link rel="stylesheet"');
      $last_style = strripos($head, '<style');
      $last_page_style = max($last_link === false ? -1 : $last_link, $last_style === false ? -1 : $last_style);
      if ($custom_position <= $last_page_style || $custom_position <= strpos($source, $theme['head'])) return false;
      $checked++;
    }
  }
  return $checked === 28;
});

smoke_test('administration templates escape post subjects', static function (): bool {
  $eda = file_get_contents(dirname(__DIR__) . '/noreita/theme/eda/eda_admin.twig');
  $monoreita = file_get_contents(dirname(__DIR__) . '/noreita/theme/monoreita/monoreita_admin.blade.php');
  if (!is_string($eda) || !is_string($monoreita)) return false;
  return !preg_match('/mb_substr\\([^\\n]+\\[\'sub\'\\][^\\n]+\\)\\|raw/', $eda)
    && !preg_match('/\\{!!\\s*mb_substr\\([^\\n]+\\[\'sub\'\\][^\\n]+\\)\\s*!!\\}/', $monoreita);
});

smoke_test('theme manifests and diagnostics detect theme integrity problems', static function (): bool {
  $eda = dirname(__DIR__) . '/noreita/theme/eda';
  $manifest = ThemeManifest::load($eda);
  $runtime = [
    'id' => $manifest['id'], 'version' => $manifest['version'], 'engine' => $manifest['engine'],
    'templates' => $manifest['templates'],
  ];
  $report = ThemeDiagnostics::inspect($eda, $manifest, $runtime);
  if ($report['summary']['errors'] !== 0 || $report['summary']['templates_checked'] !== 15) return false;
  $invalid = $manifest;
  $invalid['assets']['css'][] = 'css/missing-theme-asset.css';
  $invalid_report = ThemeDiagnostics::inspect($eda, $invalid, $runtime);
  return $invalid_report['summary']['errors'] > 0
    && in_array('missing_asset', array_column($invalid_report['issues'], 'code'), true);
});

smoke_test('Blade diagnostics reject syntax errors and double-quoted missing includes', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_blade_diagnostic_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700, true)) return false;
  try {
    $template = <<<'BLADE'
@include("components.missing")
@if (true)
<p>missing endif</p>
BLADE;
    if (file_put_contents($directory . DIRECTORY_SEPARATOR . 'main.blade.php', $template) === false) return false;
    $manifest = [
      'format' => 1,
      'id' => 'invalid-blade',
      'name' => 'Invalid Blade',
      'version' => '1.0.0',
      'engine' => 'blade',
      'requires' => ['php' => '8.1.0', 'noreita' => '4.2.0'],
      'templates' => ['main' => 'main'],
      'assets' => ['css' => [], 'javascript' => []],
      'legacy' => false,
    ];
    $runtime = [
      'id' => 'invalid-blade',
      'version' => '1.0.0',
      'engine' => 'blade',
      'templates' => ['main' => 'main'],
    ];
    $report = ThemeDiagnostics::inspect($directory, $manifest, $runtime);
    $codes = array_column($report['issues'], 'code');
    $base_errors_detected = in_array('missing_component', $codes, true)
      && in_array('blade_syntax', $codes, true);

    $base_directory = $directory . DIRECTORY_SEPARATOR . 'base';
    $override_directory = $directory . DIRECTORY_SEPARATOR . 'override';
    if (!mkdir($base_directory, 0700) || !mkdir($override_directory, 0700)
      || file_put_contents($base_directory . DIRECTORY_SEPARATOR . 'main.blade.php', '<p>valid</p>') === false
      || file_put_contents($override_directory . DIRECTORY_SEPARATOR . 'main.blade.php', $template) === false) {
      return false;
    }
    $override_report = ThemeDiagnostics::inspectRuntime([
      'base_directory' => $base_directory,
      'manifest' => $manifest,
      'base_metadata' => $runtime,
      'simple' => true,
      'engine' => 'blade',
      'view_directories' => [$override_directory, $base_directory],
      'stylesheet_themes' => [],
    ]);
    $override_codes = array_column($override_report['issues'], 'code');
    return $base_errors_detected
      && in_array('missing_component', $override_codes, true)
      && in_array('blade_syntax', $override_codes, true);
  } finally {
    if (is_dir($directory)) {
      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
      );
      foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
      }
      rmdir($directory);
    }
  }
});

smoke_test('simple themes inherit templates, engine, assets, and diagnostics from a parent', static function (): bool {
  $themes = dirname(__DIR__) . '/noreita/theme';
  $runtime = ThemeRuntime::load($themes, 'starter');
  $report = ThemeDiagnostics::inspectRuntime($runtime);
  return $runtime['id'] === 'starter'
    && $runtime['name'] === 'starter'
    && $runtime['version'] === '1.0.0'
    && $runtime['base_id'] === 'eda'
    && $runtime['engine'] === 'twig'
    && count($runtime['view_directories']) === 2
    && $runtime['view_directories'][0] === $themes . '/starter'
    && $runtime['view_directories'][1] === $themes . '/eda'
    && count($runtime['stylesheet_themes']) === 1
    && $runtime['stylesheet_themes'][0]['id'] === 'starter'
    && str_starts_with($runtime['stylesheet_themes'][0]['version'], '1.0.0-')
    && $report['summary']['errors'] === 0;
});

smoke_test('simple themes reject cycles, unsafe parents, and unknown settings', static function (): bool {
  $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_theme_inheritance_' . bin2hex(random_bytes(8));
  $a = $root . DIRECTORY_SEPARATOR . 'a';
  $b = $root . DIRECTORY_SEPARATOR . 'b';
  if (!mkdir($a, 0700, true) || !mkdir($b, 0700, true)) return false;
  try {
    file_put_contents($a . DIRECTORY_SEPARATOR . 'theme.php', "<?php return ['extends' => 'b'];\n");
    file_put_contents($b . DIRECTORY_SEPARATOR . 'theme.php', "<?php return ['extends' => 'a'];\n");
    try {
      ThemeRuntime::load($root, 'a');
      return false;
    } catch (ThemeManifestException $e) {
      if (!str_contains($e->getMessage(), 'cycle')) return false;
    }
    file_put_contents($a . DIRECTORY_SEPARATOR . 'theme.php', "<?php return ['extends' => '../eda'];\n");
    try {
      ThemeRuntime::load($root, 'a');
      return false;
    } catch (ThemeManifestException $e) {
      // Expected.
    }
    file_put_contents($a . DIRECTORY_SEPARATOR . 'theme.php', "<?php return ['extends' => 'b', 'engine' => 'twig'];\n");
    try {
      ThemeRuntime::load($root, 'a');
      return false;
    } catch (ThemeManifestException $e) {
      return str_contains($e->getMessage(), 'unknown setting');
    }
  } finally {
    foreach ([$a, $b] as $directory) {
      $definition = $directory . DIRECTORY_SEPARATOR . 'theme.php';
      if (is_file($definition)) unlink($definition);
      if (is_dir($directory)) rmdir($directory);
    }
    if (is_dir($root)) rmdir($root);
  }
});

smoke_test('required PHP extensions', static function (): bool {
  foreach (['curl', 'gd', 'mbstring', 'pdo_sqlite'] as $extension) {
    if (!extension_loaded($extension)) {
      throw new RuntimeException("missing extension: {$extension}");
    }
  }
  return true;
});

smoke_test('configuration overrides defaults and replaces list values', static function (): bool {
  $defaults = require dirname(__DIR__) . '/noreita/config.php';
  $resolved = Config::resolve($defaults, [
    'admin' => ['password' => 'configured-admin', 'login' => ['max_failures' => 9]],
    'site' => ['base_url' => 'https://configured.example/'],
    'features' => [
      'nsfw' => false,
      'image_upload' => false,
      'diary_mode' => true,
      'diary_allow_public_replies' => false,
    ],
    'social' => ['servers' => [['Local', 'https://social.example']]],
    'security' => ['trusted_proxies' => ['192.0.2.10', '2001:db8:1234::/48']],
  ]);
  return $resolved['admin']['name'] === '管理人'
    && $resolved['admin']['login']['max_failures'] === 9
    && $resolved['features']['nsfw'] === false
    && $resolved['features']['image_upload'] === false
    && $resolved['features']['diary_mode'] === true
    && $resolved['features']['diary_allow_public_replies'] === false
    && $resolved['security']['trusted_proxies'] === ['192.0.2.10', '2001:db8:1234::/48']
    && $resolved['social']['servers'] === [['Local', 'https://social.example']];
});

smoke_test('diary posting policy restricts new posts and can allow public replies', static function (): bool {
  return DiaryPostPolicy::allows(false, false, false, false)
    && !DiaryPostPolicy::allows(true, true, false, false)
    && DiaryPostPolicy::allows(true, true, false, true)
    && !DiaryPostPolicy::allows(true, false, false, true)
    && DiaryPostPolicy::allows(true, false, true, false);
});

smoke_test('eda theme settings database initializes separately and validates saved colors', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_theme_settings_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) return false;
  try {
    $settings = new EdaThemeSettings($directory);
    $defaults = EdaThemeSettings::defaults();
    $presets = EdaThemeSettings::colorPresets();
    $initial_template_data = $settings->templateData();
    $colors = $defaults;
    $colors['pageBackground'] = '#123456';
    $settings->saveColors($colors);
    $stored = $settings->colors();
    $database = new PDO('sqlite:' . $settings->databaseFile());
    $version = (int)$database->query('PRAGMA user_version')->fetchColumn();
    $table_exists = $database->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='theme_settings'")->fetchColumn() !== false;
    $invalid_rejected = false;
    try {
      $settings->saveColors(['pageBackground' => 'invalid']);
    } catch (InvalidArgumentException $e) {
      $invalid_rejected = true;
    }
    $settings->resetColors();
    $result = $stored['pageBackground'] === '#123456'
      && $settings->colors() === [] && $version === 1 && $table_exists && $invalid_rejected
      && $defaults['pageBackground'] === '#cccccc' && $defaults['threadBackground'] === '#99ccff'
      && $defaults['buttonBorder'] === '#3366ff' && $defaults['buttonBorderInset'] === '#003366'
      && array_keys($presets) === ['mono', 'dark', 'deep', 'dev', 'mayo', 'pop', 'red', 'reita', 'sql']
      && $presets['dark']['pageBackground'] === '#111111' && $presets['dark']['threadText'] === '#eeeecc'
      && $presets['sql']['pageBackground'] === '#ffffef' && $presets['pop']['pageBackgroundEnd'] === '#3cb359'
      && $initial_template_data['theme_colors']['pageBackground'] === '#cccccc'
      && (fileperms($settings->databaseFile()) & 0777) === 0600;
    $database = null;
    unset($settings);
    return $result;
  } finally {
    foreach (glob($directory . DIRECTORY_SEPARATOR . 'theme_settings.db*') ?: [] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('configuration rejects unknown keys, invalid types, and unsafe ranges', static function (): bool {
  $defaults = require dirname(__DIR__) . '/noreita/config.php';
  $invalid = [
    ['admin' => ['password' => 'configured-admin'], 'site' => ['base_url' => 'https://configured.example/'], 'unknown' => true],
    ['admin' => ['password' => 'configured-admin', 'threads_per_page' => '50'], 'site' => ['base_url' => 'https://configured.example/']],
    ['admin' => ['password' => 'configured-admin', 'threads_per_page' => 101], 'site' => ['base_url' => 'https://configured.example/']],
    ['admin' => ['password' => 'configured-admin'], 'site' => ['base_url' => 'https://configured.example/'], 'board' => ['catalog_size' => 0]],
    ['admin' => ['password' => 'configured-admin'], 'site' => ['base_url' => 'https://configured.example/'], 'board' => ['catalog_size' => 201]],
    ['admin' => ['password' => 'configured-admin'], 'site' => ['base_url' => 'https://configured.example/'], 'limits' => ['paint_request_kb' => 32769]],
    ['admin' => ['password' => 'configured-admin'], 'site' => ['base_url' => 'https://configured.example/'], 'limits' => ['paint_image_kb' => 2048, 'paint_work_kb' => 4096, 'paint_request_kb' => 1024]],
    ['admin' => ['password' => 'configured-admin'], 'site' => ['base_url' => 'https://configured.example/'], 'security' => ['trusted_proxies' => ['not-an-ip']]],
    ['admin' => ['password' => 'configured-admin'], 'site' => ['base_url' => 'https://configured.example/'], 'security' => ['trusted_proxies' => ['0.0.0.0/0']]],
    ['admin' => ['password' => 'configured-admin'], 'site' => ['base_url' => 'https://configured.example/'], 'security' => ['trusted_proxies' => ['2001:db8::/129']]],
    ['admin' => ['password' => 'admin_pass'], 'site' => ['base_url' => 'https://configured.example/']],
    ['admin' => ['password' => 'configured-admin'], 'site' => ['base_url' => 'https://example.com/noreita/']],
  ];
  foreach ($invalid as $override) {
    try {
      Config::resolve($defaults, $override);
      return false;
    } catch (ConfigException $e) {
      if (str_contains($e->getMessage(), 'configured-admin')) return false;
    }
  }
  $minimum = Config::resolve($defaults, [
    'admin' => ['password' => 'configured-admin'],
    'site' => ['base_url' => 'https://configured.example/'],
    'board' => ['catalog_size' => 1],
  ]);
  $maximum = Config::resolve($defaults, [
    'admin' => ['password' => 'configured-admin'],
    'site' => ['base_url' => 'https://configured.example/'],
    'board' => ['catalog_size' => 200],
  ]);
  return $minimum['board']['catalog_size'] === 1 && $maximum['board']['catalog_size'] === 200;
});

smoke_test('v3 configuration is converted to a validated local override', static function (): bool {
  $file = tempnam(sys_get_temp_dir(), 'noreita_v3_config_');
  if ($file === false) return false;
  $source = <<<'PHP'
<?php
$admin_pass = 'migrated-admin';
$admin_name = '旧管理人';
$servers = [['移行先', 'https://social.example']];
const BASE = 'https://board.example/';
const ID_SEED = 'migrated-id-seed';
const CRYPT_PASS = 'migrated-paint-key';
const USE_NSFW = 0;
const SNS_WINDOW_WIDTH = '720';
PHP;
  try {
    if (file_put_contents($file, $source) === false) return false;
    $defaults = require dirname(__DIR__) . '/noreita/config.php';
    $overrides = migration_convert(migration_read_legacy($file), $defaults);
    $resolved = Config::resolve($defaults, $overrides);
    return $resolved['admin']['password'] === 'migrated-admin'
      && $resolved['admin']['name'] === '旧管理人'
      && $resolved['site']['base_url'] === 'https://board.example/'
      && $resolved['features']['nsfw'] === false
      && $resolved['social']['window_width'] === 720
      && $resolved['social']['servers'] === [['移行先', 'https://social.example']];
  } finally {
    if (is_file($file)) unlink($file);
  }
});

smoke_test('error logs rotate at capacity and expired files are cleaned safely', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_error_logs_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) return false;
  $locked_handle = null;
  try {
    if (!ErrorLogStorage::append($directory, '20260805', "first\n", 10, 2)
      || !ErrorLogStorage::append($directory, '20260805', "second\n", 10, 2)
      || ErrorLogStorage::append($directory, '20260805', "third\n", 10, 2)) {
      return false;
    }
    if ((string)file_get_contents($directory . '/error-20260805.log') !== "first\n"
      || (string)file_get_contents($directory . '/error-20260805.1.log') !== "second\n") {
      return false;
    }

    $now = 1700000000;
    $today = date('Ymd', $now);
    $files = [
      'error-20200101.log' => 100,
      'error-20200101.1.log' => 100,
      'error-20200101.2.log' => 100,
      'error-20200102.log' => $now,
      'error-' . $today . '.log' => 100,
      'unrelated.log' => 100,
    ];
    foreach ($files as $name => $modified) {
      file_put_contents($directory . DIRECTORY_SEPARATOR . $name, $name);
      touch($directory . DIRECTORY_SEPARATOR . $name, $modified);
    }
    $locked_path = $directory . '/error-20200101.2.log';
    $locked_handle = fopen($locked_path, 'r');
    if ($locked_handle === false || !flock($locked_handle, LOCK_EX | LOCK_NB)) return false;

    $removed = ErrorLogStorage::cleanup($directory, 30, 20, $now);
    return $removed === 2
      && !is_file($directory . '/error-20200101.log')
      && !is_file($directory . '/error-20200101.1.log')
      && is_file($locked_path)
      && is_file($directory . '/error-20200102.log')
      && is_file($directory . '/error-' . $today . '.log')
      && is_file($directory . '/unrelated.log');
  } finally {
    if (is_resource($locked_handle)) {
      flock($locked_handle, LOCK_UN);
      fclose($locked_handle);
    }
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('administrator error log reader only reads valid records from named log files', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_error_reader_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) return false;
  try {
    $client = json_encode([
      'timestamp' => '2026-08-11T10:00:00+09:00', 'error_id' => '20260811100000-client',
      'type' => 'http-client-error', 'http_status' => 403, 'request_method' => 'GET',
      'request_path' => '/noreita/', 'message' => 'Forbidden',
    ], JSON_UNESCAPED_SLASHES);
    $server = json_encode([
      'timestamp' => '2026-08-11T10:01:00+09:00', 'error_id' => '20260811100100-server',
      'type' => 'http-server-error', 'http_status' => 500, 'request_method' => 'POST',
      'request_path' => '/noreita/', 'message' => 'Internal error',
    ], JSON_UNESCAPED_SLASHES);
    if (!is_string($client) || !is_string($server)
      || file_put_contents($directory . '/error-20260811.log', "not json\n" . $client . "\n") === false
      || file_put_contents($directory . '/error-20260811.1.log', $server . "\n") === false
      || file_put_contents($directory . '/unrelated.log', $server . "\n") === false) {
      return false;
    }
    $all = ErrorLogReader::read($directory, '20260811');
    $client_only = ErrorLogReader::read($directory, '20260811', 'all', '4xx');
    return ErrorLogReader::availableDates($directory) === ['20260811']
      && $all['total'] === 2
      && count($all['records']) === 2
      && $all['records'][0]['error_id'] === '20260811100100-server'
      && $all['types'] === ['http-client-error', 'http-server-error']
      && $client_only['total'] === 1
      && $client_only['records'][0]['http_status'] === 403
      && ErrorLogReader::read($directory, '../20260811')['total'] === 0;
  } finally {
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('legacy administrator audits remain readable from error logs', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_audit_logs_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) return false;
  try {
    $record = [
      'timestamp' => '2026-08-11T10:02:00+09:00', 'error_id' => '20260811100200-audit',
      'type' => 'admin-audit', 'audit_action' => 'temporary-images-delete',
      'files' => 3, 'images' => 1, 'message' => 'Administrator action: temporary-images-delete files=3 images=1',
    ];
    $line = json_encode($record, JSON_UNESCAPED_SLASHES);
    if (!is_string($line) || file_put_contents($directory . '/error-20260811.log', $line . "\n") === false) return false;
    $result = ErrorLogReader::read($directory, '20260811', 'admin-audit');
    return $result['total'] === 1 && count($result['records']) === 1
      && $result['records'][0]['type'] === 'admin-audit'
      && str_contains((string)$result['records'][0]['message'], 'temporary-images-delete')
      && !str_contains((string)$result['records'][0]['message'], 'password=');
  } finally {
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('administrator audits have independent storage and capacity', static function (): bool {
  $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_split_logs_' . bin2hex(random_bytes(8));
  $error_directory = $root . DIRECTORY_SEPARATOR . 'errorlog';
  $audit_directory = $root . DIRECTORY_SEPARATOR . 'auditlog';
  if (!mkdir($error_directory, 0700, true) || !mkdir($audit_directory, 0700, true)) return false;
  try {
    $audit_record = json_encode([
      'timestamp' => '2026-08-11T10:03:00+09:00', 'error_id' => '20260811100300-audit',
      'type' => 'admin-audit', 'audit_action' => 'login',
      'message' => 'Administrator action: login',
    ], JSON_UNESCAPED_SLASHES);
    if (!is_string($audit_record)
      || !ErrorLogStorage::append($error_directory, '20260811', "12345\n", 6, 1)
      || ErrorLogStorage::append($error_directory, '20260811', "full\n", 6, 1)
      || !ErrorLogStorage::append($audit_directory, '20260811', $audit_record . "\n", 2048, 1, 'audit')) {
      return false;
    }
    $audit = AuditLogReader::read($audit_directory, '20260811');
    return is_file($error_directory . '/error-20260811.log')
      && is_file($audit_directory . '/audit-20260811.log')
      && !is_file($error_directory . '/audit-20260811.log')
      && !is_file($audit_directory . '/error-20260811.log')
      && AuditLogReader::availableDates($audit_directory) === ['20260811']
      && $audit['total'] === 1
      && $audit['records'][0]['type'] === 'admin-audit'
      && str_contains((string)$audit['records'][0]['message'], 'login');
  } finally {
    foreach ([$error_directory, $audit_directory] as $directory) {
      foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        if (is_file($file)) unlink($file);
      }
      if (is_dir($directory)) rmdir($directory);
    }
    if (is_dir($root)) rmdir($root);
  }
});

smoke_test('private files and directories ship Apache access denial rules', static function (): bool {
  $root_rule = file_get_contents(dirname(__DIR__) . '/noreita/.htaccess');
  if (!is_string($root_rule)
    || !str_contains($root_rule, 'mod_authz_core.c')
    || !str_contains($root_rule, 'Require all denied')
    || !str_contains($root_rule, 'Deny from all')
    || !str_contains($root_rule, 'LimitRequestBody 33554432')
    || substr_count(strtolower($root_rule), '<filesmatch') !== substr_count(strtolower($root_rule), '</filesmatch>')
    || preg_match('/<\\/files>/i', $root_rule) === 1
    || preg_match('/<files\\s+~/i', $root_rule) === 1) {
    return false;
  }
  if (preg_match_all('/<FilesMatch "([^"]+)">/i', $root_rule, $file_matches) < 1) return false;
  $private_file_pattern = end($file_matches[1]);
  if (!is_string($private_file_pattern)) return false;
  $private_file_regex = '#' . str_replace('#', '\\#', $private_file_pattern) . '#';
  foreach ([
    'config.local.php~',
    'theme.php.bak',
    'theme_conf.php.old',
    'theme_manifest.php~',
    'main.twig.bak',
    'main.twig~',
    'main.blade.php.bak',
    'database.db-wal',
  ] as $private_file) {
    if (preg_match($private_file_regex, $private_file) !== 1) return false;
  }
  foreach (['theme.css', 'thumbnail.png', 'readme.txt'] as $public_file) {
    if (preg_match($private_file_regex, $public_file) === 1) return false;
  }
  $ignore = file_get_contents(dirname(__DIR__) . '/.gitignore');
  if (!is_string($ignore) || !str_contains($ignore, 'config.local.php')
    || preg_match('/^config\.php$/m', $ignore) === 1) return false;
  foreach (['session', 'cache', 'backup', 'errorlog', 'auditlog', 'tmp'] as $directory) {
    $rule = file_get_contents(dirname(__DIR__) . "/noreita/{$directory}/.htaccess");
    if (!is_string($rule)
      || !str_contains($rule, 'Require all denied')
      || !str_contains($rule, 'Deny from all')) {
      return false;
    }
  }
  return true;
});

smoke_test('drawing save requests enforce file and aggregate capacity limits', static function (): bool {
  PaintSaveRequestGuard::assertWithinLimits(
    ['CONTENT_LENGTH' => '1800'],
    ['tool' => 'neo', 'header' => 'stime=1'],
    ['picture' => ['error' => UPLOAD_ERR_OK, 'size' => 900, 'tmp_name' => '']],
    'neo',
    1000,
    2000,
    3000
  );

  $rejected = static function (callable $request, int $expected_status): bool {
    try {
      $request();
      return false;
    } catch (PaintSaveCapacityException $e) {
      return $e->getCode() === $expected_status;
    }
  };

  return $rejected(static fn () => PaintSaveRequestGuard::assertWithinLimits(
      ['CONTENT_LENGTH' => '3001'], [], [], 'neo', 1000, 2000, 3000
    ), 413)
    && $rejected(static fn () => PaintSaveRequestGuard::assertWithinLimits(
      [], [], ['picture' => ['error' => UPLOAD_ERR_OK, 'size' => 1001, 'tmp_name' => '']],
      'neo', 1000, 2000, 3000
    ), 413)
    && $rejected(static fn () => PaintSaveRequestGuard::assertWithinLimits(
      [], [], [
        'picture' => ['error' => UPLOAD_ERR_OK, 'size' => 900, 'tmp_name' => ''],
        'pch' => ['error' => UPLOAD_ERR_OK, 'size' => 1500, 'tmp_name' => ''],
      ], 'neo', 1000, 2000, 2000
    ), 413)
    && $rejected(static fn () => PaintSaveRequestGuard::assertWithinLimits(
      [], [], ['picture' => ['error' => UPLOAD_ERR_INI_SIZE, 'size' => 0, 'tmp_name' => '']],
      'neo', 1000, 2000, 3000
    ), 413)
    && $rejected(static fn () => PaintSaveRequestGuard::assertWithinLimits(
      [], [], ['psd' => ['error' => UPLOAD_ERR_OK, 'size' => 1, 'tmp_name' => '']],
      'neo', 1000, 2000, 3000
    ), 400)
    && (static function (): bool {
      PaintSaveRequestGuard::assertWithinLimits(
        [], [], [
          'picture' => ['error' => UPLOAD_ERR_OK, 'size' => 900, 'tmp_name' => ''],
          'chibifile' => ['error' => UPLOAD_ERR_OK, 'size' => 1500, 'tmp_name' => ''],
          'swatches' => ['error' => UPLOAD_ERR_OK, 'size' => 100, 'tmp_name' => ''],
        ], 'chi', 1000, 2000, 3000
      );
      return true;
    })()
    && $rejected(static fn () => PaintSaveRequestGuard::assertWithinLimits(
      ['CONTENT_LENGTH' => '100'], [], [], 'neo', 1000, 2000, 3000
    ), 413)
    && $rejected(static fn () => PaintSaveRequestGuard::assertImageDimensions(
      801, 600, 800, 800
    ), 413)
    && $rejected(static fn () => PaintSaveRequestGuard::assertImageDimensions(
      5000, 5000, 10000, 10000
    ), 413);
});

smoke_test('drawing and Misskey API errors use the centralized logging responder', static function (): bool {
  $save = file_get_contents(dirname(__DIR__) . '/noreita/save.inc.php');
  $misskey_note = file_get_contents(dirname(__DIR__) . '/noreita/misskey_note.inc.php');
  $misskey_api = file_get_contents(dirname(__DIR__) . '/noreita/connect_misskey_api.php');
  if (!is_string($save) || !is_string($misskey_note) || !is_string($misskey_api)) return false;
  return str_contains($save, 'ApplicationErrorHandler::respondPlainError(')
    && str_contains($misskey_api, 'ApplicationErrorHandler::respondPlainError(')
    && !str_contains($misskey_api, "'Misskey upload source image was missing: ' . \$imagePath")
    && !preg_match('/\bdie\s*\(\s*[\'\"]Error:/i', $save)
    && !preg_match('/\bdie\s*\(\s*[\'\"]Error:/i', $misskey_note)
    && !preg_match('/\bdie\s*\(\s*[\'\"]Error:/i', $misskey_api);
});

smoke_test('request client IP only trusts forwarding headers from configured proxies', static function (): bool {
  return RequestInfo::clientIp(['REMOTE_ADDR' => '203.0.113.10']) === '203.0.113.10'
    && RequestInfo::clientIp([
      'HTTP_CLIENT_IP' => '198.51.100.10',
      'HTTP_X_FORWARDED_FOR' => '198.51.100.20',
      'REMOTE_ADDR' => '192.0.2.10',
    ]) === '192.0.2.10'
    && RequestInfo::clientIp([
      'HTTP_CLIENT_IP' => '198.51.100.10',
      'HTTP_X_FORWARDED_FOR' => '198.51.100.20',
      'REMOTE_ADDR' => '192.0.2.10',
    ], ['192.0.2.10']) === '198.51.100.20'
    && RequestInfo::clientIp([
      'HTTP_X_FORWARDED_FOR' => '198.51.100.20, 10.20.30.40, 192.0.2.20',
      'REMOTE_ADDR' => '192.0.2.10',
    ], ['192.0.2.0/24', '10.0.0.0/8']) === '198.51.100.20'
    && RequestInfo::clientIp([
      'HTTP_X_FORWARDED_FOR' => '198.51.100.99, 203.0.113.20',
      'REMOTE_ADDR' => '192.0.2.10',
    ], ['192.0.2.10']) === '203.0.113.20'
    && RequestInfo::clientIp([
      'HTTP_X_FORWARDED_FOR' => 'invalid, 198.51.100.20',
      'REMOTE_ADDR' => '192.0.2.10',
    ], ['192.0.2.10']) === '192.0.2.10'
    && RequestInfo::clientIp([
      'HTTP_CLIENT_IP' => '198.51.100.10',
      'REMOTE_ADDR' => '192.0.2.10',
    ], ['192.0.2.10']) === '192.0.2.10'
    && RequestInfo::clientIp([
      'HTTP_X_FORWARDED_FOR' => '2001:db8:ffff::20',
      'REMOTE_ADDR' => '2001:db8:1234::10',
    ], ['2001:db8:1234::/48']) === '2001:db8:ffff::20'
    && RequestInfo::clientIp([
      'HTTP_X_FORWARDED_FOR' => '198.51.100.20',
      'REMOTE_ADDR' => 'invalid',
    ], ['0.0.0.0/0']) === '';
});

smoke_test('administrator session validates password changes and idle timeout', static function (): bool {
  $now = 1_700_000_000;
  $session = [
    'admin_auth_fingerprint' => AdminAuth::sessionFingerprint('admin-secret'),
    'admin_auth_last_activity' => $now - 60,
  ];
  return AdminAuth::hasValidSession($session, 'admin-secret', 1800, $now)
    && !AdminAuth::hasValidSession($session, 'changed-secret', 1800, $now)
    && !AdminAuth::hasValidSession($session, 'admin-secret', 30, $now)
    && !AdminAuth::hasValidSession($session, 'admin-secret', 1800, $now - 120);
});

smoke_test('legacy administrator session secrets use constant-time comparison', static function (): bool {
  return AdminAuth::secondaryPasswordMatches('smoke-secondary-admin-secret', 'smoke-secondary-admin-secret')
    && !AdminAuth::secondaryPasswordMatches('not-the-secondary-admin-secret', 'smoke-secondary-admin-secret')
    && !AdminAuth::secondaryPasswordMatches('', 'smoke-secondary-admin-secret')
    && !AdminAuth::secondaryPasswordMatches('smoke-secondary-admin-secret', '');
});

smoke_test('administrator login rate limit locks by IP, clears after success, and removes expired records', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_admin_limit_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) return false;
  try {
    $limiter = new AdminLoginRateLimiter($directory, 'admin-secret', 3, 60, 120);
    if ($limiter->recordFailure('192.0.2.10', 100) !== 0
      || $limiter->recordFailure('192.0.2.10', 101) !== 0
      || $limiter->recordFailure('192.0.2.10', 102) !== 120
      || $limiter->retryAfter('192.0.2.10', 103) !== 119
      || $limiter->retryAfter('192.0.2.11', 103) !== 0) {
      return false;
    }
    $records = glob($directory . DIRECTORY_SEPARATOR . 'admin-login-*.json') ?: [];
    if (count($records) !== 1) return false;
    $stored = file_get_contents($records[0]);
    if (!is_string($stored) || str_contains($stored, '192.0.2.10') || str_contains($stored, 'admin-secret')) {
      return false;
    }
    $limiter->clear('192.0.2.10');
    if ($limiter->retryAfter('192.0.2.10', 103) !== 0) return false;

    $limiter->recordFailure('192.0.2.20', 200);
    if ($limiter->recordFailure('192.0.2.20', 261) !== 0) return false;

    $before = glob($directory . DIRECTORY_SEPARATOR . 'admin-login-*.json') ?: [];
    $limiter->recordFailure('192.0.2.30', 300);
    $after = glob($directory . DIRECTORY_SEPARATOR . 'admin-login-*.json') ?: [];
    $expired_records = array_values(array_diff($after, $before));
    if (count($expired_records) !== 1 || !touch($expired_records[0], 100)) return false;

    $before = $after;
    $limiter->recordFailure('192.0.2.31', 300);
    $after = glob($directory . DIRECTORY_SEPARATOR . 'admin-login-*.json') ?: [];
    $fresh_records = array_values(array_diff($after, $before));
    if (count($fresh_records) !== 1 || !touch($fresh_records[0], 400)) return false;

    clearstatcache();
    return $limiter->cleanupExpired(500) === 1
      && !is_file($expired_records[0])
      && is_file($fresh_records[0]);
  } finally {
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('expired PHP session files are cleaned with active and unrelated files preserved', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_sessions_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) return false;
  $files = [
    'sess_expiredA' => 100,
    'sess_expiredB' => 100,
    'sess_current' => 100,
    'sess_locked' => 100,
    'sess_fresh' => 450,
    'sess_bad.name' => 100,
    'admin-login-test.json' => 100,
    '.htaccess' => 100,
  ];
  $locked_handle = null;
  try {
    foreach ($files as $name => $modified) {
      $path = $directory . DIRECTORY_SEPARATOR . $name;
      file_put_contents($path, $name);
      touch($path, $modified);
    }
    $locked_handle = fopen($directory . DIRECTORY_SEPARATOR . 'sess_locked', 'r+');
    if ($locked_handle === false || !flock($locked_handle, LOCK_EX | LOCK_NB)) return false;

    $first_removed = SessionFileCleaner::cleanup($directory, 100, 'current', 1, 500);
    $expired_remaining = array_filter(
      ['sess_expiredA', 'sess_expiredB'],
      static fn(string $name): bool => is_file($directory . DIRECTORY_SEPARATOR . $name)
    );
    if ($first_removed !== 1
      || count($expired_remaining) !== 1
      || !is_file($directory . DIRECTORY_SEPARATOR . 'sess_locked')) {
      return false;
    }

    flock($locked_handle, LOCK_UN);
    fclose($locked_handle);
    $locked_handle = null;
    $second_removed = SessionFileCleaner::cleanup($directory, 100, 'current', 100, 500);
    return $second_removed === 2
      && !is_file($directory . DIRECTORY_SEPARATOR . 'sess_expiredA')
      && !is_file($directory . DIRECTORY_SEPARATOR . 'sess_expiredB')
      && !is_file($directory . DIRECTORY_SEPARATOR . 'sess_locked')
      && is_file($directory . DIRECTORY_SEPARATOR . 'sess_current')
      && is_file($directory . DIRECTORY_SEPARATOR . 'sess_fresh')
      && is_file($directory . DIRECTORY_SEPARATOR . 'sess_bad.name')
      && is_file($directory . DIRECTORY_SEPARATOR . 'admin-login-test.json')
      && is_file($directory . DIRECTORY_SEPARATOR . '.htaccess');
  } finally {
    if (is_resource($locked_handle)) {
      flock($locked_handle, LOCK_UN);
      fclose($locked_handle);
    }
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
      if (is_file($file)) unlink($file);
    }
    $hidden_rule = $directory . DIRECTORY_SEPARATOR . '.htaccess';
    if (is_file($hidden_rule)) unlink($hidden_rule);
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('Blade include names match template filename case', static function (): bool {
  $theme = dirname(__DIR__) . '/noreita/theme/monoreita';
  $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($theme));
  foreach ($iterator as $file) {
    if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) continue;
    $source = file_get_contents($file->getPathname());
    if (!is_string($source)) return false;
    preg_match_all("/@include\\(['\"]([^'\"]+)['\"]/", $source, $matches);
    foreach ($matches[1] as $include) {
      $path = $theme . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $include) . '.blade.php';
      if (!is_file($path)) {
        throw new RuntimeException("missing template {$include} referenced by {$file->getFilename()}");
      }
    }
  }
  return true;
});

smoke_test('SQLite read and write', static function (): bool {
  $db = new PDO('sqlite::memory:');
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $db->exec('CREATE TABLE smoke (id INTEGER PRIMARY KEY, value TEXT NOT NULL)');
  $statement = $db->prepare('INSERT INTO smoke (value) VALUES (:value)');
  $statement->execute(['value' => 'noReita']);
  return $db->query('SELECT value FROM smoke')->fetchColumn() === 'noReita';
});

smoke_test('SQLite connections wait for a temporary write lock', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_busy_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) return false;
  $database_file = $directory . DIRECTORY_SEPARATOR . 'busy.db';
  $ready_file = $directory . DIRECTORY_SEPARATOR . 'ready';
  $process = null;
  $pipes = [];

  try {
    $database = Database::connect('sqlite:' . $database_file, 1500);
    $database->exec('CREATE TABLE busy_test (value TEXT NOT NULL)');
    if ((int)$database->query('PRAGMA busy_timeout')->fetchColumn() !== 1500) return false;
    $database = null;

    $lock_script = <<<'PHP'
$db = new PDO('sqlite:' . $argv[1]);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA journal_mode=WAL');
$db->exec('BEGIN IMMEDIATE');
file_put_contents($argv[2], 'ready');
usleep(350000);
$db->exec('COMMIT');
PHP;
    $process = proc_open(
      [PHP_BINARY, '-r', $lock_script, $database_file, $ready_file],
      [STDIN, ['pipe', 'w'], ['pipe', 'w']],
      $pipes
    );
    if (!is_resource($process)) return false;

    $deadline = microtime(true) + 3;
    while (!is_file($ready_file) && microtime(true) < $deadline) usleep(10000);
    if (!is_file($ready_file)) return false;

    $started = microtime(true);
    $waiting_database = Database::connect('sqlite:' . $database_file, 1500);
    $waiting_database->exec("INSERT INTO busy_test VALUES ('written-after-lock')");
    $elapsed = microtime(true) - $started;
    $stored = $waiting_database->query('SELECT value FROM busy_test')->fetchColumn();
    $waiting_database = null;

    foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
    $pipes = [];
    $exit_code = proc_close($process);
    $process = null;

    $invalid_timeout_rejected = false;
    try {
      Database::connect('sqlite:' . $database_file, -1);
    } catch (InvalidArgumentException $e) {
      $invalid_timeout_rejected = true;
    }
    return $exit_code === 0
      && $stored === 'written-after-lock'
      && $elapsed >= 0.2
      && $invalid_timeout_rejected;
  } finally {
    foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
    if (is_resource($process)) {
      proc_terminate($process);
      proc_close($process);
    }
    foreach ([$ready_file, $database_file, $database_file . '-wal', $database_file . '-shm'] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('database migration and backup', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_db_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) {
    throw new RuntimeException('could not create temporary directory');
  }
  $database_file = $directory . DIRECTORY_SEPARATOR . 'smoke.db';
  $backup_dir = $directory . DIRECTORY_SEPARATOR . 'backup';

  try {
    $db = new PDO('sqlite:' . $database_file);
    $migrator = new DatabaseMigrator($db, $database_file, $backup_dir);
    if ($migrator->migrate() !== null || $migrator->schemaVersion() !== DatabaseMigrator::SCHEMA_VERSION) {
      return false;
    }

    $db->exec("INSERT INTO board_log (com) VALUES ('preserved')");
    $db->exec('PRAGMA user_version = 0');
    $backup = $migrator->migrate();
    if ($backup === null || !is_file($backup) || $migrator->schemaVersion() !== DatabaseMigrator::SCHEMA_VERSION) {
      return false;
    }

    $backup_db = new PDO('sqlite:' . $backup);
    return $backup_db->query('SELECT com FROM board_log')->fetchColumn() === 'preserved'
      && (fileperms($backup) & 0777) === 0600;
  } finally {
    foreach (glob($directory . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . '*.db') ?: [] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_file($database_file)) unlink($database_file);
    if (is_dir($backup_dir)) rmdir($backup_dir);
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('legacy SQLite database backup', static function (): bool {
  if (!class_exists('SQLite3')) return true;

  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_legacy_db_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) return false;
  $database_file = $directory . DIRECTORY_SEPARATOR . 'source.db';
  $backup_file = $directory . DIRECTORY_SEPARATOR . 'backup.db';

  try {
    $db = new PDO('sqlite:' . $database_file);
    $db->exec('CREATE TABLE backup_test (value TEXT NOT NULL)');
    $db->exec("INSERT INTO backup_test VALUES ('preserved')");
    $migrator = new DatabaseMigrator($db, $database_file, $directory);
    $method = new ReflectionMethod($migrator, 'createLegacyBackup');
    $method->setAccessible(true);
    $method->invoke($migrator, $backup_file);

    $backup = new PDO('sqlite:' . $backup_file);
    return $backup->query('SELECT value FROM backup_test')->fetchColumn() === 'preserved';
  } finally {
    foreach ([$database_file, $database_file . '-wal', $database_file . '-shm', $backup_file] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('application initialization prepares runtime state', static function (): bool {
  $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_init_' . bin2hex(random_bytes(8));
  if (!mkdir($root, 0700)) return false;
  $database_file = $root . DIRECTORY_SEPARATOR . 'board.db';
  $backup_dir = $root . DIRECTORY_SEPARATOR . 'backup';
  $public_image = $root . DIRECTORY_SEPARATOR . 'img';
  $public_temp = $root . DIRECTORY_SEPARATOR . 'temp';
  $private_session = $root . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'session';
  $directories = [
    $public_image => 0755,
    $public_temp => 0755,
    $private_session => 0700,
  ];
  try {
    $initializer = new ApplicationInitializer(
      'sqlite:' . $database_file, $database_file, $backup_dir, $root, $directories, 0600, $public_temp
    );
    $initializer->prepareDirectories();
    $temporary_rule = $public_temp . DIRECTORY_SEPARATOR . '.htaccess';
    $temporary_rule_contents = file_get_contents($temporary_rule);
    if (!is_string($temporary_rule_contents)
      || file_put_contents($temporary_rule, str_replace("\n", "\r\n", $temporary_rule_contents)) === false) {
      return false;
    }
    // Windowsで転送・生成されたCRLFの規則も、同じ安全な内容として受け入れる。
    $initializer->prepareDirectories();
    $initializer->migrateDatabase();
    $initializer->secureDatabaseFile();
    $database = new PDO('sqlite:' . $database_file);
    $schema_version = (int)$database->query('PRAGMA user_version')->fetchColumn();
    $database = null;
    $unsafe_permission_rejected = false;
    try {
      (new ApplicationInitializer(
        'sqlite:' . $database_file, $database_file, $backup_dir, $root, [$public_image => 0777]
      ))->prepareDirectories();
    } catch (RuntimeException $e) {
      $unsafe_permission_rejected = $e->getMessage() === 'Invalid directory permission configuration.';
    }
    $read_only_root_supported = chmod($root, 0555);
    if ($read_only_root_supported) {
      clearstatcache(true, $root);
      $read_only_root_supported = (fileperms($root) & 0777) === 0555;
      $initializer->prepareDirectories();
      chmod($root, 0700);
    }
    $security_headers = ApplicationInitializer::securityHeaders();
    return !in_array('X-XSS-Protection: 1; mode=block', $security_headers, true)
      && in_array('X-Content-Type-Options: nosniff', $security_headers, true)
      && in_array('X-Frame-Options: DENY', $security_headers, true)
      && $schema_version === DatabaseMigrator::SCHEMA_VERSION
      && $unsafe_permission_rejected
      && $read_only_root_supported
      && !array_filter(array_keys($directories), static fn(string $directory): bool => !is_dir($directory))
      && (fileperms($public_image) & 0777) === 0755
      && (fileperms($public_temp) & 0777) === 0755
      && str_contains((string)file_get_contents($temporary_rule), 'Require all denied')
      && (fileperms($private_session) & 0777) === 0700
      && (fileperms($database_file) & 0777) === 0600;
  } finally {
    if (is_dir($root)) chmod($root, 0700);
    foreach ([$database_file, $database_file . '-wal', $database_file . '-shm'] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_dir($backup_dir)) rmdir($backup_dir);
    if (is_dir($private_session)) rmdir($private_session);
    if (is_dir($root . DIRECTORY_SEPARATOR . 'nested')) rmdir($root . DIRECTORY_SEPARATOR . 'nested');
    foreach ([$public_image, $public_temp] as $directory) {
      $rule = $directory . DIRECTORY_SEPARATOR . '.htaccess';
      if (is_file($rule)) unlink($rule);
      if (is_dir($directory)) rmdir($directory);
    }
    if (is_dir($root)) rmdir($root);
  }
});

smoke_test('Windows does not enforce unsupported POSIX permission modes', static function (): bool {
  return !FilesystemPermissions::modeChecksAreReliable('Windows')
    && !FilesystemPermissions::modeChecksAreReliable('WINDOWS')
    && FilesystemPermissions::modeChecksAreReliable('Linux')
    && FilesystemPermissions::modeChecksAreReliable('Darwin');
});

smoke_test('version 2 database is not modified automatically', static function (): bool {
  $database_file = tempnam(sys_get_temp_dir(), 'noreita_v2_');
  if ($database_file === false) {
    throw new RuntimeException('could not create temporary database');
  }
  $backup_dir = $database_file . '_backup';

  try {
    $db = new PDO('sqlite:' . $database_file);
    $db->exec('CREATE TABLE tlog (tid INTEGER PRIMARY KEY)');
    $migrator = new DatabaseMigrator($db, $database_file, $backup_dir);
    try {
      $migrator->migrate();
    } catch (RuntimeException $e) {
      return str_contains($e->getMessage(), 'Version 2')
        && (int)$db->query('SELECT COUNT(*) FROM tlog')->fetchColumn() === 0
        && !is_dir($backup_dir);
    }
    return false;
  } finally {
    if (is_file($database_file)) unlink($database_file);
    if (is_dir($backup_dir)) rmdir($backup_dir);
  }
});

smoke_test('failed database operation is rolled back', static function (): bool {
  $db = new PDO('sqlite::memory:');
  $migrator = new DatabaseMigrator($db, ':memory:', sys_get_temp_dir());
  $transaction = new ReflectionMethod($migrator, 'transaction');
  $transaction->setAccessible(true);

  try {
    $transaction->invoke($migrator, static function () use ($db): void {
      $db->exec('CREATE TABLE should_rollback (id INTEGER)');
      throw new RuntimeException('expected failure');
    });
  } catch (RuntimeException $e) {
    $exists = (int)$db->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'should_rollback'")->fetchColumn();
    return $e->getMessage() === 'expected failure' && $exists === 0 && !$db->inTransaction();
  }
  return false;
});

smoke_test('administrator pagination keeps replies with their parent thread', static function (): bool {
  $db = new PDO('sqlite::memory:');
  (new DatabaseMigrator($db, ':memory:', sys_get_temp_dir()))->migrate();
  $repository = new BoardRepository($db);
  $insert = static function (int $thread, ?int $parent, int $tree, string $subject) use ($repository): int {
    return $repository->insertPost([
      'thread' => $thread, 'parent' => $parent, 'tree' => $tree,
      'sub' => $subject, 'com' => '本文', 'a_name' => '名前',
      'pwd' => password_hash('pass', PASSWORD_DEFAULT), 'picfile' => '',
      'invz' => 0, 'age' => $tree,
    ]);
  };
  $old = $insert(1, null, 100, '古い親');
  $middle = $insert(1, null, 200, '中間の親');
  $new = $insert(1, null, 300, '新しい親');
  $reply_one = $insert(0, $middle, 201, '中間のレス1');
  $reply_two = $insert(0, $middle, 202, '中間のレス2');
  $db->exec("UPDATE board_log SET picfile='reply.png', nsfw=1, invz=1, admins=1 WHERE tid={$reply_two}");

  $page = $repository->listAdminThreads(1, 1);
  $replies = $repository->listAdminReplies(array_column($page, 'tid'));
  $page_ids = array_map(static fn(array $row): int => (int)$row['tid'], $page);
  $reply_filter = AdminPostFilter::normalize(['q' => 'レス1', 'type' => 'reply']);
  $filtered_page = $repository->listAdminThreads(0, 10, $reply_filter);
  $filter_valid = $repository->countAdminPosts($reply_filter) === 1
    && $repository->countAdminThreads($reply_filter) === 1
    && count($filtered_page) === 1 && (int)$filtered_page[0]['tid'] === $middle
    && !AdminPostFilter::matches($filtered_page[0], $reply_filter)
    && AdminPostFilter::matches($repository->findPost($reply_one), $reply_filter)
    && str_contains(AdminPostFilter::query($reply_filter), 'q=%E3%83%AC%E3%82%B91');
  try {
    AdminPostFilter::normalize(['date_from' => '2026-02-30']);
    return false;
  } catch (InvalidArgumentException $e) {
  }

  $stats = $repository->adminDashboardStats();
  return $filter_valid && $repository->countAdminPosts() === 5
    && $repository->countAdminThreads() === 3
    && $stats['total'] === 5 && $stats['threads'] === 3 && $stats['replies'] === 2
    && $stats['images'] === 1 && $stats['nsfw'] === 1 && $stats['hidden'] === 1
    && $stats['administrators'] === 1 && $stats['today'] === 5
    && $stats['last_7_days'] === 5 && $stats['last_30_days'] === 5
    && count($page) === 1 && (int)$page[0]['tid'] === $middle
    && array_map(static fn(array $row): int => (int)$row['tid'], $replies) === [$reply_one, $reply_two]
    && !in_array($old, $page_ids, true)
    && !in_array($new, $page_ids, true);
});

smoke_test('UUIDv7 format and uniqueness', static function (): bool {
  $first = generate_uuid();
  $second = generate_uuid();
  $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
  return $first !== $second && preg_match($pattern, $first) === 1 && preg_match($pattern, $second) === 1;
});

smoke_test('escaping and NG-word helpers', static function (): bool {
  return h('<script>') === '&lt;script&gt;'
    && t("a\tb") === 'ab'
    && s('<b>text</b>') === 'text'
    && is_ngword(['spam'], ['safe', 'contains spam'])
    && !is_ngword(['spam'], 'safe');
});

smoke_test('post validation is independent from HTTP rendering', static function (): bool {
  $input = [
    'sub' => '題名', 'name' => '名前', 'mail' => '', 'url' => '', 'com' => '本文です',
    'pwd' => 'secret', 'resto' => '',
  ];
  $rules = [
    'en' => false, 'request_method' => 'POST', 'host' => 'client.example.com',
    'blocked_hosts' => [], 'require_name' => true, 'require_comment' => true,
    'require_subject' => true, 'max_comment' => 100, 'max_name' => 100,
    'max_email' => 100, 'max_subject' => 100, 'max_url' => 100,
    'japanese_filter' => true, 'deny_comment_urls' => true, 'is_admin' => false,
    'bad_strings' => ['禁止語'], 'bad_names' => ['使用禁止名'],
    'bad_strings_a' => ['激安'], 'bad_strings_b' => ['ブランド'],
  ];
  PostValidator::validate($input, $rules);

  $invalid_cases = [
    [array_merge($input, ['com' => '']), $rules, '本文は必須です。'],
    [array_merge($input, ['com' => 'https://example.com']), array_merge($rules, ['japanese_filter' => false]), 'コメントにはURLを含めることはできません。'],
    [array_merge($input, ['name' => '使用禁止名']), $rules, '無効な名前が使用されています。'],
    [$input, array_merge($rules, ['host' => 'blocked.example.com', 'blocked_hosts' => ['blocked\\.example\\.com']]), 'あなたのホストは拒絶されています。'],
  ];
  foreach ($invalid_cases as [$invalid_input, $invalid_rules, $expected]) {
    try {
      PostValidator::validate($invalid_input, $invalid_rules);
      return false;
    } catch (PostValidationException $e) {
      if ($e->getMessage() !== $expected) return false;
    }
  }
  try {
    PostValidator::validate(
      array_merge($input, ['com' => 'https://example.com', 'pwd' => 'admin']),
      array_merge($rules, ['japanese_filter' => false])
    );
    return false;
  } catch (PostValidationException $e) {
    if ($e->getMessage() !== 'コメントにはURLを含めることはできません。') return false;
  }
  PostValidator::validate(
    array_merge($input, ['com' => 'https://example.com']),
    array_merge($rules, ['japanese_filter' => false, 'is_admin' => true])
  );
  return true;
});

smoke_test('ctype input sources are resolved in priority order', static function (): bool {
  return PostInput::resolveCtype([
      'direct' => 'img', 'usercode' => 'ctype=pch', 'http_usercode' => 'ctype=spch',
    ]) === 'img'
    && PostInput::resolveCtype(['usercode' => 'foo=bar&ctype=pch']) === 'pch'
    && PostInput::resolveCtype(['send_header' => 'usercode=' . rawurlencode('foo=bar&ctype=spch')]) === 'spch'
    && PostInput::resolveCtype(['http_usercode' => 'ctype=img']) === 'img'
    && PostInput::resolveCtype(['session_usercode' => 'ctype=pch']) === 'pch'
    && PostInput::resolveCtype(['direct' => '../invalid', 'usercode' => 'ctype=invalid']) === 'new';
});

smoke_test('post service centralizes edit and delete authorization', static function (): bool {
  $image_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_post_service_' . bin2hex(random_bytes(8));
  if (!mkdir($image_dir, 0700)) return false;
  try {
    $db = new PDO('sqlite::memory:');
    (new DatabaseMigrator($db, ':memory:', sys_get_temp_dir()))->migrate();
    $repository = new BoardRepository($db);
    $insert = static function (string $subject, string $password, string $image = '') use ($repository): int {
      return $repository->insertPost([
        'thread' => 1, 'sub' => $subject, 'com' => '本文', 'a_name' => '名前',
        'pwd' => password_hash($password, PASSWORD_DEFAULT), 'picfile' => $image,
        'invz' => 0, 'age' => 0, 'tree' => time(),
      ]);
    };
    $edit_id = $insert('編集前', 'owner-pass');
    $hide_id = $insert('非表示対象', 'another-pass');
    $delete_id = $insert('削除対象', 'delete-pass', 'owner.png');
    file_put_contents($image_dir . DIRECTORY_SEPARATOR . 'owner.png', 'image');
    $service = new PostService($repository, $image_dir);

    try {
      $service->edit($edit_id, 'wrong-pass', []);
      return false;
    } catch (PostAuthorizationException $e) {
    }
    try {
      $service->edit($edit_id, 'admin-pass', []);
      return false;
    } catch (PostAuthorizationException $e) {
    }
    $service->edit($edit_id, 'owner-pass', [
      'name' => '編集者', 'mail' => '', 'sub' => '編集後', 'com' => '編集本文',
      'url' => '', 'host' => 'localhost', 'sodane' => 0,
    ]);
    if (($repository->findPost($edit_id)['sub'] ?? '') !== '編集後') return false;

    try {
      $service->delete($hide_id, 'admin-pass', false);
      return false;
    } catch (PostAuthorizationException $e) {
    }
    if ($service->setVisibilityManyAsAdmin([$hide_id], false) !== 1
      || (int)($repository->findPost($hide_id)['invz'] ?? 1) !== 0
      || $service->setVisibilityManyAsAdmin([$hide_id, $hide_id], true) !== 1
      || (int)($repository->findPost($hide_id)['invz'] ?? 0) !== 1) return false;
    if ($service->delete($delete_id, 'delete-pass', false) !== 'deleted'
      || $repository->findPost($delete_id) !== false
      || is_file($image_dir . DIRECTORY_SEPARATOR . 'owner.png')) return false;

    $batch_parent = $insert('一括削除親', 'parent-pass', 'batch-parent.png');
    $batch_reply = $repository->insertPost([
      'thread' => 0, 'parent' => $batch_parent, 'sub' => '一括削除レス', 'com' => '本文',
      'a_name' => '名前', 'pwd' => password_hash('reply-pass', PASSWORD_DEFAULT),
      'picfile' => 'batch-reply.png', 'invz' => 0, 'age' => 0, 'tree' => time(),
    ]);
    $batch_other = $insert('一括削除別記事', 'other-pass', 'batch-other.png');
    foreach (['batch-parent.png', 'batch-reply.png', 'batch-other.png'] as $image) {
      file_put_contents($image_dir . DIRECTORY_SEPARATOR . $image, 'image');
    }
    if ($service->deleteManyAsAdmin([$batch_parent, $batch_reply, $batch_other, $batch_other, 'invalid']) !== 3
      || $repository->findPost($batch_parent) !== false
      || $repository->findPost($batch_reply) !== false
      || $repository->findPost($batch_other) !== false) return false;
    foreach (['batch-parent.png', 'batch-reply.png', 'batch-other.png'] as $image) {
      if (is_file($image_dir . DIRECTORY_SEPARATOR . $image)) return false;
    }

    $new_input = [
      'name' => '投稿者#trip-secret', 'sub' => '新規題名', 'com' => '新規本文', 'mail' => '', 'url' => '',
      'picfile' => null, 'pwd' => 'new-pass', 'sodane' => 0, 'invz' => 0,
      'resto' => '', 'modid' => '',
    ];
    $settings = [
      'default_name' => '名無し', 'default_comment' => '本文なし', 'default_subject' => '無題',
      'admin_name' => '管理者', 'admin_cap' => '(ではない)', 'is_admin' => false,
    ];
    $spoofed_admin = $service->prepareNewPost(array_merge($new_input, [
      'name' => '管理者', 'com' => '管理者名の試行', 'pwd' => 'admin-pass',
    ]), 'new.example.com', $settings);
    if ($spoofed_admin['name'] !== '管理者(ではない)' || (int)$spoofed_admin['admins'] !== 0) return false;
    $session_admin = $service->prepareNewPost(array_merge($new_input, [
      'name' => '管理者', 'com' => '管理者セッションの投稿', 'pwd' => 'unrelated-post-pass',
    ]), 'new.example.com', array_merge($settings, ['is_admin' => true]));
    if ($session_admin['name'] !== '管理者' || (int)$session_admin['admins'] !== 1) return false;
    $prepared = $service->prepareNewPost($new_input, 'new.example.com', $settings);
    $new_id = $service->createPreparedPost($prepared, [
      'pchfile' => '', 'img_w' => 0, 'img_h' => 0, 'psec' => 0, 'utime' => '',
      'tool' => '', 'nsfw' => false, 'ctype' => null, 'thumbnail' => '',
    ]);
    $new_post = $repository->findPost($new_id);
    $trip_name = generate_trip('投稿者#trip-secret');
    if (($new_post['sub'] ?? '') !== '新規題名'
      || ($new_post['a_name'] ?? '') !== $trip_name
      || PostService::nameForEdit($trip_name, '投稿者#trip-secret', true) !== '投稿者#trip-secret'
      || PostService::nameForEdit($trip_name, '別人#trip-secret', true) !== $trip_name
      || PostService::nameForEdit($trip_name, '投稿者#trip-secret', false) !== $trip_name) {
      return false;
    }
    try {
      $service->prepareNewPost($new_input, 'new.example.com', $settings);
      return false;
    } catch (DuplicatePostException $e) {
    }
    $reply_input = array_merge($new_input, [
      'sub' => '返信題名', 'com' => '返信本文', 'resto' => (string)$new_id,
    ]);
    $reply = $service->prepareNewPost($reply_input, 'reply.example.com', $settings);
    $reply_id = $service->createPreparedPost($reply, [
      'pchfile' => '', 'img_w' => 0, 'img_h' => 0, 'psec' => 0, 'utime' => '',
      'tool' => '', 'nsfw' => false, 'ctype' => null, 'thumbnail' => '',
    ]);
    $reply_row = $repository->findPost($reply_id);
    $parent_row = $repository->findPost($new_id);
    if ((int)($reply_row['thread'] ?? 1) !== 0 || (int)($reply_row['parent'] ?? 0) !== $new_id
      || (int)($parent_row['age'] ?? 0) !== 1) return false;
    return true;
  } finally {
    foreach (glob($image_dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_dir($image_dir)) rmdir($image_dir);
  }
});

smoke_test('share service builds validated destination URLs', static function (): bool {
  $servers = ShareService::servers();
  if (end($servers) !== ['直接入力', 'direct']) return false;
  if (ShareService::buildShareUrl('https://x.com', '', '題名', 'https://example.com/post')
    !== 'https://twitter.com/intent/tweet?text=' . rawurlencode('題名 https://example.com/post')) return false;
  if (ShareService::buildShareUrl('direct', 'https://social.example/', 'title', 'https://example.com')
    !== 'https://social.example/share?text=' . rawurlencode('title https://example.com')) return false;
  foreach (['javascript:alert(1)', 'https://user:pass@example.com', 'https://example.com/?redirect=evil'] as $invalid) {
    try {
      ShareService::buildShareUrl('direct', $invalid, 'title', 'url');
      return false;
    } catch (InvalidArgumentException $e) {
    }
  }
  return true;
});

smoke_test('image MIME mapping', static function (): bool {
  return get_image_type('image/jpeg') === '.jpg'
    && get_image_type('image/png') === '.png'
    && get_image_type('image/webp') === '.webp'
    && get_image_type('image/avif') === '.avif';
});

smoke_test('image upload formats follow available GD decoders', static function (): bool {
  $without_avif = static fn(string $function): bool => $function !== 'imagecreatefromavif';
  $with_everything = static fn(string $function): bool => true;
  $limited = ImageService::supportedUploadFormats($without_avif);
  $complete = ImageService::supportedUploadFormats($with_everything);
  return !isset($limited['image/avif'])
    && isset($limited['image/png'], $limited['image/jpeg'], $limited['image/gif'], $limited['image/webp'])
    && isset($complete['image/avif'])
    && ImageService::uploadAccept($without_avif) === 'image/png,image/jpeg,image/gif,image/webp'
    && ImageService::uploadFormatLabel($without_avif) === 'PNG / JPEG / GIF / WebP'
    && str_ends_with(ImageService::uploadFormatLabel($with_everything), ' / AVIF');
});

smoke_test('image directory usage is counted and formatted', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_usage_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) return false;
  try {
    file_put_contents($directory . DIRECTORY_SEPARATOR . 'one.png', str_repeat('a', 1024));
    file_put_contents($directory . DIRECTORY_SEPARATOR . 'two.pch', str_repeat('b', 512));
    mkdir($directory . DIRECTORY_SEPARATOR . 'nested', 0700);
    file_put_contents($directory . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'ignored.png', 'ignored');
    $usage = ImageService::directoryUsage($directory);
    return $usage === ['files' => 2, 'bytes' => 1536]
      && ImageService::formatBytes($usage['bytes']) === '1.5 KiB'
      && ImageService::formatBytes(0) === '0 B';
  } finally {
    safe_unlink($directory . DIRECTORY_SEPARATOR . 'one.png');
    safe_unlink($directory . DIRECTORY_SEPARATOR . 'two.pch');
    safe_unlink($directory . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'ignored.png');
    if (is_dir($directory . DIRECTORY_SEPARATOR . 'nested')) rmdir($directory . DIRECTORY_SEPARATOR . 'nested');
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('animation filenames reject path traversal', static function (): bool {
  return ImageService::isSafeAnimationFilename('1712345678901234.pch')
    && ImageService::isSafeAnimationFilename('legacy-name_01.spch')
    && ImageService::isSafeAnimationFilename('drawing.tgkr')
    && !ImageService::isSafeAnimationFilename('../secret.pch')
    && !ImageService::isSafeAnimationFilename('subdir/secret.pch')
    && !ImageService::isSafeAnimationFilename('drawing.php')
    && !ImageService::isSafeAnimationFilename('drawing.chi')
    && !ImageService::isSafeAnimationFilename('.pch');
});

smoke_test('uploaded animation headers distinguish NEO PCH and Tegaki TGKR', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_animation_header_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) return false;
  $pch = $directory . DIRECTORY_SEPARATOR . 'sample.pch';
  $tgkr = $directory . DIRECTORY_SEPARATOR . 'sample.tgkr';
  $invalid = $directory . DIRECTORY_SEPARATOR . 'invalid.pch';
  try {
    file_put_contents($pch, "NEO\0" . pack('v', 640) . pack('v', 480) . "\0\0\0\0");
    file_put_contents($tgkr, 'TGK' . chr(1) . pack('N', 1024) . "\1\2\3\1");
    file_put_contents($invalid, 'OLD' . str_repeat("\0", 9));
    $neo = ImageService::animationUploadInfo($pch, 'client.PCH');
    $tegaki = ImageService::animationUploadInfo($tgkr, 'client.tgkr');
    if ($neo !== ['extension' => 'pch', 'tool' => 'neo', 'width' => 640, 'height' => 480]
      || $tegaki !== ['extension' => 'tgkr', 'tool' => 'tegaki', 'width' => 0, 'height' => 0]) {
      return false;
    }
    try {
      ImageService::animationUploadInfo($invalid, 'invalid.pch');
      return false;
    } catch (ImageUploadException $e) {
      return $e->getCode() === 422;
    }
  } finally {
    safe_unlink($pch);
    safe_unlink($tgkr);
    safe_unlink($invalid);
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('posted image filenames reject invalid continuation targets', static function (): bool {
  return ImageService::isSafePostedImageFilename('1784.png')
    && ImageService::isSafePostedImageFilename('drawing-name.webp')
    && !ImageService::isSafePostedImageFilename('1784')
    && !ImageService::isSafePostedImageFilename('../1784.png')
    && !ImageService::isSafePostedImageFilename('drawing.php');
});

smoke_test('temporary images are parsed, found, and cleaned up', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_temp_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) return false;
  $now = 1700000000;
  try {
    file_put_contents($directory . DIRECTORY_SEPARATOR . '100.png', 'image');
    file_put_contents($directory . DIRECTORY_SEPARATOR . '100.dat', "127.0.0.1\thost\tagent\t.png\tuser-a\treplace-a\t100\t160\t0\tneo");
    file_put_contents($directory . DIRECTORY_SEPARATOR . '200.png', 'image');
    file_put_contents($directory . DIRECTORY_SEPARATOR . '200.dat', "127.0.0.2\thost\tagent\t.png\tuser-b\treplace-b\t200\t230\t0\tklecks");
    file_put_contents($directory . DIRECTORY_SEPARATOR . 'orphan.dat', "127.0.0.3\thost\tagent\t.png\tuser-c\treplace-c\t0\t0\t0\tneo");

    $images = ImageService::listTemporaryImages($directory);
    $found = ImageService::findTemporaryImageByReplacementCode($directory, 'replace-b');
    if (count($images) !== 2 || $images[0]['filename'] !== '100.png'
      || $images[0]['paint_seconds'] !== 60 || $images[0]['tool'] !== 'neo'
      || $found === null || $found['base_name'] !== '200'
      || ImageService::findTemporaryImageByReplacementCode($directory, 'missing') !== null) {
      return false;
    }

    file_put_contents($directory . DIRECTORY_SEPARATOR . '100.pch', 'animation');
    file_put_contents($directory . DIRECTORY_SEPARATOR . '100.psd', 'working data');
    touch($directory . DIRECTORY_SEPARATOR . '100.png', $now - 86401);
    $entries = ImageService::temporaryImageEntries($directory, 1, $now);
    $entries_by_filename = [];
    foreach ($entries as $entry) $entries_by_filename[$entry['filename']] = $entry;
    $owner_image = ImageService::authorizedTemporaryImage($directory, '100.png', 'user-a', false);
    $other_user_image = ImageService::authorizedTemporaryImage($directory, '100.png', 'user-b', false);
    $admin_image = ImageService::authorizedTemporaryImage($directory, '100.png', '', true);
    $work_file = ImageService::authorizedTemporaryImage($directory, '100.pch', 'user-a', false);
    $deleted = ImageService::deleteTemporaryImages($directory, ['100.png', '../invalid.png']);
    if (count($entries) !== 2 || !isset($entries_by_filename['100.png']) || !$entries_by_filename['100.png']['expired']
      || $entries_by_filename['100.png']['related_files'] !== 4 || $deleted['images'] !== 1 || $deleted['files'] !== 4
      || $deleted['skipped'] !== 0 || is_file($directory . DIRECTORY_SEPARATOR . '100.png')
      || is_file($directory . DIRECTORY_SEPARATOR . '100.dat') || is_file($directory . DIRECTORY_SEPARATOR . '100.pch')
      || is_file($directory . DIRECTORY_SEPARATOR . '100.psd')
      || !is_array($owner_image) || $owner_image['mime_type'] !== 'image/png'
      || $other_user_image !== null || !is_array($admin_image) || $work_file !== null) {
      return false;
    }

    file_put_contents($directory . DIRECTORY_SEPARATOR . 'expired.tmp', 'old');
    file_put_contents($directory . DIRECTORY_SEPARATOR . 'pchup-test-tmp.pch', 'old upload');
    file_put_contents($directory . DIRECTORY_SEPARATOR . 'recent.tmp', 'recent');
    touch($directory . DIRECTORY_SEPARATOR . 'expired.tmp', $now - 86401);
    touch($directory . DIRECTORY_SEPARATOR . 'pchup-test-tmp.pch', $now - 301);
    touch($directory . DIRECTORY_SEPARATOR . 'recent.tmp', $now - 60);

    return ImageService::cleanupTemporaryFiles($directory, 1, $now) === 2
      && !is_file($directory . DIRECTORY_SEPARATOR . 'expired.tmp')
      && !is_file($directory . DIRECTORY_SEPARATOR . 'pchup-test-tmp.pch')
      && is_file($directory . DIRECTORY_SEPARATOR . 'recent.tmp');
  } finally {
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('animation playback data is built by the image service', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_playback_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) return false;
  try {
    $image = imagecreatetruecolor(120, 80);
    imagepng($image, $directory . DIRECTORY_SEPARATOR . 'drawing.png');
    file_put_contents($directory . DIRECTORY_SEPARATOR . 'drawing.pch', 'NEO animation');

    $data = ImageService::animationPlaybackData($directory, 'drawing.pch', 12);
    return $data['tool'] === 'neo' && $data['template_type'] === 'standard'
      && $data['picw'] === 120 && $data['pich'] === 80
      && $data['w'] === 300 && $data['h'] === 326
      && $data['pchfile'] === './drawing.pch'
      && $data['datasize'] === strlen('NEO animation') && $data['speed'] === 12;
  } finally {
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('external URL security boundaries', static function (): bool {
  return ExternalImageService::resolvePublicIp('127.0.0.1') === false
    && ExternalImageService::resolvePublicIp('192.168.1.1') === false
    && ExternalImageService::resolvePublicIp('169.254.169.254') === false
    && ExternalImageService::resolvePublicIp('::1') === false
    && ExternalImageService::resolveRedirectUrl('https://example.com/a/b.png', '../c.png') === 'https://example.com/c.png'
    && ExternalImageService::resolveRedirectUrl('https://example.com/a/b.png', "https://example.com/x\nInjected: yes") === false;
});

smoke_test('Misskey server URLs reject SSRF destinations', static function (): bool {
  return MisskeyServerSecurity::resolvePublicIp('127.0.0.1') === false
    && MisskeyServerSecurity::resolvePublicIp('169.254.169.254') === false
    && MisskeyServerSecurity::resolvePublicIp('93.184.216.34') === '93.184.216.34'
    && MisskeyServerSecurity::normalizeBaseUrl('https://127.0.0.1') === false
    && MisskeyServerSecurity::normalizeBaseUrl('https://169.254.169.254') === false
    && MisskeyServerSecurity::normalizeBaseUrl('https://localhost') === false
    && MisskeyServerSecurity::normalizeBaseUrl('http://misskey.io') === false
    && MisskeyServerSecurity::normalizeBaseUrl('https://user:pass@misskey.io') === false
    && MisskeyServerSecurity::normalizeBaseUrl('https://misskey.io:8443') === false
    && MisskeyServerSecurity::normalizeBaseUrl('https://misskey.io/api') === false
    && MisskeyServerSecurity::normalizeBaseUrl('https://misskey.io/?query=1') === false
    && MisskeyServerSecurity::normalizeBaseUrl('https://misskey.io/#fragment') === false
    && MisskeyServerSecurity::curlOptions('https://127.0.0.1') === false;
});

smoke_test('cached external image thumbnail link', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_external_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) return false;
  $url = 'https://example.com/picture.png';
  $thumbnail = $directory . DIRECTORY_SEPARATOR . md5($url) . '_thumb.jpg';
  try {
    file_put_contents($thumbnail, 'cached thumbnail');
    $service = new ExternalImageService($directory, 'thumbnail/', 200, 0600, 0700);
    $html = $service->addThumbnailLinks('image: ' . $url);
    return str_contains($html, 'href="' . $url . '"')
      && str_contains($html, 'src="thumbnail/' . basename($thumbnail) . '"');
  } finally {
    if (is_file($thumbnail)) unlink($thumbnail);
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('external image thumbnails use a stable cache filename and remove legacy files', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_external_cache_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) return false;
  $input = $directory . DIRECTORY_SEPARATOR . 'source.png';
  $output = null;
  $legacy = $directory . DIRECTORY_SEPARATOR . 'noreita_thumb_legacy.avif';
  try {
    $image = imagecreatetruecolor(4, 4);
    if ($image === false || !imagepng($image, $input)) return false;
    $thumbnail = new Thumbnail($input, $directory, 20, false, 'cached_thumb');
    if (!$thumbnail->createThumbnail()) return false;
    $output = $thumbnail->getOutputPath();
    $cached = $directory . DIRECTORY_SEPARATOR . 'cached_thumb.' . pathinfo((string)$output, PATHINFO_EXTENSION);
    if ($output !== $cached || !is_file($cached)) return false;
    if (file_put_contents($legacy, 'legacy') === false) return false;
    ExternalImageService::cleanupLegacyThumbnails($directory);
    return !is_file($legacy) && is_file($cached);
  } finally {
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('external image thumbnails limit URLs and cache failures briefly', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_external_limits_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) return false;
  try {
    $cached_urls = [
      'https://example.com/one.png',
      'https://example.com/two.jpg',
      'https://example.com/three.webp',
    ];
    foreach ($cached_urls as $url) {
      file_put_contents($directory . DIRECTORY_SEPARATOR . md5($url) . '_thumb.jpg', 'cached');
    }
    $service = new ExternalImageService($directory, 'thumbnail/', 200, 0600, 0700, 0600, 2, 3, 300);
    $html = $service->addThumbnailLinks(implode(' ', $cached_urls));
    if (substr_count($html, 'src="thumbnail/') !== 2
      || !str_contains($html, md5($cached_urls[0]) . '_thumb.jpg')
      || !str_contains($html, md5($cached_urls[1]) . '_thumb.jpg')
      || str_contains($html, md5($cached_urls[2]) . '_thumb.jpg')) return false;

    $failed_urls = [
      'http://127.0.0.1/blocked-one.png',
      'http://127.0.0.1/blocked-two.png',
      'http://127.0.0.1/blocked-three.png',
    ];
    $limited = new ExternalImageService($directory, 'thumbnail/', 200, 0600, 0700, 0600, 2, 1, 300);
    $limited->addThumbnailLinks($failed_urls[0] . ' ' . $failed_urls[1]);
    $failure_directory = $directory . DIRECTORY_SEPARATOR . '.external-image-failures';
    if (count(glob($failure_directory . DIRECTORY_SEPARATOR . '*.failure.dat') ?: []) !== 1) return false;

    // 既知の失敗は取得枠を消費せず、同一リクエスト内の次のURLを1件だけ試行できる。
    $with_negative_cache = new ExternalImageService($directory, 'thumbnail/', 200, 0600, 0700, 0600, 2, 1, 300);
    $with_negative_cache->addThumbnailLinks($failed_urls[0] . ' ' . $failed_urls[2]);
    return count(glob($failure_directory . DIRECTORY_SEPARATOR . '*.failure.dat') ?: []) === 2;
  } finally {
    $failure_directory = $directory . DIRECTORY_SEPARATOR . '.external-image-failures';
    foreach (glob($failure_directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_dir($failure_directory)) rmdir($failure_directory);
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('GD thumbnail generation', static function (): bool {
  $input = tempnam(sys_get_temp_dir(), 'noreita_smoke_');
  if ($input === false) {
    throw new RuntimeException('could not create temporary file');
  }

  $output = null;
  try {
    $image = imagecreatetruecolor(4, 4);
    if ($image === false) {
      throw new RuntimeException('could not create source image');
    }
    imagefill($image, 0, 0, imagecolorallocate($image, 20, 120, 220));
    if (!imagepng($image, $input)) {
      throw new RuntimeException('could not save source image');
    }

    $thumbnail = new Thumbnail($input, sys_get_temp_dir(), 20);
    $created = $thumbnail->createThumbnail();
    $output = $thumbnail->getOutputPath();
    return $created && $output !== null && is_file($output) && filesize($output) > 0;
  } finally {
    if (is_file($input)) unlink($input);
    if ($output !== null && is_file($output)) unlink($output);
  }
});

smoke_test('GD thumbnails preserve transparent pixels for supported input formats', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_transparent_thumbnail_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) return false;
  try {
    if (!function_exists('imageavif') && !function_exists('imagewebp')) return true;
    $formats = ['png' => 'imagepng', 'gif' => 'imagegif'];
    if (function_exists('imagewebp') && function_exists('imagecreatefromwebp')) $formats['webp'] = 'imagewebp';
    if (function_exists('imageavif') && function_exists('imagecreatefromavif')) $formats['avif'] = 'imageavif';
    foreach ($formats as $extension => $writer) {
      $input = $directory . DIRECTORY_SEPARATOR . 'source.' . $extension;
      if ($extension === 'gif') {
        $image = imagecreate(4, 4);
        if ($image === false) return false;
        $transparent = imagecolorallocate($image, 0, 0, 0);
        imagecolortransparent($image, $transparent);
        imagefill($image, 0, 0, $transparent);
        imagesetpixel($image, 2, 2, imagecolorallocate($image, 220, 20, 20));
      } else {
        $image = imagecreatetruecolor(4, 4);
        if ($image === false) return false;
        imagealphablending($image, false);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagesavealpha($image, true);
        imagesetpixel($image, 2, 2, imagecolorallocatealpha($image, 220, 20, 20, 0));
      }
      if (!$writer($image, $input)) return false;

      $thumbnail = new Thumbnail($input, $directory, 20, false, 'thumbnail-' . $extension);
      if (!$thumbnail->createThumbnail()) return false;
      $output = $thumbnail->getOutputPath();
      if ($output === null || !is_file($output)) return false;
      $output_image = match (mime_content_type($output)) {
        'image/avif' => function_exists('imagecreatefromavif') ? imagecreatefromavif($output) : false,
        'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($output) : false,
        default => false,
      };
      if ($output_image === false || ((imagecolorat($output_image, 0, 0) >> 24) & 0x7f) === 0) return false;
    }
    return true;
  } finally {
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('related image files are deleted together', static function (): bool {
  $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_images_' . bin2hex(random_bytes(8));
  if (!mkdir($directory, 0700)) return false;
  try {
    foreach (['png', 'webp', 'pch', 'psd', 'dat'] as $extension) {
      file_put_contents($directory . DIRECTORY_SEPARATOR . 'post.' . $extension, 'test');
    }
    ImageService::deleteRelatedFiles($directory, 'post.png');
    return count(glob($directory . DIRECTORY_SEPARATOR . 'post.*') ?: []) === 0;
  } finally {
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
      if (is_file($file)) unlink($file);
    }
    if (is_dir($directory)) rmdir($directory);
  }
});

smoke_test('post deletion restores every related file when the database transaction fails', static function (): bool {
  $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_delete_' . bin2hex(random_bytes(8));
  $images = $root . DIRECTORY_SEPARATOR . 'img';
  $staging = $root . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'delete-staging';
  if (!mkdir($images, 0700, true)) return false;
  $files = [
    'parent.png', 'parent.pch', 'parent_thumb_safe_test.webp',
    'reply.webp', 'reply.chi', 'reply_thumb_nsfw_test.webp',
  ];
  try {
    foreach ($files as $file) file_put_contents($images . DIRECTORY_SEPARATOR . $file, $file);

    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE board_log (
      tid INTEGER PRIMARY KEY, parent INTEGER NOT NULL, thread INTEGER NOT NULL,
      picfile TEXT NOT NULL, pwd TEXT NOT NULL
    )');
    $insert = $db->prepare('INSERT INTO board_log VALUES (?, ?, ?, ?, ?)');
    $password = password_hash('post-password', PASSWORD_DEFAULT);
    $insert->execute([1, 0, 1, 'parent.png', $password]);
    $insert->execute([2, 1, 0, 'reply.webp', $password]);
    $db->exec("CREATE TRIGGER reject_reply_deletion BEFORE DELETE ON board_log
      WHEN OLD.tid = 2 BEGIN SELECT RAISE(ABORT, 'forced deletion failure'); END");

    $service = new PostService(
      new BoardRepository($db), $images, 100, 0600, $staging
    );
    $failed = false;
    try {
      $service->deleteManyAsAdmin(['1']);
    } catch (PDOException $e) {
      $failed = str_contains($e->getMessage(), 'forced deletion failure');
    }
    if (!$failed
      || (int)$db->query('SELECT COUNT(*) FROM board_log')->fetchColumn() !== 2
      || array_filter($files, static fn(string $file): bool => !is_file($images . DIRECTORY_SEPARATOR . $file))
      || (glob($staging . DIRECTORY_SEPARATOR . 'delete-*') ?: []) !== []) {
      return false;
    }

    $db->exec('DROP TRIGGER reject_reply_deletion');
    $deleted = $service->deleteManyAsAdmin(['1']);
    return $deleted === 2
      && (int)$db->query('SELECT COUNT(*) FROM board_log')->fetchColumn() === 0
      && array_filter($files, static fn(string $file): bool => is_file($images . DIRECTORY_SEPARATOR . $file)) === []
      && (glob($staging . DIRECTORY_SEPARATOR . 'delete-*') ?: []) === [];
  } finally {
    foreach ([$images, $staging] as $directory) {
      foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        if (is_file($file)) unlink($file);
        if (is_dir($file)) {
          foreach (glob($file . DIRECTORY_SEPARATOR . '*') ?: [] as $nested) if (is_file($nested)) unlink($nested);
          rmdir($file);
        }
      }
      if (is_dir($directory)) rmdir($directory);
    }
    $backup = $root . DIRECTORY_SEPARATOR . 'backup';
    if (is_dir($backup)) rmdir($backup);
    if (is_dir($root)) rmdir($root);
  }
});

smoke_test('interrupted post deletions recover from manifests without touching active operations', static function (): bool {
  $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_recover_delete_' . bin2hex(random_bytes(8));
  $images = $root . DIRECTORY_SEPARATOR . 'img';
  $staging = $root . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'delete-staging';
  if (!mkdir($images, 0700, true)) return false;
  try {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE board_log (tid INTEGER PRIMARY KEY, picfile TEXT NOT NULL)');
    $insert = $db->prepare('INSERT INTO board_log VALUES (?, ?)');
    $service = new PostService(
      new BoardRepository($db), $images, 100, 0600, $staging
    );

    foreach (['restore.png', 'restore.pch', 'restore_thumb_safe_test.webp'] as $file) {
      file_put_contents($images . DIRECTORY_SEPARATOR . $file, $file);
    }
    $insert->execute([1, 'restore.png']);
    $restore_stage = ImageService::stageRelatedFilesForDeletion(
      $images, $staging, ['restore.png'], [['tid' => 1, 'picfile' => 'restore.png']]
    );
    flock($restore_stage['lock_handle'], LOCK_UN);
    fclose($restore_stage['lock_handle']);
    $restored = $service->recoverInterruptedDeletions();
    if ($restored['restored'] !== 1
      || !is_file($images . DIRECTORY_SEPARATOR . 'restore.png')
      || !is_file($images . DIRECTORY_SEPARATOR . 'restore.pch')
      || !is_file($images . DIRECTORY_SEPARATOR . 'restore_thumb_safe_test.webp')) {
      return false;
    }

    foreach (['complete.webp', 'complete.chi', 'complete_thumb_nsfw_test.webp'] as $file) {
      file_put_contents($images . DIRECTORY_SEPARATOR . $file, $file);
    }
    $insert->execute([2, 'complete.webp']);
    $complete_stage = ImageService::stageRelatedFilesForDeletion(
      $images, $staging, ['complete.webp'], [['tid' => 2, 'picfile' => 'complete.webp']]
    );
    $db->exec('DELETE FROM board_log WHERE tid = 2');
    flock($complete_stage['lock_handle'], LOCK_UN);
    fclose($complete_stage['lock_handle']);
    $completed = $service->recoverInterruptedDeletions();
    if ($completed['completed'] !== 1
      || is_file($images . DIRECTORY_SEPARATOR . 'complete.webp')
      || is_file($images . DIRECTORY_SEPARATOR . 'complete.chi')
      || is_file($images . DIRECTORY_SEPARATOR . 'complete_thumb_nsfw_test.webp')) {
      return false;
    }

    file_put_contents($images . DIRECTORY_SEPARATOR . 'active.png', 'active');
    $insert->execute([3, 'active.png']);
    $active_stage = ImageService::stageRelatedFilesForDeletion(
      $images, $staging, ['active.png'], [['tid' => 3, 'picfile' => 'active.png']]
    );
    $active = $service->recoverInterruptedDeletions();
    $active_was_skipped = $active['skipped'] === 1
      && !is_file($images . DIRECTORY_SEPARATOR . 'active.png')
      && is_file($active_stage['directory'] . DIRECTORY_SEPARATOR . 'active.png');
    ImageService::rollbackStagedDeletion($active_stage);

    return $active_was_skipped
      && is_file($images . DIRECTORY_SEPARATOR . 'active.png')
      && (glob($staging . DIRECTORY_SEPARATOR . 'delete-*') ?: []) === [];
  } finally {
    $iterator = is_dir($root)
      ? new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
      )
      : null;
    if ($iterator !== null) {
      foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
      }
    }
    if (is_dir($root)) rmdir($root);
  }
});

smoke_test('invalid deletion recovery data is quarantined and expires safely', static function (): bool {
  $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_delete_quarantine_' . bin2hex(random_bytes(8));
  $images = $root . DIRECTORY_SEPARATOR . 'img';
  $staging = $root . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'delete-staging';
  $quarantine = $root . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'delete-quarantine';
  if (!mkdir($staging, 0700, true) || !mkdir($images, 0700, true)) return false;
  try {
    $operation = $staging . DIRECTORY_SEPARATOR . 'delete-' . str_repeat('a', 24);
    mkdir($operation, 0700);
    file_put_contents($operation . '/.lock', '');
    file_put_contents($operation . '/manifest.json', '{broken');
    file_put_contents($operation . '/orphan.png', 'image');

    $now = 1700000000;
    $result = ImageService::recoverStagedDeletions(
      $images, $staging, static fn(array $posts): bool => false, $quarantine, 30, $now
    );
    $quarantined = glob($quarantine . DIRECTORY_SEPARATOR . 'quarantine-delete-*') ?: [];
    if ($result['invalid'] !== 1 || $result['quarantined'] !== 1 || count($quarantined) !== 1
      || is_dir($operation) || !is_file($quarantined[0] . '/quarantine.json')
      || !is_file($quarantined[0] . '/orphan.png')) {
      return false;
    }

    touch($quarantined[0], 100);
    $unsafe = $quarantine . DIRECTORY_SEPARATOR . 'quarantine-delete-' . str_repeat('b', 24)
      . '-20200101000000-' . str_repeat('c', 8);
    mkdir($unsafe, 0700);
    mkdir($unsafe . '/nested', 0700);
    touch($unsafe, 100);
    $unrelated = $quarantine . DIRECTORY_SEPARATOR . 'keep-this-directory';
    mkdir($unrelated, 0700);
    touch($unrelated, 100);

    return ImageService::cleanupDeletionQuarantine($quarantine, 30, 10, $now) === 1
      && !is_dir($quarantined[0])
      && is_dir($unsafe)
      && is_dir($unrelated);
  } finally {
    $iterator = is_dir($root)
      ? new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
      )
      : null;
    if ($iterator !== null) {
      foreach ($iterator as $item) {
        if ($item->isLink() || $item->isFile()) unlink($item->getPathname());
        elseif ($item->isDir()) rmdir($item->getPathname());
      }
    }
    if (is_dir($root)) rmdir($root);
  }
});

smoke_test('posted image replacement can roll back or complete atomically', static function (): bool {
  $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_replace_' . bin2hex(random_bytes(8));
  $temp = $root . DIRECTORY_SEPARATOR . 'tmp';
  $images = $root . DIRECTORY_SEPARATOR . 'img';
  mkdir($temp, 0700, true);
  mkdir($images, 0700, true);
  $write_source = static function () use ($temp): void {
    $image = imagecreatetruecolor(4, 3);
    imagefill($image, 0, 0, imagecolorallocate($image, 20, 120, 220));
    imagepng($image, $temp . DIRECTORY_SEPARATOR . 'new.png');
    file_put_contents($temp . DIRECTORY_SEPARATOR . 'new.dat', 'metadata');
    file_put_contents($temp . DIRECTORY_SEPARATOR . 'new.pch', 'new animation');
  };

  try {
    $old_image = imagecreatetruecolor(4, 3);
    imagefill($old_image, 0, 0, imagecolorallocate($old_image, 220, 20, 20));
    imagepng($old_image, $images . DIRECTORY_SEPARATOR . 'old.png');
    file_put_contents($images . DIRECTORY_SEPARATOR . 'old.pch', 'old animation');
    $write_source();

    $replacement = ImageService::replacePostedFiles(
      $temp, $images, 'new', '.png', 100, 'old.png', 'old.pch', 0600
    );
    if (!is_file($images . DIRECTORY_SEPARATOR . 'old.png')
      || !is_file($images . DIRECTORY_SEPARATOR . 'old.pch')
      || !is_file($images . DIRECTORY_SEPARATOR . 'new.png')
      || !is_file($temp . DIRECTORY_SEPARATOR . 'new.png')) return false;

    ImageService::rollbackPostedReplacement($replacement);
    if (!is_file($images . DIRECTORY_SEPARATOR . 'old.png')
      || !is_file($images . DIRECTORY_SEPARATOR . 'old.pch')
      || is_file($images . DIRECTORY_SEPARATOR . 'new.png')
      || is_file($images . DIRECTORY_SEPARATOR . 'new.pch')
      || !is_file($temp . DIRECTORY_SEPARATOR . 'new.png')) return false;

    $replacement = ImageService::replacePostedFiles(
      $temp, $images, 'new', '.png', 101, 'old.png', 'old.pch', 0600
    );
    ImageService::completePostedReplacement($replacement);
    return !is_file($images . DIRECTORY_SEPARATOR . 'old.png')
      && !is_file($images . DIRECTORY_SEPARATOR . 'old.pch')
      && is_file($images . DIRECTORY_SEPARATOR . 'new.png')
      && is_file($images . DIRECTORY_SEPARATOR . 'new.pch')
      && !is_file($temp . DIRECTORY_SEPARATOR . 'new.png')
      && !is_file($temp . DIRECTORY_SEPARATOR . 'new.pch')
      && !is_file($temp . DIRECTORY_SEPARATOR . 'new.dat');
  } finally {
    foreach ([$temp, $images] as $directory) {
      foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        if (is_file($file)) unlink($file);
      }
      if (is_dir($directory)) rmdir($directory);
    }
    if (is_dir($root)) rmdir($root);
  }
});

smoke_test('new post image and animation are finalized', static function (): bool {
  $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_finalize_' . bin2hex(random_bytes(8));
  $temp = $root . DIRECTORY_SEPARATOR . 'tmp';
  $images = $root . DIRECTORY_SEPARATOR . 'img';
  mkdir($temp, 0700, true);
  mkdir($images, 0700, true);
  try {
    $source = imagecreatetruecolor(4, 3);
    imagefill($source, 0, 0, imagecolorallocate($source, 20, 120, 220));
    imagepng($source, $temp . DIRECTORY_SEPARATOR . 'post.png');
    file_put_contents($temp . DIRECTORY_SEPARATOR . 'post.dat', "ip\thost\tagent\t.png\tcode\trep\t100\t160\t\tneo");
    file_put_contents($temp . DIRECTORY_SEPARATOR . 'post.pch', 'NEO');
    file_put_contents($temp . DIRECTORY_SEPARATOR . 'post.psd', 'PSD');

    $result = ImageService::finalizeNewPost($temp, $images, 'post.png', 'new', true, 100, false, 0600);
    return $result['img_w'] === 4 && $result['img_h'] === 3
      && $result['psec'] === 60 && $result['tool'] === 'PaintBBS NEO'
      && $result['pchfile'] === 'post.pch'
      && is_file($images . DIRECTORY_SEPARATOR . 'post.png')
      && is_file($images . DIRECTORY_SEPARATOR . 'post.pch')
      && is_file($images . DIRECTORY_SEPARATOR . 'post.psd')
      && !is_file($temp . DIRECTORY_SEPARATOR . 'post.psd')
      && !is_file($temp . DIRECTORY_SEPARATOR . 'post.dat');
  } finally {
    foreach ([$temp, $images] as $directory) {
      foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) if (is_file($file)) unlink($file);
      if (is_dir($directory)) rmdir($directory);
    }
    if (is_dir($root)) rmdir($root);
  }
});

smoke_test('image consistency repair backs up data and fixes recoverable issues', static function (): bool {
  $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'noreita_consistency_' . bin2hex(random_bytes(8));
  $images = $root . DIRECTORY_SEPARATOR . 'img';
  mkdir($images, 0700, true);
  $database = $root . DIRECTORY_SEPARATOR . 'reita.db';
  try {
    $db = new PDO('sqlite:' . $database);
    $db->exec('CREATE TABLE board_log (
      tid INTEGER PRIMARY KEY, picfile TEXT, pchfile TEXT, thumbnail TEXT,
      img_w INTEGER, img_h INTEGER, nsfw INTEGER
    )');
    $image = imagecreatetruecolor(4, 3);
    imagepng($image, $images . DIRECTORY_SEPARATOR . 'valid.png');
    imagepng($image, $images . DIRECTORY_SEPARATOR . 'orphan.png');
    $insert = $db->prepare('INSERT INTO board_log VALUES (?, ?, ?, ?, ?, ?, ?)');
    $insert->execute([1, 'valid.png', 'valid.pch', '', 40, 30, 1]);
    $insert->execute([2, 'missing.png', '', '', 8, 6, 0]);
    unset($db);

    $report = checker_scan($database, $images);
    $types = array_column($report['issues'], 'type');
    $repair = checker_repair([
      'root' => $root,
      'database' => $database,
      'image_dir' => $images,
      'thumbnail_file' => dirname(__DIR__) . '/noreita/thumbnail.inc.php',
      'thumbnail_width' => 20,
      'file_permission' => 0600,
    ], $report);
    $after = checker_scan($database, $images);
    $repaired = (new PDO('sqlite:' . $database))->query(
      'SELECT pchfile, thumbnail, img_w, img_h FROM board_log WHERE tid = 1'
    )->fetch(PDO::FETCH_ASSOC);
    $action_types = array_column($repair['actions'], 'type');
    $ok = $report['summary']['posts_checked'] === 2
      && $report['summary']['errors'] === 1
      && $report['summary']['warnings'] === 4
      && in_array('missing_image', $types, true)
      && in_array('orphan_file', $types, true)
      && $repair['failed'] === 0 && is_file($repair['backup'])
      && in_array('update_dimensions', $action_types, true)
      && in_array('clear_missing_pch', $action_types, true)
      && in_array('regenerate_thumbnail', $action_types, true)
      && in_array('quarantine_file', $action_types, true)
      && is_array($repaired) && $repaired['pchfile'] === ''
      && (int)$repaired['img_w'] === 4 && (int)$repaired['img_h'] === 3
      && is_file($images . DIRECTORY_SEPARATOR . $repaired['thumbnail'])
      && !is_file($images . DIRECTORY_SEPARATOR . 'orphan.png')
      && $after['summary']['errors'] === 1 && $after['summary']['warnings'] === 0;
    if (!$ok) {
      throw new RuntimeException(json_encode([
        'before' => $report, 'repair' => $repair, 'after' => $after, 'row' => $repaired,
      ], JSON_UNESCAPED_SLASHES));
    }
    return true;
  } finally {
    foreach (glob($images . DIRECTORY_SEPARATOR . '*') ?: [] as $file) if (is_file($file)) unlink($file);
    if (is_dir($images)) rmdir($images);
    if (is_file($database)) unlink($database);
    foreach (['backup', 'orphan'] as $subdirectory) {
      foreach (glob($root . DIRECTORY_SEPARATOR . $subdirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $entry) {
        if (is_file($entry)) unlink($entry);
        if (is_dir($entry)) {
          foreach (glob($entry . DIRECTORY_SEPARATOR . '*') ?: [] as $file) if (is_file($file)) unlink($file);
          rmdir($entry);
        }
      }
      if (is_dir($root . DIRECTORY_SEPARATOR . $subdirectory)) rmdir($root . DIRECTORY_SEPARATOR . $subdirectory);
    }
    if (is_dir($root)) rmdir($root);
  }
});

echo "\nSmoke tests: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
