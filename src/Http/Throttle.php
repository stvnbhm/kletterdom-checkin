<?php

declare(strict_types=1);

namespace Kletterdom\Http;

/**
 * Datei-basierter Rate-Limiter (gleitendes Zeitfenster pro IP+Route).
 * Reicht für die zwei tatsächlich genutzten Throttle-Routen
 * (POST /halle-register: 3/min, POST /self-checkin/scan: 60/min).
 */
final class Throttle
{
    public function __construct(private readonly string $storagePath)
    {
        if (! is_dir($storagePath)) {
            @mkdir($storagePath, 0775, true);
        }
    }

    public function hit(string $key, int $maxHits, int $windowSeconds): bool
    {
        $file = $this->storagePath . '/' . sha1($key) . '.json';
        $now  = time();

        $handle = @fopen($file, 'c+');
        if ($handle === false) {
            return true;
        }

        try {
            flock($handle, LOCK_EX);
            $raw  = stream_get_contents($handle) ?: '[]';
            $hits = json_decode($raw, true);
            if (! is_array($hits)) {
                $hits = [];
            }

            $hits = array_values(array_filter(
                $hits,
                static fn ($timestamp): bool => is_int($timestamp) && $timestamp >= ($now - $windowSeconds),
            ));

            if (count($hits) >= $maxHits) {
                return false;
            }

            $hits[] = $now;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($hits, JSON_THROW_ON_ERROR));

            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
