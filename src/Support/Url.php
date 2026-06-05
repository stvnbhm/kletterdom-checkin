<?php

declare(strict_types=1);

namespace Kletterdom\Support;

final class Url
{
    /**
     * Baut eine absolute App-URL relativ zur `APP_URL`-Basis. Wird
     * vor allem für die QR-Verify-URL benötigt, weil der QR-Inhalt
     * auch dann gültig sein muss, wenn er von einem anderen Gerät
     * (z. B. dem Self-Check-in-Terminal) gescannt wird.
     */
    public static function to(string $path): string
    {
        $base = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
        $path = '/' . ltrim($path, '/');
        return $base . $path;
    }

    /** @param array<string,scalar|null> $params */
    public static function with(string $path, array $params): string
    {
        $filtered = array_filter(
            $params,
            static fn ($v): bool => $v !== null && $v !== '',
        );
        if ($filtered === []) {
            return $path;
        }
        return $path . (str_contains($path, '?') ? '&' : '?') . http_build_query($filtered);
    }
}
