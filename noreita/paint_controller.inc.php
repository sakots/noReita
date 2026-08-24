<?php

final class PaintController {
  public static function paint(string $rep, ?int $replyTo): void { paint_form($rep, $replyTo); }
  public static function temporary(): void { paint_com('tmp'); }
  public static function editImage(): void { paint_com(''); }
  public static function animation(): void { open_pch(); }
  public static function continue(): void { in_continue(); }
  public static function uploadAnimation(): void { animation_upload(); }
  public static function temporaryImage(): void { temporary_image(); }
}
