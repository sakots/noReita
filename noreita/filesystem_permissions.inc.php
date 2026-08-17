<?php
// filesystem_permissions.inc.php for noReita (C) sakots 2026 MIT License

const FILESYSTEM_PERMISSIONS_INC_VER = 20260817;

final class FilesystemPermissions {
  /**
   * Windowsのchmod()とfileperms()はPOSIXの所有者・グループ・その他の
   * 各ビットを表さないため、数値モードの一致を安全性判定に使用しない。
   */
  public static function modeChecksAreReliable(?string $os_family = null): bool {
    return strcasecmp($os_family ?? PHP_OS_FAMILY, 'Windows') !== 0;
  }

  public static function apply(string $path, int $permission): bool {
    if (!self::modeChecksAreReliable()) return true;
    return @chmod($path, $permission);
  }
}
