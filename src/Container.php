<?php

declare(strict_types=1);

namespace Kletterdom;

use Closure;
use RuntimeException;

/**
 * Minimaler Service-Container: jede Definition wird beim ersten Aufruf
 * resolved und danach gecached. Reicht für ein Plain-PHP-Setup und
 * macht die Controller per Konstruktor-Injektion testbar.
 */
final class Container
{
    /** @var array<string, Closure(self):mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function set(string $key, Closure $factory): void
    {
        $this->factories[$key] = $factory;
        unset($this->instances[$key]);
    }

    public function get(string $key): mixed
    {
        if (array_key_exists($key, $this->instances)) {
            return $this->instances[$key];
        }

        if (! isset($this->factories[$key])) {
            throw new RuntimeException("Container has no entry for {$key}");
        }

        return $this->instances[$key] = ($this->factories[$key])($this);
    }
}
