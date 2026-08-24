<?php

final class PostController {
  public static function register(ApplicationContext $context): void { regist($context); }
  public static function edit(ApplicationContext $context): void { editform(context: $context); }
  public static function saveEdit(ApplicationContext $context): void { editexec($context); }
  public static function delete(ApplicationContext $context): void { delmode($context); }
  public static function replaceImage(ApplicationContext $context): void { picreplace($context); }
}
