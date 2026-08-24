<?php
// Public board endpoints. Handler bodies remain function-based while the route boundary is migrated.

final class BoardController {
  public static function index(): void {
    def();
  }

  public static function response(): void {
    res();
  }

  public static function catalog(): void {
    catalog();
  }

  public static function search(): void {
    search();
  }
}
