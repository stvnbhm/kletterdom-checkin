#!/usr/bin/env php
<?php
/**
 * Berechnet SRI-Hashes (sha384) für vendorte JS-Dateien und schreibt
 * public/assets/vendor-manifest.json.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'html5-qrcode.min.js' => 'public/assets/js/html5-qrcode.min.js',
    'chart.umd.min.js'    => 'public/assets/js/chart.umd.min.js',
];

$manifest = [];

foreach ($files as $name => $rel) {
    $path = $root . '/' . $rel;
    if (! is_readable($path)) {
        fwrite(STDERR, "Missing: {$rel} — run: npm run vendor:js\n");
        exit(1);
    }
    $hash = base64_encode(hash('sha384', (string) file_get_contents($path), true));
    $manifest[$name] = [
        'path'      => '/assets/js/' . $name,
        'integrity' => 'sha384-' . $hash,
    ];
    fwrite(STDOUT, "{$name}: sha384-{$hash}\n");
}

$out = $root . '/public/assets/vendor-manifest.json';
file_put_contents($out, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
fwrite(STDOUT, "Written: {$out}\n");
