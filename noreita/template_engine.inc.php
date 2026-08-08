<?php
// noReita template engine abstraction (C) sakots 2026 MIT License

interface TemplateEngine {
  /** @param array<string,mixed> $data */
  public function render(string $template, array $data = []): string;
}

final class BladeTemplateEngine implements TemplateEngine {
  private \eftec\bladeone\BladeOne $blade;

  public function __construct(string $views, string $cache) {
    $this->blade = new \eftec\bladeone\BladeOne($views, $cache, \eftec\bladeone\BladeOne::MODE_AUTO);
    $this->blade->pipeEnable = true;
  }

  public function render(string $template, array $data = []): string {
    TemplateEngineFactory::assertTemplateName($template);
    return $this->blade->run($template, $data);
  }
}

final class TwigTemplateEngine implements TemplateEngine {
  private \Twig\Environment $twig;
  private BladeTemplateEngine $blade_fallback;

  public function __construct(string $views, string $cache) {
    $loader = new \Twig\Loader\FilesystemLoader($views);
    $this->twig = new \Twig\Environment($loader, [
      'cache' => $cache . DIRECTORY_SEPARATOR . 'twig',
      'auto_reload' => true,
      'autoescape' => 'html',
    ]);
    $this->blade_fallback = new BladeTemplateEngine($views, $cache);
  }

  public function render(string $template, array $data = []): string {
    TemplateEngineFactory::assertTemplateName($template);
    $twig_template = $template . '.twig';
    if ($this->twig->getLoader()->exists($twig_template)) {
      return $this->twig->render($twig_template, $data);
    }
    return $this->blade_fallback->render($template, $data);
  }
}

final class TemplateEngineFactory {
  public static function create(string $engine, string $views, string $cache): TemplateEngine {
    if (!is_dir($views)) {
      throw new RuntimeException('Template directory is missing: ' . $views);
    }
    if (!is_dir($cache) || !is_writable($cache)) {
      throw new RuntimeException('Template cache directory is not writable: ' . $cache);
    }

    return match ($engine) {
      'blade' => new BladeTemplateEngine($views, $cache),
      'twig' => new TwigTemplateEngine($views, $cache),
      default => throw new InvalidArgumentException('Unsupported template engine: ' . $engine),
    };
  }

  public static function assertTemplateName(string $template): void {
    if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._\/-]*\z/D', $template) !== 1
      || str_contains($template, '..')) {
      throw new InvalidArgumentException('Invalid template name.');
    }
  }
}
