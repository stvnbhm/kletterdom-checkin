<?php

declare(strict_types=1);

namespace Kletterdom\Support;

/**
 * Liest SRI-Hashes aus public/assets/vendor-manifest.json (erzeugt via scripts/compute-sri.php).
 */
final class VendorAssets
{
    /** @var array<string,array{path:string,integrity:string}>|null */
    private static ?array $manifest = null;

    /** @return array{path:string,integrity:string}|null */
    public static function get(string $filename): ?array
    {
        self::load();
        return self::$manifest[$filename] ?? null;
    }

    public static function scriptTag(string $filename): string
    {
        $entry = self::get($filename);
        if ($entry === null) {
            return '<script src="/assets/js/' . htmlspecialchars($filename, ENT_QUOTES) . '"></script>';
        }
        return sprintf(
            '<script src="%s" integrity="%s" crossorigin="anonymous"></script>',
            htmlspecialchars($entry['path'], ENT_QUOTES),
            htmlspecialchars($entry['integrity'], ENT_QUOTES),
        );
    }

    private static function load(): void
    {
        if (self::$manifest !== null) {
            return;
        }
        $file = dirname(__DIR__, 2) . '/public/assets/vendor-manifest.json';
        if (! is_readable($file)) {
            self::$manifest = [];
            return;
        }
        $data = json_decode((string) file_get_contents($file), true);
        self::$manifest = is_array($data) ? $data : [];
    }
}
