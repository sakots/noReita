<?php

/**
 * Request-scoped state shared while legacy handlers are migrated away from globals.
 * Template data is retained by reference so existing $dat mutations remain visible.
 */
final class ApplicationContext {
  /** @var array<string,mixed> */
  public array $data;

  /** @var array<string,mixed> */
  public array $request;

  /**
   * @param array<string,mixed> $data
   * @param array<string,mixed> $request
   */
  public function __construct(
    public readonly bool $english,
    public readonly TemplateEngine $templates,
    array &$data,
    public readonly string $usercode,
    public readonly string $requestMethod,
    public readonly string $themeDirectory,
    array $request = [],
  ) {
    $this->data =& $data;
    $this->request = $request;
  }
}

final class ApplicationContextRegistry {
  private static ?ApplicationContext $current = null;

  public static function set(ApplicationContext $context): void {
    self::$current = $context;
  }

  public static function isInitialized(): bool {
    return self::$current !== null;
  }

  public static function current(): ApplicationContext {
    if (self::$current === null) {
      throw new LogicException('ApplicationContext has not been initialized.');
    }
    return self::$current;
  }
}
