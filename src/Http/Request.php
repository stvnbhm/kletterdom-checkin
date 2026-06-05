<?php

declare(strict_types=1);

namespace Kletterdom\Http;

final class Request
{
    /**
     * @param array<string,mixed>  $query
     * @param array<string,mixed>  $post
     * @param array<string,mixed>  $server
     * @param array<string,mixed>  $files
     */
    public function __construct(
        public readonly array  $query,
        public readonly array  $post,
        public readonly array  $server,
        public readonly array  $files,
        public readonly string $method,
        public readonly string $path,
        public readonly string $rawBody,
    ) {
    }

    public static function capture(): self
    {
        $server = $_SERVER;
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $uri    = (string) ($server['REQUEST_URI'] ?? '/');
        $path   = parse_url($uri, PHP_URL_PATH) ?: '/';
        $body   = (string) file_get_contents('php://input');
        $post   = $_POST;

        if ($method === 'POST' && isset($post['_method'])) {
            $method = strtoupper((string) $post['_method']);
        }

        if ($body !== '' && empty($post)) {
            $contentType = (string) ($server['CONTENT_TYPE'] ?? '');
            if (str_contains($contentType, 'application/json')) {
                $decoded = json_decode($body, true);
                if (is_array($decoded)) {
                    $post = $decoded;
                }
            }
        }

        return new self($_GET, $post, $server, $_FILES, $method, $path, $body);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }
        return $this->query[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        return is_scalar($value) ? (string) $value : $default;
    }

    public function boolean(string $key): bool
    {
        return filter_var($this->input($key, false), FILTER_VALIDATE_BOOL);
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function header(string $name): ?string
    {
        $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value      = $this->server[$normalized] ?? null;
        return is_string($value) ? $value : null;
    }

    public function expectsJson(): bool
    {
        $accept    = (string) ($this->server['HTTP_ACCEPT'] ?? '');
        $requested = (string) ($this->server['HTTP_X_REQUESTED_WITH'] ?? '');
        return str_contains($accept, 'application/json') || strcasecmp($requested, 'XMLHttpRequest') === 0;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '');
    }

    public function userAgent(): string
    {
        return (string) ($this->server['HTTP_USER_AGENT'] ?? '');
    }

    public function csrfToken(): ?string
    {
        $token = $this->input('_token');
        if (is_string($token) && $token !== '') {
            return $token;
        }
        return $this->header('X-CSRF-TOKEN');
    }
}
