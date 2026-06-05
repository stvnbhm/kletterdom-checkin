<?php

declare(strict_types=1);

namespace Kletterdom\Support;

use DateTimeInterface;
use RuntimeException;
use Throwable;

/**
 * Deterministischer HMAC-Hash aus Nachname + Geburtsdatum für den
 * Abgleich Mitglieder ↔ Registrierungen. Keyed HMAC ist hier wichtig:
 * Nachname + Geburtsdatum hat geringe Entropie und wäre als reiner
 * SHA256 brute-forcebar.
 */
final class PrivacyIndex
{
    public function __construct(private readonly string $hashKey)
    {
        if ($this->hashKey === '') {
            throw new RuntimeException('HASH_KEY must not be empty.');
        }
    }

    public function normalizeName(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }
        $lower = mb_strtolower($trimmed, 'UTF-8');
        return preg_replace('/\s+/u', ' ', $lower);
    }

    public function normalizeBirthDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        try {
            $ts = strtotime((string) $value);
            if ($ts === false) {
                return null;
            }
            return date('Y-m-d', $ts);
        } catch (Throwable) {
            return null;
        }
    }

    public function buildNameBirthHash(?string $lastName, mixed $birthDate): ?string
    {
        $lastNorm  = $this->normalizeName($lastName);
        $birthNorm = $this->normalizeBirthDate($birthDate);
        if ($lastNorm === null || $birthNorm === null) {
            return null;
        }
        return hash_hmac('sha256', $lastNorm . '|' . $birthNorm, $this->hashKey);
    }
}
