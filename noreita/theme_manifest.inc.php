<?php
// noReita theme manifest and diagnostics (C) sakots 2026 MIT License

final class ThemeManifestException extends RuntimeException {
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
    if ($engine === 'twig') self::compileTwigTemplates($theme_directory, $issues);
    return self::report($issues, $templates_checked, $components_checked, $assets_checked);
  }

  /** @param array<int,array<string,string>> $issues */
  private static function checkComponentIncludes(string $directory, string $engine, array &$issues): int {
    $suffix = $engine === 'twig' ? '.twig' : '.blade.php';
    $pattern = $engine === 'twig'
      ? "/\{%\\s*include\\s+['\"]([^'\"]+)['\"]/"
      : "/@include\\(\\s*'([^']+)'/";
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
