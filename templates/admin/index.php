<?php
/** @var \Kletterdom\Http\Csrf  $csrf
 *  @var \Kletterdom\Http\Flash $flash
 *  @var array                  $registrations
 *  @var array                  $stats
 *  @var array                  $chart
 *  @var array                  $pagination
 *  @var ?string                $query
 *  @var ?string                $statusFilter
 */
$token            = $csrf->token();
$confirmRequired  = $flash->peek('confirm_missing_count_required');

$statusLabels = [
    'green'  => 'Zutritt OK',
    'blue'   => 'Schnuppergast',
    'orange' => 'Freigabe nötig',
    'red'    => 'Gesperrt',
];
$statusColors = [
    'green'  => 'bg-green-100 text-green-700',
    'blue'   => 'bg-blue-100 text-blue-700',
    'orange' => 'bg-orange-100 text-orange-700',
    'red'    => 'bg-red-100 text-red-700',
];

ob_start();
?>
<main class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
                <div class="text-3xl font-bold text-teal-600"><?= (int) ($stats['checked_in_today'] ?? 0) ?></div>
                <div class="text-sm text-gray-500 mt-1">Heute eingecheckt</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
                <div class="text-3xl font-bold text-gray-700"><?= (int) ($stats['total_registrations'] ?? 0) ?></div>
                <div class="text-sm text-gray-500 mt-1">Registrierungen gesamt</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
                <div class="text-3xl font-bold text-purple-500"><?= (int) ($stats['members'] ?? 0) ?></div>
                <div class="text-sm text-gray-500 mt-1">Aktive Mitglieder</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center">
                <div class="text-3xl font-bold text-red-400"><?= (int) ($stats['inactive_members'] ?? 0) ?></div>
                <div class="text-sm text-gray-500 mt-1">Inaktive Mitglieder</div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-semibold text-gray-700 mb-4">📊 Hallenauslastung – letzte 30 Tage</h3>
            <div class="relative" style="height: 220px;">
                <canvas id="auslastungChart"></canvas>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-6">

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">📥 Mitglieder importieren</h3>
                    <p class="mt-1 text-xs text-gray-400 leading-relaxed">
                        CSV-Spalten:
                        <code class="font-mono bg-gray-100 px-1 py-0.5 rounded text-gray-600">
                            Mitgliedsnummer; Nachname; Status; Betrag offen; Geburtsdatum
                        </code>
                        <span class="block mt-1">
                            Gespeichert werden nur Mitgliedsnummer, Status, Beitragsstatus und ein Hash aus Nachname&nbsp;+&nbsp;Geburtsdatum.
                        </span>
                    </p>
                </div>

                <form id="importForm" action="/admin/import-members" method="POST" enctype="multipart/form-data" class="flex flex-col gap-4">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">

                    <div class="border border-gray-200 rounded-lg p-3 bg-gray-50">
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">CSV-Datei wählen</label>
                        <input type="file" name="members_csv" accept=".csv,.txt"
                               class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100">
                    </div>

                    <?php if ($confirmRequired !== null): ?>
                        <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                            ⚠️ <strong><?= (int) $confirmRequired ?> Mitglieder</strong> fehlen in der CSV und würden auf „inaktiv" gesetzt.
                            Bitte die Anzahl unten bestätigen und erneut importieren.
                        </div>
                    <?php endif; ?>

                    <div class="flex flex-col gap-1">
                        <label for="confirm_missing_count" class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Fehlende Mitglieder bestätigen</label>
                        <input type="number" name="confirm_missing_count" id="confirm_missing_count" placeholder="z. B. 12"
                               class="w-36 border border-gray-300 rounded-md px-3 py-1.5 text-sm shadow-sm focus:ring-teal-400 focus:border-teal-400">
                    </div>

                    <button id="importBtn" type="submit"
                            class="w-full bg-teal-600 hover:bg-teal-700 disabled:bg-teal-300 disabled:cursor-not-allowed text-white text-sm font-semibold py-2 px-4 rounded-lg transition flex items-center justify-center gap-2 min-h-[44px]">
                        <span id="importBtnText">Importieren</span>
                    </button>
                </form>

                <div class="border-t border-gray-100 pt-4 mt-auto">
                    <h4 class="text-sm font-semibold text-gray-600 mb-1">📤 Check-ins exportieren</h4>
                    <p class="text-xs text-gray-400 mb-3">Exportiert alle Check-ins im gewählten Zeitraum als CSV (UTF-8 BOM, Semikolon).</p>
                    <form action="/admin/export-checkins" method="GET" class="flex flex-col gap-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Von</label>
                                <input type="date" name="from" value="<?= htmlspecialchars(date('Y-m-d', strtotime('-30 days')), ENT_QUOTES) ?>"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Bis</label>
                                <input type="date" name="to" value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES) ?>"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2 px-4 rounded-lg transition min-h-[44px]">
                            CSV herunterladen
                        </button>
                    </form>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">📤 Alle Registrierungen exportieren</h3>
                    <p class="mt-1 text-xs text-gray-400 leading-relaxed">Exportiert alle aktuellen Registrierungen als CSV (UTF-8 BOM, Semikolon).</p>
                </div>
                <form action="/admin/export-registrations" method="GET">
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2 px-4 rounded-lg transition min-h-[44px]">
                        <?= (int) ($stats['total_registrations'] ?? 0) ?> Registrierungen als CSV herunterladen
                    </button>
                </form>

                <div class="border-t border-gray-100 pt-4 mt-4">
                    <h4 class="text-sm font-semibold text-gray-600 mb-1">🗑️ Veraltete Registrierungen entfernen</h4>
                    <p class="text-xs text-gray-400 mb-3">Löscht alle Registrierungen, deren letzter Check-in älter als 2 Jahre ist.</p>
                    <form action="/admin/stale-registrations" method="POST"
                          data-confirm="Alle <?= (int) ($stats['stale_registrations'] ?? 0) ?> veralteten Registrierungen wirklich löschen?"
                          onsubmit="adminConfirm(event, this)">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="w-full bg-red-50 hover:bg-red-100 border border-red-200 text-red-700 text-sm font-semibold py-2 px-4 rounded-lg transition min-h-[44px]">
                            <?= (int) ($stats['stale_registrations'] ?? 0) ?> veraltete Registrierung(en) löschen
                        </button>
                    </form>
                </div>

                <div class="border-t border-gray-100 pt-4">
                    <h4 class="text-sm font-semibold text-gray-600 mb-1">🗑️ Inaktive Mitglieder entfernen</h4>
                    <p class="text-xs text-gray-400 mb-3">Löscht alle Registrierungen inaktiver Mitglieder inkl. Check-ins.</p>
                    <form action="/admin/inactive-members" method="POST"
                          data-confirm="Alle <?= (int) ($stats['inactive_members'] ?? 0) ?> inaktiven Mitglieder wirklich dauerhaft löschen?"
                          onsubmit="adminConfirm(event, this)">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="w-full bg-red-50 hover:bg-red-100 border border-red-200 text-red-700 text-sm font-semibold py-2 px-4 rounded-lg transition min-h-[44px]">
                            <?= (int) ($stats['inactive_members'] ?? 0) ?> inaktive Mitglieder löschen
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold text-gray-700">Alle Registrierungen</h3>
                    <span class="text-sm text-gray-400"><?= (int) ($pagination['total'] ?? 0) ?> gesamt</span>
                </div>
                <form method="GET" action="/admin" class="flex flex-wrap gap-2 items-center">
                    <input type="text" name="q" value="<?= htmlspecialchars((string) ($query ?? ''), ENT_QUOTES) ?>"
                           placeholder="Namen (mit Komma), Mitgliedsnr. oder Notiz …"
                           class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-52">
                    <select name="status" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
                        <option value="">Alle Status</option>
                        <option value="guest"  <?= ($statusFilter ?? '') === 'guest'  ? 'selected' : '' ?>>Schnuppergäste (alle)</option>
                        <option value="green"  <?= ($statusFilter ?? '') === 'green'  ? 'selected' : '' ?>>Zutritt OK</option>
                        <option value="orange" <?= ($statusFilter ?? '') === 'orange' ? 'selected' : '' ?>>Freigabe nötig</option>
                        <option value="red"    <?= ($statusFilter ?? '') === 'red'    ? 'selected' : '' ?>>Gesperrt</option>
                    </select>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-3 py-1.5 rounded-lg transition">Suchen</button>
                    <?php if ($query || $statusFilter): ?>
                        <a href="/admin" class="text-sm text-gray-500 hover:text-gray-700 px-2 py-1.5 rounded-lg hover:bg-gray-100">✕ Zurücksetzen</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="hidden md:block overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3 text-left">Name</th>
                            <th class="px-4 py-3 text-left">Typ</th>
                            <th class="px-4 py-3 text-left">Mitgliedsnr.</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">QR-Link</th>
                            <th class="px-4 py-3 text-left">Check-ins</th>
                            <th class="px-4 py-3 text-left">Registriert am</th>
                            <th class="px-4 py-3 text-left min-w-[200px]">Notiz / Kurs</th>
                            <th class="px-4 py-3 text-left">Aktion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($registrations as $reg): ?>
                            <?php
                            $regName  = trim($reg['first_name'] . ' ' . $reg['last_name']);
                            $statusCls = $statusColors[$reg['access_status']] ?? 'bg-gray-100 text-gray-600';
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    <?= htmlspecialchars($regName, ENT_QUOTES) ?>
                                    <?php if (! empty($reg['birth_date'])): ?>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars(date('d.m.Y', strtotime((string) $reg['birth_date'])), ENT_QUOTES) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-gray-500"><?= $reg['member_type'] === 'member' ? 'Mitglied' : 'Gast' ?></td>
                                <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars((string) ($reg['member_number'] ?? '–'), ENT_QUOTES) ?></td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $statusCls ?>">
                                        <?= htmlspecialchars($statusLabels[$reg['access_status']] ?? (string) $reg['access_status'], ENT_QUOTES) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if (! empty($reg['qr_token'])): ?>
                                        <a href="/verify/<?= htmlspecialchars($reg['qr_token'], ENT_QUOTES) ?>" target="_blank"
                                           class="font-mono text-xs text-indigo-600 hover:text-indigo-800 hover:underline break-all">
                                            <?= htmlspecialchars($reg['qr_token'], ENT_QUOTES) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-300 text-xs">–</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-gray-600 tabular-nums"><?= (int) ($reg['checkins_count'] ?? 0) ?></td>
                                <td class="px-4 py-3 text-gray-400 text-xs">
                                    <?= $reg['created_at'] ? htmlspecialchars(date('d.m.Y H:i', strtotime((string) $reg['created_at'])), ENT_QUOTES) : '—' ?>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <form action="/admin/registrations/<?= (int) $reg['id'] ?>/notes" method="POST" class="flex flex-col gap-1.5">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                                        <input type="hidden" name="_method" value="PATCH">
                                        <?php if ($query): ?><input type="hidden" name="q" value="<?= htmlspecialchars((string) $query, ENT_QUOTES) ?>"><?php endif; ?>
                                        <?php if ($statusFilter): ?><input type="hidden" name="status" value="<?= htmlspecialchars((string) $statusFilter, ENT_QUOTES) ?>"><?php endif; ?>
                                        <?php if (($pagination['page'] ?? 1) > 1): ?><input type="hidden" name="page" value="<?= (int) $pagination['page'] ?>"><?php endif; ?>
                                        <textarea name="notes" rows="2" maxlength="65535" placeholder="z. B. Kurs Mo 18:00"
                                                  class="w-full min-w-[160px] max-w-xs border border-gray-200 rounded-md px-2 py-1 text-xs text-gray-800 resize-y"><?= htmlspecialchars((string) ($reg['notes'] ?? ''), ENT_QUOTES) ?></textarea>
                                        <button type="submit" class="self-start text-xs font-semibold text-indigo-600 hover:text-indigo-800 min-h-[36px] px-1">Speichern</button>
                                    </form>
                                </td>
                                <td class="px-4 py-3">
                                    <form action="/admin/registrations/<?= (int) $reg['id'] ?>" method="POST"
                                          data-confirm="Registrierung von <?= htmlspecialchars($regName, ENT_QUOTES) ?> wirklich löschen?"
                                          onsubmit="adminConfirm(event, this)">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button type="submit" class="text-xs text-red-500 hover:text-red-700 hover:underline transition min-h-[44px] px-1">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if ($registrations === []): ?>
                            <tr><td colspan="9" class="px-4 py-10 text-center text-gray-400">Noch keine Registrierungen vorhanden.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (($pagination['pages'] ?? 1) > 1): ?>
                <div class="px-6 py-4 border-t border-gray-100 flex flex-wrap items-center gap-2 text-sm">
                    <?php
                    $base = '/admin';
                    $extra = [];
                    if ($query) { $extra[] = 'q=' . urlencode($query); }
                    if ($statusFilter) { $extra[] = 'status=' . urlencode($statusFilter); }
                    $extraQ = $extra === [] ? '' : ('&' . implode('&', $extra));
                    ?>
                    <?php for ($p = 1; $p <= (int) $pagination['pages']; $p++): ?>
                        <a href="<?= $base ?>?page=<?= $p ?><?= $extraQ ?>"
                           class="px-3 py-1 rounded border <?= $p === (int) $pagination['page'] ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' ?>">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<div id="confirmModal" class="fixed inset-0 z-[100] hidden">
    <div class="absolute inset-0 bg-gray-900/50" onclick="closeAdminConfirm()"></div>
    <div class="relative min-h-full flex items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-900">Bitte bestätigen</h3>
            </div>
            <div class="px-5 py-4">
                <p id="confirmMessage" class="text-sm text-gray-600 leading-relaxed"></p>
            </div>
            <div class="px-5 py-4 bg-gray-50 border-t border-gray-100 flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
                <button type="button" onclick="closeAdminConfirm()"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition min-h-[44px]">
                    Abbrechen
                </button>
                <button type="button" id="confirmOkBtn"
                        class="inline-flex items-center justify-center rounded-lg border border-transparent bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 transition min-h-[44px]">
                    Ja, löschen
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.ADMIN_CHART_LABELS = <?= json_encode($chart['labels'] ?? [], JSON_HEX_TAG | JSON_HEX_QUOT) ?>;
window.ADMIN_CHART_VALUES = <?= json_encode($chart['values'] ?? [], JSON_HEX_TAG | JSON_HEX_QUOT) ?>;
</script>
<?= \Kletterdom\Support\VendorAssets::scriptTag('chart.umd.min.js') ?>
<script src="/assets/js/admin.js"></script>
<?php
$body = ob_get_clean();
echo $view->render('layout', [
    'title' => 'Admin | Kletterdom',
    'body'  => $body,
]);
