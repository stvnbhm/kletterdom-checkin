<?php

declare(strict_types=1);

namespace Kletterdom\Http;

use DateTimeImmutable;

/**
 * Sammelt Validierungsfehler ohne Framework. Jeder Check liefert das
 * Validator-Objekt zurück, sodass die Aufrufe in den Controllern lesbar
 * verkettet werden können.
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @param array<string,mixed> $data */
    public function __construct(private readonly array $data)
    {
    }

    public function required(string $field, string $message): self
    {
        $value = $this->raw($field);
        if ($value === null || (is_string($value) && trim($value) === '')) {
            $this->setError($field, $message);
        }
        return $this;
    }

    public function accepted(string $field, string $message): self
    {
        $value = $this->raw($field);
        if (! in_array($value, ['1', 1, true, 'on', 'yes', 'true'], true)) {
            $this->setError($field, $message);
        }
        return $this;
    }

    public function email(string $field, string $message): self
    {
        $value = $this->raw($field);
        if ($value === null || $value === '') {
            return $this;
        }
        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->setError($field, $message);
        }
        return $this;
    }

    public function maxLength(string $field, int $max, string $message): self
    {
        $value = $this->raw($field);
        if (is_string($value) && mb_strlen($value) > $max) {
            $this->setError($field, $message);
        }
        return $this;
    }

    public function in(string $field, array $allowed, string $message): self
    {
        $value = $this->raw($field);
        if ($value === null || $value === '') {
            return $this;
        }
        if (! in_array((string) $value, $allowed, true)) {
            $this->setError($field, $message);
        }
        return $this;
    }

    public function regex(string $field, string $pattern, string $message): self
    {
        $value = $this->raw($field);
        if ($value === null || $value === '') {
            return $this;
        }
        if (! is_string($value) || ! preg_match($pattern, $value)) {
            $this->setError($field, $message);
        }
        return $this;
    }

    public function dateBetween(string $field, string $minIso, string $maxIso, string $message): self
    {
        $value = $this->raw($field);
        if ($value === null || $value === '') {
            return $this;
        }
        $date = $this->parseDate((string) $value);
        if (! $date instanceof DateTimeImmutable) {
            $this->setError($field, $message);
            return $this;
        }
        $min = new DateTimeImmutable($minIso);
        $max = new DateTimeImmutable($maxIso);
        if ($date < $min || $date > $max) {
            $this->setError($field, $message);
        }
        return $this;
    }

    public function empty(string $field, string $message): self
    {
        $value = $this->raw($field);
        if ($value !== null && $value !== '') {
            $this->setError($field, $message);
        }
        return $this;
    }

    public function setError(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    private function raw(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
