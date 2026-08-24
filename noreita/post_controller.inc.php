<?php

final class PostController {
  public static function register(): void { regist(); }
  public static function edit(): void { editform(); }
  public static function saveEdit(): void { editexec(); }
  public static function delete(): void { delmode(); }
  public static function replaceImage(): void { picreplace(); }
}
