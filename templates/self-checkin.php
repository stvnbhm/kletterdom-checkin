<?php
/** @var \Kletterdom\Http\Csrf $csrf */
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf->token(), ENT_QUOTES) ?>">
    <title>Self-Check-in | Kletterdom</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        html, body { height: 100%; margin: 0; overflow: hidden; }
        #self-checkin-app { height: 100vh; min-height: 32rem; }
        #scanner-viewport { position: relative; width: 100%; height: 100%; min-height: 280px; }
        #scanner-viewport video {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            display: block;
        }
        #scanner-viewport__dashboard { display: none !important; }
        #qr-shaded-region {
            position: absolute !important;
            inset: 0 !important;
            width: 100% !important;
            height: 100% !important;
        }
    </style>
</head>
<body class="font-sans antialiased bg-slate-950 text-white h-full overflow-hidden">

<div id="self-checkin-app" class="flex flex-col overflow-hidden h-full min-h-screen">

    <header class="flex-shrink-0 flex items-center justify-between px-6 py-4 border-b border-slate-800 bg-slate-900/80">
        <div>
            <h1 class="text-xl md:text-2xl font-bold tracking-tight text-white">Kletterdom Self-Check-in</h1>
        </div>
        <time id="self-checkin-clock" class="text-2xl md:text-3xl font-mono font-semibold text-slate-300 tabular-nums"></time>
    </header>

    <div class="flex-1 grid grid-cols-1 lg:grid-cols-2 gap-0 min-h-0">

        <section class="flex flex-col min-h-0 border-b lg:border-b-0 lg:border-r border-slate-800 bg-slate-900 p-4 md:p-6">
            <div class="flex-shrink-0 mb-4">
                <h2 id="scan-headline" class="text-2xl md:text-4xl font-bold text-white">QR-Code scannen</h2>
                <p id="scan-subline" class="text-slate-400 text-lg md:text-xl mt-1">QR-Code vor die Kamera halten</p>
            </div>

            <div class="flex-1 relative rounded-2xl overflow-hidden bg-black border-2 border-slate-700 min-h-[240px] lg:min-h-0">
                <div id="scanner-viewport" class="absolute inset-0"></div>
                <div id="scanner-paused" class="hidden absolute inset-0 z-20 bg-slate-900/70 flex items-center justify-center">
                    <p class="text-xl md:text-2xl font-semibold text-slate-200">Code wird geprüft …</p>
                </div>
            </div>

            <p class="flex-shrink-0 mt-4 text-slate-500 text-sm md:text-base text-center lg:text-left">
                Probleme? Bitte beim Hallendienst melden.
            </p>
        </section>

        <section class="flex flex-col min-h-0 p-4 md:p-8 justify-center">
            <p class="text-xs font-bold uppercase tracking-widest text-slate-500 mb-3">Status</p>

            <div id="status-panel"
                 class="flex-1 flex flex-col items-center justify-center rounded-3xl border-4 p-8 md:p-12 transition-colors duration-300
                        bg-slate-800/50 border-slate-600 text-slate-200"
                 data-state="ready">

                <div id="status-icon" class="text-6xl md:text-8xl mb-6" aria-hidden="true">○</div>
                <p id="status-headline" class="text-3xl md:text-5xl font-bold text-center leading-tight">Bereit</p>
                <p id="status-subline" class="text-lg md:text-2xl text-center mt-4 text-slate-400 max-w-md">QR-Code vorhalten</p>

                <div id="status-hint-ready" class="mt-10 w-full max-w-sm space-y-3 text-slate-400 text-base md:text-lg border-t border-slate-700 pt-8">
                    <p><span class="text-green-400 font-semibold">Direkter Check-in:</span> Grün / Blau</p>
                    <p><span class="text-amber-400 font-semibold">Sonst:</span> Hallendienst</p>
                </div>
            </div>
        </section>
    </div>

    <footer class="flex-shrink-0 px-6 py-2 text-center text-xs text-slate-600 border-t border-slate-800">
        Auto-Reset nach Ergebnis
    </footer>
</div>

<?= \Kletterdom\Support\VendorAssets::scriptTag('html5-qrcode.min.js') ?>
<script src="/assets/js/self-checkin.js"></script>

</body>
</html>
