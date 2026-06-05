<?php
/** @var array<int,array<string,mixed>> $registrations
 *  @var array<int,string>              $pastCheckinDates
 *  @var ?string                        $query
 *  @var array{page:int,per_page:int,total:int,pages:int} $pagination
 *  @var \Kletterdom\Http\Csrf          $csrf
 */
$token  = $csrf->token();
$qParam = $query !== null && $query !== '' ? '?q=' . urlencode($query) : '';

$accessStyle = static fn (string $s): string => [
    'green'  => 'bg-green-100 text-green-800',
    'blue'   => 'bg-blue-100 text-blue-800',
    'orange' => 'bg-amber-100 text-amber-800',
][$s] ?? 'bg-red-100 text-red-800';

$accessText = static fn (string $s): string => [
    'green'  => 'Zutritt OK',
    'blue'   => 'Schnuppergast',
    'orange' => 'Freigabe nötig',
][$s] ?? 'Gesperrt';

$rows = $registrations;
?>

<?php if ($query === null || $query === ''): ?>
    <p class="mb-3 text-sm text-gray-500">
        Standardmäßig erscheinen nur aktuell eingecheckte Personen. Weitere Personen findest du über die Suche.
    </p>
<?php endif; ?>

<div class="space-y-4 md:hidden">
    <?php $shownDividerMobile = false; ?>
    <?php foreach ($rows as $reg): ?>
        <?php
        $currentCheckin = ! empty($reg['current_checkin_at']);
        $visits         = (int) ($reg['trial_visits_count'] ?? 0);
        $past           = $pastCheckinDates[(int) $reg['id']] ?? '';

        $isTrialMaxReached         = $reg['member_type'] === 'guest' && $visits >= 3;
        $isUnverifiedMemberBlocked = $reg['member_type'] === 'member' && empty($reg['member_match']) && $reg['access_status'] === 'red';
        $isHardBlocked             = $reg['access_status'] === 'red' || $isTrialMaxReached || $isUnverifiedMemberBlocked;

        $isTrialNeedsModal = $reg['member_type'] === 'guest' && $visits >= 1 && $visits < 3;
        $requiresModal     = ! $isHardBlocked && ($reg['access_status'] === 'orange' || $isTrialNeedsModal);

        $isUnverifiedMember = $reg['member_type'] === 'member' && empty($reg['member_match']);
        $unverifiedCheckins = $isUnverifiedMember ? (int) ($reg['checkins_count'] ?? 0) : 0;
        $nextCheckinTriggersRed = ($reg['member_type'] === 'guest' && $visits === 2)
                                || ($isUnverifiedMember && $unverifiedCheckins === 2);
        ?>

        <?php if (! $shownDividerMobile && ! $currentCheckin): ?>
            <?php $shownDividerMobile = true; ?>
            <div class="px-2 pt-2 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">Noch nicht eingecheckt</div>
        <?php endif; ?>

        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm space-y-3 <?= $currentCheckin ? 'border-l-2 border-l-indigo-300' : '' ?>">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-sm font-semibold text-gray-900">
                        <?= htmlspecialchars(trim($reg['first_name'] . ' ' . $reg['last_name']), ENT_QUOTES) ?>
                    </div>
                    <div class="text-xs text-gray-400 mt-0.5">
                        Reg. <?= $reg['created_at'] ? htmlspecialchars(date('d.m.Y', strtotime((string) $reg['created_at'])), ENT_QUOTES) : '—' ?>
                        · <?= $reg['member_type'] === 'guest' ? 'Gast' : 'Mitglied' ?>
                        <?php if (! empty($reg['member_number'])): ?>
                            · <?= htmlspecialchars((string) $reg['member_number'], ENT_QUOTES) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex flex-col items-end gap-1 shrink-0">
                    <?php if ($currentCheckin): ?>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold text-green-800">✅ Eingecheckt</span>
                    <?php else: ?>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold <?= $accessStyle($reg['access_status']) ?>">
                            <?= htmlspecialchars($accessText($reg['access_status']), ENT_QUOTES) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="text-sm text-gray-600">
                <?php if (! empty($reg['needs_parent_consent']) && empty($reg['parent_consent_received'])): ?>
                    <div class="text-xs text-gray-600 space-y-1">
                        <div>
                            Klettert alleine? – dann Formular nötig
                            (<a href="https://www.oetk-langenlois.at/fileadmin/Einverstaendniserklaerung-14-18.pdf" target="_blank" rel="noopener" class="underline text-gray-500">PDF</a>)
                            <form method="POST" action="/hallendienst/<?= (int) $reg['id'] ?>/parent-consent">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                                <?php if ($query !== null && $query !== ''): ?>
                                    <input type="hidden" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES) ?>">
                                <?php endif; ?>
                                <button type="submit" class="underline text-gray-600 bg-transparent border-none p-0 cursor-pointer text-xs">
                                    Formular ausgefüllt erhalten?
                                </button>
                            </form>
                        </div>
                    </div>
                <?php elseif (! $currentCheckin && ! empty($reg['access_reason'])): ?>
                    <div class="text-xs text-gray-500"><?= htmlspecialchars((string) $reg['access_reason'], ENT_QUOTES) ?></div>
                <?php endif; ?>
            </div>

            <div class="border-t border-gray-100 pt-3">
                <?php if ($currentCheckin): ?>
                    <div class="space-y-2">
                        <span class="block text-sm text-gray-600">
                            Eingecheckt <?= htmlspecialchars(date('H:i', strtotime((string) $reg['current_checkin_at'])), ENT_QUOTES) ?> Uhr
                        </span>
                        <form method="POST" action="/hallendienst/<?= (int) $reg['id'] ?>/checkout"
                              onsubmit="askConfirm(<?= json_encode($reg['first_name'] . ' ' . $reg['last_name'] . ' wirklich auschecken?', JSON_HEX_TAG | JSON_HEX_QUOT) ?>, this); return false;">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                            <?php if ($query !== null && $query !== ''): ?>
                                <input type="hidden" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES) ?>">
                            <?php endif; ?>
                            <button type="submit" class="w-full inline-flex items-center justify-center rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-100 transition min-h-[44px]">
                                Check-out
                            </button>
                        </form>
                    </div>
                <?php elseif ($isHardBlocked): ?>
                    <button disabled class="w-full inline-flex items-center justify-center border border-gray-200 bg-gray-100 text-gray-400 rounded-lg px-3 py-2 text-sm font-semibold cursor-not-allowed min-h-[44px]">
                        Check-in
                    </button>
                <?php elseif ($requiresModal): ?>
                    <form id="checkin-form-<?= (int) $reg['id'] ?>" method="POST" action="/hallendienst/<?= (int) $reg['id'] ?>/check-in" class="hidden">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                        <?php if ($query !== null && $query !== ''): ?>
                            <input type="hidden" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES) ?>">
                        <?php endif; ?>
                        <input type="text" name="reason" id="reason-<?= (int) $reg['id'] ?>">
                    </form>
                    <button type="button"
                            onclick="openCheckinModal(
                                document.getElementById('checkin-form-<?= (int) $reg['id'] ?>'),
                                document.getElementById('reason-<?= (int) $reg['id'] ?>'),
                                <?= json_encode($reg['first_name'] . ' ' . $reg['last_name'], JSON_HEX_TAG | JSON_HEX_QUOT) ?>,
                                <?= json_encode((string) ($reg['access_reason'] ?? ''),     JSON_HEX_TAG | JSON_HEX_QUOT) ?>,
                                <?= json_encode((string) $reg['access_status'],             JSON_HEX_TAG | JSON_HEX_QUOT) ?>,
                                <?= json_encode($nextCheckinTriggersRed,                    JSON_HEX_TAG | JSON_HEX_QUOT) ?>,
                                <?= (int) $visits ?>,
                                <?= json_encode($past,                                       JSON_HEX_TAG | JSON_HEX_QUOT) ?>,
                                <?= json_encode((string) ($reg['manual_exception_reason'] ?? ''), JSON_HEX_TAG | JSON_HEX_QUOT) ?>
                            )"
                            class="w-full inline-flex items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 transition min-h-[44px]">
                        Check-in
                    </button>
                <?php else: ?>
                    <form method="POST" action="/hallendienst/<?= (int) $reg['id'] ?>/check-in">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                        <?php if ($query !== null && $query !== ''): ?>
                            <input type="hidden" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES) ?>">
                        <?php endif; ?>
                        <button type="submit" class="w-full inline-flex items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 transition min-h-[44px]">
                            Check-in
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($rows === []): ?>
        <div class="bg-white border border-gray-200 rounded-xl p-6 text-center text-sm text-gray-500 shadow-sm">
            <?= ($query === null || $query === '') ? 'Aktuell ist niemand eingecheckt.' : 'Keine Registrierungen gefunden.' ?>
        </div>
    <?php endif; ?>
</div>

<div class="hidden md:block bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto w-full">
        <table class="w-full text-left">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Mitgliedsnr.</th>
                    <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Zutritt</th>
                    <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Zusatzinfos</th>
                    <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Check-in / Aktion</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php $shownDivider = false; ?>
                <?php foreach ($rows as $reg): ?>
                    <?php
                    $currentCheckin = ! empty($reg['current_checkin_at']);
                    $visits         = (int) ($reg['trial_visits_count'] ?? 0);
                    $past           = $pastCheckinDates[(int) $reg['id']] ?? '';

                    $isTrialMaxReached         = $reg['member_type'] === 'guest' && $visits >= 3;
                    $isUnverifiedMemberBlocked = $reg['member_type'] === 'member' && empty($reg['member_match']) && $reg['access_status'] === 'red';
                    $isHardBlocked             = $reg['access_status'] === 'red' || $isTrialMaxReached || $isUnverifiedMemberBlocked;

                    $isTrialNeedsModal = $reg['member_type'] === 'guest' && $visits >= 1 && $visits < 3;
                    $requiresModal     = ! $isHardBlocked && ($reg['access_status'] === 'orange' || $isTrialNeedsModal);

                    $isUnverifiedMember = $reg['member_type'] === 'member' && empty($reg['member_match']);
                    $unverifiedCheckins = $isUnverifiedMember ? (int) ($reg['checkins_count'] ?? 0) : 0;
                    $nextCheckinTriggersRed = ($reg['member_type'] === 'guest' && $visits === 2)
                                            || ($isUnverifiedMember && $unverifiedCheckins === 2);
                    ?>

                    <?php if (! $shownDivider && ! $currentCheckin): ?>
                        <?php $shownDivider = true; ?>
                        <tr>
                            <td colspan="5" class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider bg-gray-50 border-t border-b border-gray-100">
                                Noch nicht eingecheckt
                            </td>
                        </tr>
                    <?php endif; ?>

                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-4 align-top">
                            <div class="text-sm font-semibold text-gray-900">
                                <?= htmlspecialchars(trim($reg['first_name'] . ' ' . $reg['last_name']), ENT_QUOTES) ?>
                            </div>
                            <div class="text-xs text-gray-400 mt-0.5">
                                Registriert am <?= $reg['created_at'] ? htmlspecialchars(date('d.m.Y', strtotime((string) $reg['created_at'])), ENT_QUOTES) : '—' ?>
                                · <?= $reg['member_type'] === 'guest' ? 'Gast' : 'Mitglied' ?>
                            </div>
                        </td>
                        <td class="px-4 py-4 align-top text-sm text-gray-600"><?= htmlspecialchars((string) ($reg['member_number'] ?? '—'), ENT_QUOTES) ?></td>
                        <td class="px-4 py-4 align-top">
                            <?php if ($currentCheckin): ?>
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold text-green-800">✅ Eingecheckt</span>
                            <?php else: ?>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold <?= $accessStyle($reg['access_status']) ?>">
                                    <?= htmlspecialchars($accessText($reg['access_status']), ENT_QUOTES) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-4 align-top text-sm text-gray-600">
                            <?php if (! empty($reg['needs_parent_consent']) && empty($reg['parent_consent_received'])): ?>
                                <div class="text-xs text-gray-600 space-y-1">
                                    <div>
                                        Klettert alleine? – dann Formular nötig
                                        (<a href="https://www.oetk-langenlois.at/fileadmin/Einverstaendniserklaerung-14-18.pdf" target="_blank" rel="noopener" class="underline text-gray-500">PDF</a>)
                                        <form method="POST" action="/hallendienst/<?= (int) $reg['id'] ?>/parent-consent" class="inline">
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                                            <?php if ($query !== null && $query !== ''): ?>
                                                <input type="hidden" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES) ?>">
                                            <?php endif; ?>
                                            <button type="submit" class="underline text-gray-600 bg-transparent border-none p-0 cursor-pointer text-xs">Formular erhalten</button>
                                        </form>
                                    </div>
                                </div>
                            <?php elseif (! $currentCheckin && ! empty($reg['access_reason'])): ?>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars((string) $reg['access_reason'], ENT_QUOTES) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-4 align-top">
                            <?php if ($currentCheckin): ?>
                                <div class="space-y-2">
                                    <span class="block text-sm text-gray-600">
                                        Eingecheckt <?= htmlspecialchars(date('H:i', strtotime((string) $reg['current_checkin_at'])), ENT_QUOTES) ?> Uhr
                                    </span>
                                    <form method="POST" action="/hallendienst/<?= (int) $reg['id'] ?>/checkout"
                                          onsubmit="askConfirm(<?= json_encode($reg['first_name'] . ' ' . $reg['last_name'] . ' wirklich auschecken?', JSON_HEX_TAG | JSON_HEX_QUOT) ?>, this); return false;">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                                        <?php if ($query !== null && $query !== ''): ?>
                                            <input type="hidden" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES) ?>">
                                        <?php endif; ?>
                                        <button type="submit" class="w-full inline-flex items-center justify-center rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-100 transition min-h-[44px]">
                                            Check-out
                                        </button>
                                    </form>
                                </div>
                            <?php elseif ($isHardBlocked): ?>
                                <button disabled class="w-full inline-flex items-center justify-center border border-gray-200 bg-gray-100 text-gray-400 rounded-lg px-3 py-2 text-sm font-semibold cursor-not-allowed min-h-[44px]">
                                    Check-in
                                </button>
                            <?php elseif ($requiresModal): ?>
                                <form id="dt-checkin-form-<?= (int) $reg['id'] ?>" method="POST" action="/hallendienst/<?= (int) $reg['id'] ?>/check-in" class="hidden">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                                    <?php if ($query !== null && $query !== ''): ?>
                                        <input type="hidden" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES) ?>">
                                    <?php endif; ?>
                                    <input type="text" name="reason" id="dt-reason-<?= (int) $reg['id'] ?>">
                                </form>
                                <button type="button"
                                        onclick="openCheckinModal(
                                            document.getElementById('dt-checkin-form-<?= (int) $reg['id'] ?>'),
                                            document.getElementById('dt-reason-<?= (int) $reg['id'] ?>'),
                                            <?= json_encode($reg['first_name'] . ' ' . $reg['last_name'], JSON_HEX_TAG | JSON_HEX_QUOT) ?>,
                                            <?= json_encode((string) ($reg['access_reason'] ?? ''),     JSON_HEX_TAG | JSON_HEX_QUOT) ?>,
                                            <?= json_encode((string) $reg['access_status'],             JSON_HEX_TAG | JSON_HEX_QUOT) ?>,
                                            <?= json_encode($nextCheckinTriggersRed,                    JSON_HEX_TAG | JSON_HEX_QUOT) ?>,
                                            <?= (int) $visits ?>,
                                            <?= json_encode($past,                                       JSON_HEX_TAG | JSON_HEX_QUOT) ?>,
                                            <?= json_encode((string) ($reg['manual_exception_reason'] ?? ''), JSON_HEX_TAG | JSON_HEX_QUOT) ?>
                                        )"
                                        class="w-full inline-flex items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 transition min-h-[44px]">
                                    Check-in
                                </button>
                            <?php else: ?>
                                <form method="POST" action="/hallendienst/<?= (int) $reg['id'] ?>/check-in">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                                    <?php if ($query !== null && $query !== ''): ?>
                                        <input type="hidden" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES) ?>">
                                    <?php endif; ?>
                                    <button type="submit" class="w-full inline-flex items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 transition min-h-[44px]">
                                        Check-in
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 border-t border-gray-100">
                            <?= ($query === null || $query === '') ? 'Aktuell ist niemand eingecheckt.' : 'Keine Registrierungen gefunden.' ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (($pagination['pages'] ?? 1) > 1): ?>
    <div class="mt-6 flex flex-wrap items-center gap-2 text-sm">
        <?php for ($p = 1; $p <= $pagination['pages']; $p++): ?>
            <?php $url = '/hallendienst?page=' . $p . ($query ? '&q=' . urlencode($query) : ''); ?>
            <a href="<?= htmlspecialchars($url, ENT_QUOTES) ?>"
               class="px-3 py-1 rounded border <?= $p === $pagination['page'] ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' ?>">
                <?= $p ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>
