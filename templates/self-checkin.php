<?php
/** @var \Kletterdom\Http\Csrf $csrf */
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf->token(), ENT_QUOTES) ?>">
    <title>Self-Check-in | Kletterdom</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            overflow: hidden;
        }
        @supports (height: 100dvh) {
            html, body { height: 100dvh; }
        }
        #self-checkin-app {
            height: 100vh;
            height: 100dvh;
            min-height: -webkit-fill-available;
        }
        /*
         * Only clip the scanner shell — never set object-fit/100% on the
         * <video>/<canvas> html5-qrcode injects (breaks iOS QR decoding).
         */
        #scanner-shell {
            overflow: hidden;
            isolation: isolate;
        }
        #scanner-viewport {
            position: absolute;
            inset: 0;
            overflow: hidden;
            width: 100%;
            height: 100%;
        }
        #scanner-viewport__dashboard { display: none !important; }
        #camera-select,
        #camera-select option {
            background-color: #ffffff;
            color: #111827;
        }

        /* Phone layout: fixed-height camera, scrollable status below */
        @media (max-width: 1023px) {
            #self-checkin-main {
                display: flex;
                flex-direction: column;
                min-height: 0;
                overflow: hidden;
            }
            #scanner-section {
                flex: 0 0 auto;
                min-height: 0;
                padding-bottom: 0.75rem;
            }
            #scanner-shell {
                height: 32dvh;
                max-height: 32dvh;
                min-height: 12rem;
                flex: 0 0 auto;
            }
            #status-section {
                flex: 1 1 auto;
                min-height: 0;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
                padding-top: 0.75rem;
            }
            #status-panel {
                padding: 1rem 1.25rem;
                min-height: 0;
            }
            #status-icon {
                font-size: 2.25rem;
                margin-bottom: 0.5rem;
            }
            #status-headline {
                font-size: 1.375rem;
            }
            #status-subline {
                font-size: 0.95rem;
                margin-top: 0.5rem;
            }
            #status-hint-ready {
                margin-top: 0.75rem;
                padding-top: 0.75rem;
                font-size: 0.85rem;
            }
            #scan-headline {
                font-size: 1.25rem;
            }
            #scan-subline {
                font-size: 0.95rem;
            }
            #self-checkin-header h1 {
                font-size: 1rem;
            }
            #self-checkin-clock {
                font-size: 1.25rem;
            }
        }

        @media (min-width: 1024px) {
            #self-checkin-main {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }
            #scanner-shell {
                min-height: 280px;
            }
        }
    </style>
</head>
<body class="font-sans antialiased bg-slate-950 text-white h-full overflow-hidden">

<div id="self-checkin-app" class="flex flex-col overflow-hidden h-full">

    <header id="self-checkin-header" class="flex-shrink-0 flex items-center justify-between px-4 sm:px-6 py-3 sm:py-4 border-b border-slate-800 bg-slate-900/80"
            style="padding-top: max(0.75rem, env(safe-area-inset-top));">
        <div>
            <h1 class="text-xl md:text-2xl font-bold tracking-tight text-white">Kletterdom Self-Check-in</h1>
        </div>
        <time id="self-checkin-clock" class="text-2xl md:text-3xl font-mono font-semibold text-slate-300 tabular-nums"></time>
    </header>

    <div id="self-checkin-main" class="flex-1 gap-0 min-h-0 overflow-hidden">

        <section id="scanner-section" class="flex flex-col min-h-0 border-b lg:border-b-0 lg:border-r border-slate-800 bg-slate-900 p-3 sm:p-4 md:p-6">
            <div class="flex-shrink-0 mb-2 sm:mb-3">
                <h2 id="scan-headline" class="text-2xl md:text-4xl font-bold text-white">QR-Code scannen</h2>
                <p id="scan-subline" class="text-slate-400 text-lg md:text-xl mt-1">QR-Code vor die Kamera halten</p>
                <div id="camera-picker" class="hidden mt-2 sm:mt-3 flex flex-col sm:flex-row sm:items-center gap-2">
                    <label for="camera-select" class="text-xs font-semibold uppercase tracking-wide text-slate-500 shrink-0">Kamera</label>
                    <select id="camera-select"
                            class="flex-1 min-w-0 rounded-lg border border-gray-300 bg-white text-gray-900 text-sm px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </select>
                </div>
            </div>

            <div id="scanner-shell" class="relative rounded-2xl overflow-hidden bg-black border-2 border-slate-700 lg:flex-1 lg:min-h-0">
                <div id="scanner-viewport"></div>
                <div id="scanner-paused" class="hidden absolute inset-0 z-20 bg-slate-900/70 flex items-center justify-center">
                    <p class="text-xl md:text-2xl font-semibold text-slate-200">Code wird geprüft …</p>
                </div>
            </div>

            <p class="flex-shrink-0 mt-2 sm:mt-3 text-slate-500 text-xs sm:text-sm md:text-base text-center lg:text-left">
                Probleme? Bitte beim Hallendienst melden.
            </p>
        </section>

        <section id="status-section" class="flex flex-col min-h-0 p-3 sm:p-4 md:p-8 lg:justify-center">
            <p class="text-xs font-bold uppercase tracking-widest text-slate-500 mb-2 sm:mb-3 shrink-0">Status</p>

            <div id="status-panel"
                 class="flex flex-col items-center justify-center rounded-3xl border-4 p-8 md:p-12 transition-colors duration-300
                        bg-slate-800/50 border-slate-600 text-slate-200 lg:flex-1"
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

    <footer class="flex-shrink-0 px-4 sm:px-6 py-2 text-center text-xs text-slate-600 border-t border-slate-800"
            style="padding-bottom: max(0.5rem, env(safe-area-inset-bottom));">
        Auto-Reset nach Ergebnis
    </footer>
</div>

<?= \Kletterdom\Support\VendorAssets::scriptTag('html5-qrcode.min.js') ?>
<script src="/assets/js/self-checkin.js?v=20260608b"></script>

</body>
</html>
