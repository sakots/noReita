<?php
// Public board endpoints. Handler bodies remain function-based while the route boundary is migrated.

final class BoardController {
  public static function index(ApplicationContext $context): void {
    def($context);
  }

  public static function response(ApplicationContext $context): void {
    res($context);
  }

  public static function catalog(ApplicationContext $context): void {
    catalog($context);
  }

  public static function search(ApplicationContext $context): void {
    search($context);
  }
}
