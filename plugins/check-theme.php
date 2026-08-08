<?php
declare(strict_types=1);

// noReita theme manifest and template diagnostic tool

require_once dirname(__DIR__) . '/noreita/theme_manifest.inc.php';

function theme_checker_usage(string $script): string {
  return <<<TEXT
Usage: php {$script} [--root=PATH] [--theme=ID] [--json]

Checks a noReita v4 theme without modifying files.

  --root=PATH  Directory containing noReita's config.php (default: noreita/)
  --theme=ID   Theme directory name (default: paths.theme from configuration)
  --json       Print a machine-readable report
  --help       Show this help
TEXT;
}

/** @return array{root:string,theme:?string,json:bool,help:bool} */
function theme_checker_options(array $arguments): array {
  $root = null;
  $theme = null;
  $json = false;
  $help = false;
  foreach (array_slice($arguments, 1) as $argument) {
    if ($argument === '--json') {
      $json = true;
    } elseif ($argument === '--help' || $argument === '-h') {
      $help = true;
    } elseif (str_starts_with($argument, '--root=')) {
      $root = substr($argument, strlen('--root='));
    } elseif (str_starts_with($argument, '--theme=')) {
      $theme = substr($argument, strlen('--theme='));
    } else {
      throw new InvalidArgumentException("Unknown option: {$argument}");
    }
  }
  if ($root === null || $root === '') {
    $default = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'noreita';
    $root = is_file($default . DIRECTORY_SEPARATOR . 'config.php') ? $default : __DIR__;
  }
  $resolved = realpath($root);
  if ($resolved === false || !is_dir($resolved)) {
    throw new RuntimeException("Root directory does not exist: {$root}");
  }
  if ($theme !== null && !ThemeManifest::safeName($theme)) {
    throw new InvalidArgumentException('Theme must be a safe directory name.');
  }
  return ['root' => $resolved, 'theme' => $theme, 'json' => $json, 'help' => $help];
}

/** @return array{summary:array<string,int>,issues:array<int,array<string,string>>} */
function theme_checker_run(string $root, ?string $selected_theme): array {
  $autoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
  if (!is_file($autoload)) throw new RuntimeException('Composer dependencies are missing. Run composer install first.');
  require_once $autoload;
  require_once $root . DIRECTORY_SEPARATOR . 'bootstrap.php';
  require_once $root . DIRECTORY_SEPARATOR . 'config_loader.inc.php';
  require_once $root . DIRECTORY_SEPARATOR . 'template_engine.inc.php';
  require_once $root . DIRECTORY_SEPARATOR . 'theme_manifest.inc.php';
  if ($selected_theme === null) Config::load($root);
  $theme = $selected_theme ?? Config::string('paths.theme');
  if (!ThemeManifest::safeName($theme)) throw new RuntimeException('Configured theme name is unsafe.');
  $directory = $root . DIRECTORY_SEPARATOR . 'theme' . DIRECTORY_SEPARATOR . $theme;
  if (!is_dir($directory)) throw new RuntimeException("Theme directory does not exist: {$theme}");
  $configuration = $directory . DIRECTORY_SEPARATOR . 'theme_conf.php';
  if (!is_file($configuration) || !is_readable($configuration)) {
    throw new RuntimeException("theme_conf.php is missing or unreadable for theme: {$theme}");
  }
  require $configuration;
  $manifest = ThemeManifest::load($directory);
  return ThemeDiagnostics::inspect($directory, $manifest, ThemeManifest::runtimeMetadata());
}

function theme_checker_main(array $arguments): int {
  try {
    $options = theme_checker_options($arguments);
    if ($options['help']) {
      echo theme_checker_usage($arguments[0] ?? 'check-theme.php');
      return 0;
    }
    $report = theme_checker_run($options['root'], $options['theme']);
    if ($options['json']) {
      echo json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    } else {
      $summary = $report['summary'];
      echo "Theme diagnostic: {$summary['errors']} error(s), {$summary['warnings']} warning(s)\n";
      echo "Templates: {$summary['templates_checked']}, components: {$summary['components_checked']}, assets: {$summary['assets_checked']}\n";
      foreach ($report['issues'] as $issue) {
        echo strtoupper($issue['severity']) . " [{$issue['code']}] {$issue['message']}\n";
      }
    }
    return $report['summary']['errors'] === 0 ? 0 : 1;
  } catch (Throwable $e) {
    fwrite(STDERR, 'Theme diagnostic failed: ' . $e->getMessage() . PHP_EOL);
    return 2;
  }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
  exit(theme_checker_main($argv));
}
