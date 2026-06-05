<?php

declare(strict_types=1);

namespace Kletterdom\Http;

final class Flash
{
    private const KEY = '_flash';

    public function __construct(private readonly Session $session)
    {
    }

    public function set(string $key, mixed $value): void
    {
        $bag       = $this->bag();
        $bag[$key] = $value;
        $this->session->set(self::KEY, $bag);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $bag = $this->bag();
        if (! array_key_exists($key, $bag)) {
            return $default;
        }

        $value = $bag[$key];
        unset($bag[$key]);
        $this->session->set(self::KEY, $bag);

        return $value;
    }

    /**
     * Returns the value without consuming it. Used while rendering
     * templates so different partials can both read the same flash.
     */
    public function peek(string $key, mixed $default = null): mixed
    {
        return $this->bag()[$key] ?? $default;
    }

    /**
     * Drops every remaining flash entry. Should be called once the
     * template has finished rendering so values do not survive the
     * next request.
     */
    public function clear(): void
    {
        $this->session->set(self::KEY, []);
    }

    /**
     * @param array<string,string> $messages
     */
    public function setErrors(array $messages): void
    {
        $this->set('errors', $messages);
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        $errors = $this->bag()['errors'] ?? [];
        return is_array($errors) ? $errors : [];
    }

    public function withOld(array $input): void
    {
        $bag        = $this->bag();
        $bag['old'] = $input;
        $this->session->set(self::KEY, $bag);
    }

    public function old(string $key, mixed $default = ''): mixed
    {
        $old = $this->bag()['old'] ?? [];
        return is_array($old) ? ($old[$key] ?? $default) : $default;
    }

    /** @return array<string,mixed> */
    private function bag(): array
    {
        $bag = $this->session->get(self::KEY, []);
        return is_array($bag) ? $bag : [];
    }
}
