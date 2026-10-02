<?php
// Shared application startup helpers.

/**
 * 入口で共通初期化を行い、表示言語を返す。
 * 設定エラーは利用者向けの安全な画面へ変換する。
 */
function app_bootstrap(string $root): bool {
  require_once $root . '/bootstrap.php';
  try {
    ApplicationBootstrap::boot($root);
  } catch (ConfigException $e) {
    ApplicationBootstrap::renderConfigurationError($root, $e);
  }
  return ApplicationBootstrap::english();
}
