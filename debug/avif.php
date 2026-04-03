<?php
// GDライブラリの確認
echo "=== GD Library ===<br>\n";
if (extension_loaded('gd')) {
  echo "GD: インストール済み<br>\n";
  echo "AVIF (GD): " . (function_exists('imageavif') ? '✅サポート済み' : '❌未サポート') . "<br>\n";
  $gd_info = gd_info();
  echo "GD Version: " . $gd_info['GD Version'] . "<br>\n";
} else {
  echo "GD: インストールされていません<br>\n";
}
