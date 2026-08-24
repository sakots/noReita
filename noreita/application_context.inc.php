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
