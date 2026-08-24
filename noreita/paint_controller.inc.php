<?php

final class PaintController {
  public static function paint(ApplicationContext $context, string $rep, ?int $replyTo): void { paint_form($context, $rep, $replyTo); }
  public static function temporary(ApplicationContext $context): void { paint_com($context, 'tmp'); }
  public static function editImage(ApplicationContext $context): void { paint_com($context, ''); }
  public static function animation(ApplicationContext $context): void { open_pch(); }
  public static function continue(ApplicationContext $context): void { in_continue(); }
  public static function uploadAnimation(ApplicationContext $context): void { animation_upload($context); }
  public static function temporaryImage(ApplicationContext $context): void { temporary_image($context); }
}
