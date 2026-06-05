<?php

declare(strict_types=1);

namespace Kletterdom\Http;

/**
 * Sehr schlanker Template-Renderer: führt eine PHP-Datei mit den
 * gegebenen Variablen aus und gibt das gepufferte Output zurück.
 * Templates verwenden direkt PHP — kein eigener Parser.
 */
final class View
{
    /** @var array<string,mixed> */
    private array $shared = [];

    public function __construct(private readonly string $templatePath)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /** @param array<string,mixed> $data */
    public function render(string $template, array $data = []): string
    {
        $file = $this->templatePath . '/' . $template . '.php';
        if (! is_file($file)) {
            throw new \RuntimeException("Template not found: {$template}");
        }

        $combined = array_merge($this->shared, $data);
        $combined['view'] = $this;

        ob_start();
        try {
            (static function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })($file, $combined);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $data */
    public function partial(string $template, array $data = []): void
    {
        echo $this->render($template, $data);
    }
}
