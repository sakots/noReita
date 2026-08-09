<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/noreita/config_loader.inc.php';

function migration_usage(string $script): string {
  return <<<TEXT
Usage:
  php {$script} --source=/path/to/v3/config.php --output=/path/to/v4/config.local.php [--force]
  php {$script} --source=/path/to/v3/config.php --dry-run

The source is executed as trusted PHP. Back it up and inspect the generated array before deployment.
TEXT;
}

/** @return array{source:string,output:?string,dry_run:bool,force:bool} */
function migration_options(array $arguments): array {
  $source = '';
  $output = null;
  $dry_run = false;
  $force = false;
  foreach (array_slice($arguments, 1) as $argument) {
    if (strpos($argument, '--source=') === 0) {
      $source = substr($argument, strlen('--source='));
    } elseif (strpos($argument, '--output=') === 0) {
      $output = substr($argument, strlen('--output='));
    } elseif ($argument === '--dry-run') {
      $dry_run = true;
    } elseif ($argument === '--force') {
      $force = true;
    } elseif ($argument === '--help' || $argument === '-h') {
      echo migration_usage($arguments[0]) . "\n";
      exit(0);
    } else {
      throw new InvalidArgumentException("Unknown option: {$argument}");
    }
  }
  if ($source === '' || (!$dry_run && ($output === null || $output === ''))) {
    throw new InvalidArgumentException('Specify --source and either --output or --dry-run.');
  }
  return ['source' => $source, 'output' => $output, 'dry_run' => $dry_run, 'force' => $force];
}

/** @return array{constants:array<string,mixed>,variables:array<string,mixed>} */
function migration_read_legacy(string $source): array {
  if (!is_file($source) || !is_readable($source)) {
    throw new RuntimeException("Legacy configuration is missing or unreadable: {$source}");
  }
  $before = get_defined_constants(true)['user'] ?? [];
  $variables = (static function (string $legacy_file): array {
    require $legacy_file;
    unset($legacy_file);
    return get_defined_vars();
  })($source);
  $after = get_defined_constants(true)['user'] ?? [];
  return ['constants' => array_diff_key($after, $before), 'variables' => $variables];
}

/** @return mixed */
function migration_value_at(array $values, string $key) {
  $current = $values;
  foreach (explode('.', $key) as $segment) $current = $current[$segment];
  return $current;
}

/** @param mixed $value */
function migration_set(array &$values, string $key, $value): void {
  $segments = explode('.', $key);
  $current =& $values;
  foreach ($segments as $index => $segment) {
    if ($index === count($segments) - 1) {
      $current[$segment] = $value;
      return;
    }
    if (!isset($current[$segment]) || !is_array($current[$segment])) $current[$segment] = [];
    $current =& $current[$segment];
  }
}

/** @param mixed $value @param mixed $default @return mixed */
function migration_coerce($value, $default, string $legacy_name) {
  if (is_bool($default) && ($value === 0 || $value === 1 || $value === '0' || $value === '1')) {
    return (bool)$value;
  }
  if (is_int($default) && is_string($value) && preg_match('/\A-?\d+\z/D', $value) === 1) {
    return (int)$value;
  }
  if (gettype($value) !== gettype($default)) {
    throw new RuntimeException("Cannot convert legacy setting {$legacy_name}: incompatible type.");
  }
  return $value;
}

/** @return array<string,mixed> */
function migration_convert(array $legacy, array $defaults): array {
  $overrides = [];
  foreach (LegacyConfigMap::constantMap() as $legacy_name => $key) {
    if (!array_key_exists($legacy_name, $legacy['constants'])) continue;
    $default = migration_value_at($defaults, $key);
    $value = migration_coerce($legacy['constants'][$legacy_name], $default, $legacy_name);
    if ($value !== $default) migration_set($overrides, $key, $value);
  }
  foreach (LegacyConfigMap::variableMap() as $legacy_name => $key) {
    if (!array_key_exists($legacy_name, $legacy['variables'])) continue;
    $default = migration_value_at($defaults, $key);
    $value = migration_coerce($legacy['variables'][$legacy_name], $default, '$' . $legacy_name);
    if ($value !== $default) migration_set($overrides, $key, $value);
  }
  // 必須値は現在の既定値と偶然一致していても、設置者設定として明示する。
  foreach (['admin.password', 'site.base_url', 'identity.seed', 'security.paint_password'] as $key) {
    foreach (LegacyConfigMap::constantMap() as $legacy_name => $mapped) {
      if ($mapped === $key && array_key_exists($legacy_name, $legacy['constants'])) {
        migration_set($overrides, $key, migration_coerce(
          $legacy['constants'][$legacy_name], migration_value_at($defaults, $key), $legacy_name
        ));
      }
    }
    foreach (LegacyConfigMap::variableMap() as $legacy_name => $mapped) {
      if ($mapped === $key && array_key_exists($legacy_name, $legacy['variables'])) {
        migration_set($overrides, $key, migration_coerce(
          $legacy['variables'][$legacy_name], migration_value_at($defaults, $key), '$' . $legacy_name
        ));
      }
    }
  }
  Config::resolve($defaults, $overrides);
  return $overrides;
}

function migration_render(array $overrides, string $source): string {
  return "<?php\n"
    . "// Generated from " . basename($source) . " by migrate-config-v3.php.\n"
    . "// Review this file before deployment.\n\nreturn "
    . var_export($overrides, true) . ";\n";
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
  try {
    $options = migration_options($argv);
    $defaults = require dirname(__DIR__) . '/noreita/config.php';
    $content = migration_render(
      migration_convert(migration_read_legacy($options['source']), $defaults),
      $options['source']
    );
    if ($options['dry_run']) {
      echo $content;
      exit(0);
    }
    $output = (string)$options['output'];
    if (is_file($output) && !$options['force']) {
      throw new RuntimeException("Output already exists; use --force to replace it: {$output}");
    }
    $directory = dirname($output);
    if (!is_dir($directory) || !is_writable($directory)) {
      throw new RuntimeException("Output directory is not writable: {$directory}");
    }
    $temporary = tempnam($directory, '.config-local-');
    if ($temporary === false || file_put_contents($temporary, $content, LOCK_EX) === false
      || !chmod($temporary, 0600) || !rename($temporary, $output)) {
      if (is_string($temporary) && is_file($temporary)) unlink($temporary);
      throw new RuntimeException('Failed to write config.local.php safely.');
    }
    echo "Created {$output}\n";
  } catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
  }
}
