<?php

final class AdminController {
  public static function login(): void { admin_login(); }
  public static function logout(): void { admin_logout(); }
  public static function dashboard(): void { admin(); }
  public static function manage(): void { admin_manage(); }
  public static function post(): void { admin_post(); }
  public static function edit(): void { admin_edit(); }
  public static function themeSettings(): void { admin_theme_settings(); }
  public static function errorLog(): void { admin_errorlog(); }
  public static function auditLog(): void { admin_auditlog(); }
  public static function temporaryImages(): void { admin_temporary_images(); }
  public static function manageTemporaryImages(): void { admin_temporary_images_manage(); }
}
