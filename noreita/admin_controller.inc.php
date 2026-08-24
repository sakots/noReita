<?php

final class AdminController {
  public static function login(ApplicationContext $context): void { admin_login($context); }
  public static function logout(ApplicationContext $context): void { admin_logout($context); }
  public static function dashboard(ApplicationContext $context): void { admin($context); }
  public static function manage(ApplicationContext $context): void { admin_manage($context); }
  public static function post(ApplicationContext $context): void { admin_post($context); }
  public static function edit(ApplicationContext $context): void { admin_edit($context); }
  public static function themeSettings(ApplicationContext $context): void { admin_theme_settings($context); }
  public static function errorLog(ApplicationContext $context): void { admin_errorlog($context); }
  public static function auditLog(ApplicationContext $context): void { admin_auditlog($context); }
  public static function temporaryImages(ApplicationContext $context): void { admin_temporary_images($context); }
  public static function manageTemporaryImages(ApplicationContext $context): void { admin_temporary_images_manage($context); }
}
