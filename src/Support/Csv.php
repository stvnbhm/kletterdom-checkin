<?php

declare(strict_types=1);

namespace Kletterdom\Support;

final class Csv
{
    private const ENCLOSURE = '"';
    private const ESCAPE    = '\\';

    /**
     * @param resource $handle
     * @return array<int,string>|false
     */
    public static function readRow($handle, string $delimiter = ';'): array|false
    {
        return fgetcsv($handle, 0, $delimiter, self::ENCLOSURE, self::ESCAPE);
    }

    /**
     * @param resource     $handle
     * @param array<int,mixed> $fields
     */
    public static function writeRow($handle, array $fields, string $delimiter = ';'): void
    {
        fputcsv($handle, $fields, $delimiter, self::ENCLOSURE, self::ESCAPE);
    }

    /**
     * Schützt CSV-Zellen vor Formula/CSV-Injection beim Öffnen in
     * Excel / LibreOffice / Google Sheets. Werte, die mit =, +, -, @,
     * Tab oder CR beginnen, werden mit einem führenden Apostroph
     * neutralisiert (OWASP-Empfehlung).
     */
    public static function sanitizeCell(?string $value): string
    {
        $value = (string) ($value ?? '');
        if ($value === '') {
            return '';
        }
        $first = $value[0];
        if ($first === '=' || $first === '+' || $first === '-' || $first === '@'
            || $first === "\t" || $first === "\r") {
            return "'" . $value;
        }
        return $value;
    }

    /**
     * Liest die erste Zeile und wählt den häufigsten Kandidaten
     * (;  ,  Tab) als Delimiter. Zeiger wird zurückgesetzt.
     *
     * @param resource $handle
     */
    public static function detectDelimiter($handle): string
    {
        rewind($handle);
        $firstLine = fgets($handle);
        rewind($handle);

        if ($firstLine === false || trim($firstLine) === '') {
            return ';';
        }

        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);

        $candidates = [';', ',', "\t"];
        $counts     = [];
        foreach ($candidates as $candidate) {
            $counts[$candidate] = substr_count($firstLine, $candidate);
        }
        arsort($counts);
        $best = array_key_first($counts);

        return ($counts[$best] ?? 0) > 0 ? $best : ';';
    }

    /**
     * @param array<int,mixed> $row
     */
    public static function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * Parst Geldbeträge robust:
     * - "1.234,56" (österreichisch)  → 1234.56
     * - "1234.56"  (englisch)        → 1234.56
     * - "15,00"                      → 15.00
     */
    public static function parseAmount(?string $value): float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0.0;
        }
        $value = preg_replace('/[\s\x{00A0}]+/u', '', $value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    public static function parseGermanDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('d.m.Y', $value);
        if ($date === false) {
            return null;
        }
        return $date->format('Y-m-d');
    }
}
