<?php
// noReita theme manifest and diagnostics (C) sakots 2026 MIT License

final class ThemeManifestException extends RuntimeException {
}

/**
 * v4.2の簡易テーマと従来テーマを、同じ実行情報へ解決する。
 * 簡易テーマはtheme.phpで親を1つ指定し、設定・未配置テンプレート・アセットを継承する。
 */
final class ThemeRuntime {
  private const MAX_INHERITANCE_DEPTH = 8;

  /**
   * @return array{
   *   id:string,name:string,version:string,engine:string,templates:array<string,string>,
   *   active_directory:string,base_id:string,base_directory:string,
   *   view_directories:array<int,string>,stylesheet_themes:array<int,array{id:string,version:string}>,
   *   manifest:array<string,mixed>,base_metadata:array<string,mixed>,simple:bool
   * }
   */
  public static function load(string $themes_root, string $theme_id): array {
    $root = realpath($themes_root);
    if ($root === false || !is_dir($root)) {
      throw new ThemeManifestException('Theme root directory does not exist.');
    }
    if (!ThemeManifest::safeName($theme_id)) {
      throw new ThemeManifestException('Configured theme name is unsafe.');
    }

    $active_id = $theme_id;
    $simple_themes = [];
    $seen = [];
    for ($depth = 0; $depth < self::MAX_INHERITANCE_DEPTH; $depth++) {
      if (isset($seen[$theme_id])) {
        throw new ThemeManifestException('Theme inheritance contains a cycle.');
      }
      $seen[$theme_id] = true;
      $directory = $root . DIRECTORY_SEPARATOR . $theme_id;
      if (!is_dir($directory) || !is_readable($directory)) {
        throw new ThemeManifestException("Theme directory is missing or unreadable: {$theme_id}");
      }

      $simple_file = $directory . DIRECTORY_SEPARATOR . 'theme.php';
      if (!is_file($simple_file)) {
        $configuration = $directory . DIRECTORY_SEPARATOR . 'theme_conf.php';
        if (!is_file($configuration) || !is_readable($configuration)) {
          throw new ThemeManifestException("Theme must contain theme.php or theme_conf.php: {$theme_id}");
        }
        $base_id = $theme_id;
        $base_directory = $directory;
        break;
      }

      $definition = require $simple_file;
      if (!is_array($definition)) {
        throw new ThemeManifestException("theme.php must return an array: {$theme_id}");
      }
      $definition = self::validateSimpleDefinition($definition, $theme_id);
      $simple_themes[] = [
        'id' => $theme_id,
        'directory' => $directory,
        'name' => $definition['name'],
        'version' => $definition['version'],
      ];
      $theme_id = $definition['extends'];
    }
    if (!isset($base_id, $base_directory)) {
      throw new ThemeManifestException('Theme inheritance is too deep.');
    }

    require_once $base_directory . DIRECTORY_SEPARATOR . 'theme_conf.php';
    $manifest = ThemeManifest::load($base_directory);
    $base_metadata = ThemeManifest::runtimeMetadata();
    ThemeManifest::assertMatchesRuntime($manifest, $base_metadata);

    $active = $simple_themes[0] ?? [
      'id' => $base_id,
      'directory' => $base_directory,
      'name' => (string)$base_metadata['id'],
      'version' => (string)$base_metadata['version'],
    ];
    $view_directories = array_column($simple_themes, 'directory');
    $view_directories[] = $base_directory;
    $view_directories = array_values(array_unique($view_directories));

    $stylesheet_themes = [];
    foreach (array_reverse($simple_themes) as $theme) {
      $stylesheet = $theme['directory'] . DIRECTORY_SEPARATOR . 'theme.css';
      if (is_file($stylesheet)) {
        $digest = @hash_file('sha256', $stylesheet);
        $cache_version = $theme['version'] . (is_string($digest) ? '-' . substr($digest, 0, 12) : '');
        $stylesheet_themes[] = ['id' => $theme['id'], 'version' => $cache_version];
      }
    }

    return [
      'id' => $active_id,
      'name' => $active['name'],
      'version' => $active['version'],
      'engine' => (string)$base_metadata['engine'],
      'templates' => $base_metadata['templates'],
      'active_directory' => $active['directory'],
      'base_id' => $base_id,
      'base_directory' => $base_directory,
      'view_directories' => $view_directories,
      'stylesheet_themes' => $stylesheet_themes,
      'manifest' => $manifest,
      'base_metadata' => $base_metadata,
      'simple' => $simple_themes !== [],
    ];
  }

  /** @param array<string,mixed> $definition @return array{name:string,version:string,extends:string} */
  private static function validateSimpleDefinition(array $definition, string $theme_id): array {
    $unknown = array_diff(array_keys($definition), ['name', 'version', 'extends']);
    if ($unknown !== []) {
      throw new ThemeManifestException('theme.php contains an unknown setting: ' . (string)reset($unknown));
    }
    $parent = $definition['extends'] ?? null;
    if (!is_string($parent) || !ThemeManifest::safeName($parent) || $parent === $theme_id) {
      throw new ThemeManifestException('theme.php extends must be a different safe theme name.');
    }
    $name = $definition['name'] ?? $theme_id;
    if (!is_string($name) || trim($name) === '' || mb_strlen($name) > 100) {
      throw new ThemeManifestException('theme.php name must be a non-empty string up to 100 characters.');
    }
    $version = $definition['version'] ?? '1.0.0';
    if (!is_string($version) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/D', $version) !== 1) {
      throw new ThemeManifestException('theme.php version must contain only letters, numbers, dot, underscore, and hyphen.');
    }
    return ['name' => trim($name), 'version' => $version, 'extends' => $parent];
  }
}

final class ThemeManifest {
  public const FORMAT_VERSION = 1;

  /** @var array<string,string> */
  private const TEMPLATE_CONSTANTS = [
    'main' => 'MAINFILE',
    'response' => 'RESFILE',
    'paint' => 'PAINTFILE',
    'paint_backend' => 'PAINTFILE_BE',
    'animation' => 'ANIMEFILE',
    'tegaki_animation' => 'ANIMEFILE_TEGAKI',
    'image_post' => 'PICFILE',
    'catalog' => 'CATALOGFILE',
    'admin' => 'ADMINFILE',
    'admin_post' => 'ADMINPOSTFILE',
    'admin_errorlog' => 'ADMINERRORLOGFILE',
    'admin_temporary_images' => 'ADMINTEMPORARYFILE',
    'share_server' => 'SET_SHARE_SERVER',
    'misskey_note' => 'MISSKEYFILE',
    'other' => 'OTHERFILE',
  ];

  /** @return array<string,mixed> */
  public static function load(string $theme_directory): array {
    $manifest_file = rtrim($theme_directory, '/\\') . DIRECTORY_SEPARATOR . 'theme_manifest.php';
    if (!is_file($manifest_file)) {
      return ['legacy' => true, 'id' => basename(rtrim($theme_directory, '/\\'))];
    }
    $manifest = require $manifest_file;
    if (!is_array($manifest)) {
      throw new ThemeManifestException('theme_manifest.php must return an array.');
    }
    self::validate($manifest);
    $manifest['legacy'] = false;
    return $manifest;
  }

  /** @return array<string,mixed> */
  public static function runtimeMetadata(): array {
    $templates = [];
    foreach (self::TEMPLATE_CONSTANTS as $key => $constant) {
      if (defined($constant)) $templates[$key] = constant($constant);
    }
    return [
      'id' => defined('THEME_NAME') ? THEME_NAME : '',
      'version' => defined('THEME_VER') ? THEME_VER : '',
      'engine' => defined('THEME_TEMPLATE_ENGINE') ? THEME_TEMPLATE_ENGINE : 'blade',
      'templates' => $templates,
    ];
  }

  /** @param array<string,mixed> $manifest @param array<string,mixed> $runtime */
  public static function assertMatchesRuntime(array $manifest, array $runtime): void {
    if (($manifest['legacy'] ?? false) === true) return;
    foreach (['id', 'version', 'engine'] as $key) {
      if (($manifest[$key] ?? null) !== ($runtime[$key] ?? null)) {
        throw new ThemeManifestException("Theme manifest {$key} does not match theme_conf.php.");
      }
    }
    if (($manifest['templates'] ?? null) !== ($runtime['templates'] ?? null)) {
      throw new ThemeManifestException('Theme manifest templates do not match theme_conf.php.');
    }
    $minimum_php = $manifest['requires']['php'] ?? null;
    if (!is_string($minimum_php) || version_compare(PHP_VERSION, $minimum_php, '<')) {
      throw new ThemeManifestException('The current PHP version does not satisfy the theme requirement.');
    }
    $minimum_noreita = $manifest['requires']['noreita'] ?? null;
    if (!is_string($minimum_noreita) || (defined('NOREITA_VERSION')
      && version_compare(NOREITA_VERSION, $minimum_noreita, '<'))) {
      throw new ThemeManifestException('The current noReita version does not satisfy the theme requirement.');
    }
  }

  /** @param array<string,mixed> $manifest */
  private static function validate(array $manifest): void {
    if (($manifest['format'] ?? null) !== self::FORMAT_VERSION) {
      throw new ThemeManifestException('Unsupported theme manifest format.');
    }
    foreach (['id', 'name', 'version', 'engine'] as $key) {
      if (!is_string($manifest[$key] ?? null) || trim($manifest[$key]) === '') {
        throw new ThemeManifestException("Theme manifest {$key} must be a non-empty string.");
      }
    }
    if (!self::safeName($manifest['id'])) {
      throw new ThemeManifestException('Theme manifest id must be a safe directory name.');
    }
    if (!in_array($manifest['engine'], ['blade', 'twig'], true)) {
      throw new ThemeManifestException('Theme manifest engine must be blade or twig.');
    }
    if (!is_array($manifest['requires'] ?? null) || !is_string($manifest['requires']['php'] ?? null)
      || !is_string($manifest['requires']['noreita'] ?? null)) {
      throw new ThemeManifestException('Theme manifest requires.php and requires.noreita must be specified.');
    }
    if (!is_array($manifest['templates'] ?? null) || $manifest['templates'] === []) {
      throw new ThemeManifestException('Theme manifest templates must be a non-empty map.');
    }
    foreach ($manifest['templates'] as $key => $template) {
      if (!is_string($key) || !is_string($template) || !self::safeTemplateName($template)) {
        throw new ThemeManifestException('Theme manifest contains an invalid template name.');
      }
    }
    if (!is_array($manifest['assets'] ?? null)) {
      throw new ThemeManifestException('Theme manifest assets must be a map.');
    }
    foreach (['css', 'javascript'] as $type) {
      if (!is_array($manifest['assets'][$type] ?? null)) {
        throw new ThemeManifestException("Theme manifest assets.{$type} must be a list.");
      }
      foreach ($manifest['assets'][$type] as $asset) {
        if (!is_string($asset) || !self::safeRelativePath($asset)) {
          throw new ThemeManifestException('Theme manifest contains an unsafe asset path.');
        }
      }
    }
  }

  public static function safeName(string $name): bool {
    return preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/D', $name) === 1;
  }

  public static function safeTemplateName(string $name): bool {
    return preg_match('/\A[A-Za-z0-9][A-Za-z0-9._\/-]*\z/D', $name) === 1
      && !str_contains($name, '..');
  }

  public static function safeRelativePath(string $path): bool {
    $normalized = str_replace('\\', '/', $path);
    return $normalized !== '' && $normalized[0] !== '/'
      && !str_contains($normalized, '..')
      && preg_match('/\A[A-Za-z0-9][A-Za-z0-9._\/-]*\z/D', $normalized) === 1;
  }
}

final class ThemeDiagnostics {
  /** @param array<string,mixed> $manifest @param array<string,mixed> $runtime
   * @return array{summary:array<string,int>,issues:array<int,array<string,string>>} */
  public static function inspect(string $theme_directory, array $manifest, array $runtime): array {
    $issues = [];
    if (($manifest['legacy'] ?? false) === true) {
      self::add($issues, 'warning', 'missing_manifest', 'theme_manifest.php is missing; legacy Blade compatibility is in use.');
      return self::report($issues, 0, 0, 0);
    }
    try {
      ThemeManifest::assertMatchesRuntime($manifest, $runtime);
    } catch (ThemeManifestException $e) {
      self::add($issues, 'error', 'manifest_mismatch', $e->getMessage());
    }

    $engine = (string)$manifest['engine'];
    $extension = $engine === 'twig' ? '.twig' : '.blade.php';
    $templates_checked = 0;
    foreach ($manifest['templates'] as $template) {
      $templates_checked++;
      $path = $theme_directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $template) . $extension;
      if (!is_file($path) || !is_readable($path)) {
        self::add($issues, 'error', 'missing_template', "Required template is missing or unreadable: {$template}{$extension}");
      }
    }

    $components_checked = self::checkComponentIncludes($theme_directory, $engine, $issues);
    $assets_checked = 0;
    foreach (array_merge($manifest['assets']['css'], $manifest['assets']['javascript']) as $asset) {
      $assets_checked++;
      $path = $theme_directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $asset);
      if (!is_file($path) || !is_readable($path)) {
        self::add($issues, 'error', 'missing_asset', "Required asset is missing or unreadable: {$asset}");
      }
    }
    if ($engine === 'twig') {
      self::compileTwigTemplates($theme_directory, $issues);
    } else {
      self::compileBladeTemplates([$theme_directory], [$theme_directory], $issues);
    }
    return self::report($issues, $templates_checked, $components_checked, $assets_checked);
  }

  /**
   * 親テーマの完全性に加えて、簡易テーマの差分テンプレートと固定CSSを検査する。
   * @param array<string,mixed> $runtime
   * @return array{summary:array<string,int>,issues:array<int,array<string,string>>}
   */
  public static function inspectRuntime(array $runtime): array {
    $base = self::inspect(
      (string)$runtime['base_directory'],
      $runtime['manifest'],
      $runtime['base_metadata']
    );
    if (($runtime['simple'] ?? false) !== true) return $base;

    $issues = $base['issues'];
    $engine = (string)$runtime['engine'];
    $suffix = $engine === 'twig' ? '.twig' : '.blade.php';
    $directories = $runtime['view_directories'];
    $override_directories = array_slice($directories, 0, -1);
    $templates_checked = $base['summary']['templates_checked'];
    $components_checked = $base['summary']['components_checked'];
    foreach ($override_directories as $directory) {
      foreach (self::templateFiles($directory, $suffix) as $file) {
        $templates_checked++;
        $source = @file_get_contents($file);
        if (!is_string($source)) {
          self::add($issues, 'error', 'unreadable_template', 'Override template is unreadable: ' . basename($file));
          continue;
        }
        $pattern = $engine === 'twig'
          ? "/\{%\\s*include\\s+['\"]([^'\"]+)['\"]/"
          : "/@include\\s*\(\\s*['\"]([^'\"]+)['\"]/";
        if (preg_match_all($pattern, $source, $matches) !== false) {
          foreach ($matches[1] ?? [] as $include) {
            if (!str_starts_with($include, 'components')) continue;
            $relative = $engine === 'twig'
              ? $include
              : str_replace('.', '/', $include) . '.blade.php';
            $components_checked++;
            if (!self::existsInDirectories($directories, $relative)) {
              self::add($issues, 'error', 'missing_component', 'Override references a missing component: ' . $relative);
            }
          }
        }
      }
    }

    if ($engine === 'twig') {
      try {
        $twig = TwigTemplateEngine::createEnvironment($directories, false);
        foreach ($override_directories as $directory) {
          $prefix = strlen(rtrim($directory, '/\\')) + 1;
          foreach (self::templateFiles($directory, '.twig') as $file) {
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file, $prefix));
            $twig->load($relative);
          }
        }
      } catch (Throwable $e) {
        self::add($issues, 'error', 'twig_syntax', $e->getMessage());
      }
    } else {
      self::compileBladeTemplates($directories, $override_directories, $issues);
    }

    $assets_checked = $base['summary']['assets_checked'];
    foreach ($runtime['stylesheet_themes'] as $stylesheet_theme) {
      $assets_checked++;
      $directory = dirname((string)$runtime['base_directory']) . DIRECTORY_SEPARATOR . $stylesheet_theme['id'];
      if (!is_readable($directory . DIRECTORY_SEPARATOR . 'theme.css')) {
        self::add($issues, 'error', 'missing_asset', 'Simple theme stylesheet is unreadable.');
      }
    }
    return self::report($issues, $templates_checked, $components_checked, $assets_checked);
  }

  /** @param array<int,array<string,string>> $issues */
  private static function checkComponentIncludes(string $directory, string $engine, array &$issues): int {
    $suffix = $engine === 'twig' ? '.twig' : '.blade.php';
    $pattern = $engine === 'twig'
      ? "/\{%\\s*include\\s+['\"]([^'\"]+)['\"]/"
      : "/@include\\s*\\(\\s*['\"]([^'\"]+)['\"]/";
    $checked = 0;
    foreach (self::templateFiles($directory, $suffix) as $file) {
      $source = file_get_contents($file);
      if ($source === false) {
        self::add($issues, 'error', 'unreadable_template', 'Template is unreadable: ' . basename($file));
        continue;
      }
      if (preg_match_all($pattern, $source, $matches) === false) continue;
      foreach ($matches[1] ?? [] as $include) {
        if (!str_starts_with($include, 'components')) continue;
        $relative = $engine === 'twig'
          ? $include
          : str_replace('.', '/', $include) . '.blade.php';
        $checked++;
        $path = $directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_file($path) || !is_readable($path)) {
          self::add($issues, 'error', 'missing_component', "Component referenced by " . basename($file) . " is missing: {$relative}");
        }
      }
    }
    return $checked;
  }

  /** @param array<int,array<string,string>> $issues */
  private static function compileTwigTemplates(string $directory, array &$issues): void {
    if (!class_exists('TwigTemplateEngine')) {
      self::add($issues, 'error', 'twig_unavailable', 'Twig template compiler is unavailable.');
      return;
    }
    try {
      $twig = TwigTemplateEngine::createEnvironment($directory, false);
      $prefix = strlen(rtrim($directory, '/\\')) + 1;
      foreach (self::templateFiles($directory, '.twig') as $file) {
        $relative = substr($file, $prefix, -5);
        $twig->load(str_replace(DIRECTORY_SEPARATOR, '/', $relative) . '.twig');
      }
    } catch (Throwable $e) {
      self::add($issues, 'error', 'twig_syntax', $e->getMessage());
    }
  }

  /** @param array<int,string> $view_directories @param array<int,string> $scan_directories
   * @param array<int,array<string,string>> $issues */
  private static function compileBladeTemplates(array $view_directories, array $scan_directories, array &$issues): void {
    if (!class_exists(\eftec\bladeone\BladeOne::class)) {
      self::add($issues, 'error', 'blade_unavailable', 'BladeOne template compiler is unavailable.');
      return;
    }
    $blade = new \eftec\bladeone\BladeOne(
      $view_directories,
      sys_get_temp_dir(),
      \eftec\bladeone\BladeOne::MODE_SLOW
    );
    foreach ($scan_directories as $directory) {
      $prefix = strlen(rtrim($directory, '/\\')) + 1;
      foreach (self::templateFiles($directory, '.blade.php') as $file) {
        $source = @file_get_contents($file);
        if (!is_string($source)) continue;
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file, $prefix));
        try {
          $compiled = $blade->compileString($source);
          token_get_all($compiled, TOKEN_PARSE);
        } catch (Throwable $e) {
          self::add($issues, 'error', 'blade_syntax', "Blade syntax error in {$relative}: {$e->getMessage()}");
        }
      }
    }
  }

  /** @return array<int,string> */
  private static function templateFiles(string $directory, string $suffix): array {
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $item) {
      $path = $item->getPathname();
      if ($item->isFile() && str_ends_with($path, $suffix)) $files[] = $path;
    }
    sort($files, SORT_STRING);
    return $files;
  }

  /** @param array<int,string> $directories */
  private static function existsInDirectories(array $directories, string $relative): bool {
    $relative = str_replace('/', DIRECTORY_SEPARATOR, $relative);
    foreach ($directories as $directory) {
      if (is_file($directory . DIRECTORY_SEPARATOR . $relative)) return true;
    }
    return false;
  }

  /** @param array<int,array<string,string>> $issues */
  private static function add(array &$issues, string $severity, string $code, string $message): void {
    $issues[] = ['severity' => $severity, 'code' => $code, 'message' => $message];
  }

  /** @param array<int,array<string,string>> $issues
   * @return array{summary:array<string,int>,issues:array<int,array<string,string>>} */
  private static function report(array $issues, int $templates, int $components, int $assets): array {
    $errors = count(array_filter($issues, static fn(array $issue): bool => $issue['severity'] === 'error'));
    return [
      'summary' => [
        'templates_checked' => $templates,
        'components_checked' => $components,
        'assets_checked' => $assets,
        'errors' => $errors,
        'warnings' => count($issues) - $errors,
      ],
      'issues' => $issues,
    ];
  }
}
