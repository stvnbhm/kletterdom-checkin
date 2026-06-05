<?php

declare(strict_types=1);

namespace Kletterdom\Http;

final class Response
{
    /** @param array<string,string> $headers */
    public function __construct(
        public readonly string $body = '',
        public readonly int    $status = 200,
        public readonly array  $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(mixed $payload, int $status = 200): self
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return new self($body, $status, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public static function redirect(string $location, int $status = 303): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public static function noContent(int $status = 204): self
    {
        return new self('', $status);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        if ($this->body !== '') {
            echo $this->body;
        }
    }
}
