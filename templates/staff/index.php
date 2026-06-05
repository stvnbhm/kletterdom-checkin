<?php
/** @var \Kletterdom\Http\Csrf $csrf
 *  @var \Kletterdom\Http\View $view
 *  @var ?string               $query
 *  @var array                 $stats
 *  @var array                 $registrations
 *  @var array                 $pastCheckinDates
 *  @var array                 $pagination
 */
$token = $csrf->token();
ob_start();
?>
<main class="py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div id="staff-stats">
            <?php $view->partial('staff/stats', ['stats' => $stats]); ?>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
            <h2 class="text-xl font-semibold text-gray-800">Check-In Ansicht</h2>
            <div class="flex flex-wrap items-center gap-2">

                <form method="POST" action="/hallendienst/checkout-all"
                      onsubmit="askConfirm('Alle aktuell eingecheckten Personen wirklich auschecken?', this); return false;">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                    <?php if ($query !== null && $query !== ''): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES) ?>">
                    <?php endif; ?>
                    <button type="submit" class="inline-flex items-center gap-2 bg-white border border-gray-300 text-gray-700 rounded-lg px-4 py-2 text-sm font-semibold hover:bg-red-50 hover:border-red-300 hover:text-red-700 transition min-h-[44px]">
                        Alle auschecken
                    </button>
                </form>

                <button id="qr-toggle-btn" type="button" onclick="staffToggleScanner()"
                        class="inline-flex items-center gap-2 bg-indigo-600 text-white rounded-lg px-4 py-2 text-sm font-semibold hover:bg-indigo-700 transition min-h-[44px]">
                    QR-Code scannen
                    <span class="hidden md:inline text-xs font-normal text-indigo-100">(F2)</span>
                </button>
            </div>
        </div>

        <div id="qr-scanner-panel" class="hidden mb-6 bg-white border border-indigo-200 rounded-xl shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 bg-indigo-50 border-b border-indigo-100">
                <span class="text-sm font-semibold text-indigo-800">Kamera-Scanner</span>
                <button type="button" onclick="staffToggleScanner()" class="text-indigo-400 hover:text-indigo-700">✕</button>
            </div>
            <div class="p-4">
                <div class="mb-3 flex flex-col sm:flex-row sm:items-center gap-3">
                    <div class="flex items-center gap-3 flex-1">
                        <label for="camera-select" class="text-xs text-gray-500 whitespace-nowrap">Kamera:</label>
                        <select id="camera-select" class="flex-1 border border-gray-300 rounded-md px-2 py-1.5 text-sm bg-white text-gray-900 focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Wird geladen…</option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="staffStartScanner()" class="flex-1 sm:flex-none justify-center inline-flex items-center bg-indigo-600 text-white rounded-md px-3 py-1.5 text-xs font-semibold hover:bg-indigo-700 transition">Starten</button>
                        <button type="button" onclick="staffStopScanner()" class="flex-1 sm:flex-none justify-center inline-flex items-center bg-white border border-gray-300 text-gray-700 rounded-md px-3 py-1.5 text-xs font-semibold hover:bg-gray-50 transition">Stopp</button>
                    </div>
                </div>
                <div id="qr-reader" class="rounded-lg overflow-hidden border border-gray-200 bg-gray-900"
                     style="width:100%; max-width:480px; min-height:240px; margin:0 auto;"></div>
                <div id="qr-status" class="mt-3 hidden rounded-lg px-4 py-3 text-sm font-medium"></div>
            </div>
        </div>

        <div class="mb-6 rounded-xl border border-orange-200 bg-orange-50 p-4 shadow-sm">
            <form method="GET" action="/hallendienst" class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                <div class="flex-1 min-w-0 sm:min-w-[320px]">
                    <label for="q" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-orange-700">Suche</label>
                    <input id="q" type="text" name="q" value="<?= htmlspecialchars((string) ($query ?? ''), ENT_QUOTES) ?>"
                           placeholder="Name oder Mitgliedsnummer suchen"
                           class="block w-full rounded-lg border border-orange-200 bg-white py-2.5 pl-3 pr-3 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-orange-500 focus:ring-2 focus:ring-orange-200">
                </div>
                <div class="flex gap-2 flex-col sm:flex-row">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-orange-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-orange-700">Suchen</button>
                    <a href="/hallendienst" class="inline-flex items-center justify-center rounded-lg border border-orange-200 bg-white px-4 py-2.5 text-sm font-semibold text-orange-700 transition hover:bg-orange-100 no-underline">Zurücksetzen</a>
                </div>
            </form>
        </div>

        <div id="staff-list">
            <?php $view->partial('staff/list', [
                'registrations'    => $registrations,
                'pastCheckinDates' => $pastCheckinDates,
                'query'            => $query,
                'pagination'       => $pagination,
            ]); ?>
        </div>
    </div>
</main>

<div id="confirmModal" class="fixed inset-0 z-[100] hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-gray-900/50" onclick="closeConfirmModal()"></div>
    <div class="relative min-h-full flex items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-900">Bitte bestätigen</h3>
            </div>
            <div class="px-5 py-4 flex flex-col gap-3">
                <p id="confirmModalText" class="text-sm text-gray-600 leading-relaxed"></p>

                <div id="confirmOrangeHint" class="hidden rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                    <span id="confirmOrangeReason" class="block font-semibold"></span>
                </div>

                <div id="confirmKulanzWrapper" class="hidden rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-600">
                    <span class="font-semibold block mb-1 text-gray-500">ℹ️ Letzter Kulanzgrund:</span>
                    <span id="confirmLastKulanz" class="block"></span>
                </div>

                <div id="confirmRedNextHint" class="hidden rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                    <span class="font-semibold block mb-1">Achtung:</span>
                    Nach diesem Check-in wird der Status auf <strong>Rot</strong> gesetzt.
                </div>

                <div id="confirmOrangeKulanz" class="hidden">
                    <label for="confirmKulanzInput" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                        Kulanzgrund <span id="confirmKulanzOptional" class="font-normal normal-case text-gray-400">(Pflicht)</span>
                    </label>
                    <input type="text" id="confirmKulanzInput" class="block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white text-gray-900 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>
            <div class="px-5 py-4 bg-gray-50 border-t border-gray-100 flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
                <button type="button" onclick="closeConfirmModal()"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition min-h-[44px]">
                    Abbrechen
                </button>
                <button type="button" id="confirmOkBtn"
                        class="inline-flex items-center justify-center rounded-lg border border-transparent bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 transition min-h-[44px]">
                    Ja, auschecken
                </button>
            </div>
        </div>
    </div>
</div>

<script>window.STAFF_SNAPSHOT_URL = '/hallendienst/snapshot';</script>
<?= \Kletterdom\Support\VendorAssets::scriptTag('html5-qrcode.min.js') ?>
<script src="/assets/js/staff.js"></script>
<?php
$body = ob_get_clean();
echo $view->render('layout', [
    'title' => 'Check-In | Kletterdom',
    'body'  => $body,
]);
