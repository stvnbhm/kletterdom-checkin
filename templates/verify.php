<?php
/** @var \Kletterdom\Auth\Auth $auth
 *  @var \Kletterdom\Http\Csrf $csrf
 *  @var array<string,mixed>   $registration
 *  @var string                $qrDataUri
 */
$colors = [
    'green'  => ['bg' => 'bg-green-50',  'border' => 'border-green-500',  'text' => 'text-green-800',  'icon' => '✅', 'label' => 'Zutritt OK'],
    'blue'   => ['bg' => 'bg-blue-50',   'border' => 'border-blue-500',   'text' => 'text-blue-800',   'icon' => '🔵', 'label' => 'Schnupperklettern'],
    'orange' => ['bg' => 'bg-orange-50', 'border' => 'border-orange-500', 'text' => 'text-orange-800', 'icon' => '⚠️', 'label' => 'Bitte beim Hallendienst melden'],
    'red'    => ['bg' => 'bg-red-50',    'border' => 'border-red-500',    'text' => 'text-red-800',    'icon' => '🚫', 'label' => 'Kein Zutritt'],
];
$c = $colors[$registration['access_status'] ?? 'red'] ?? $colors['red'];

$currentCheckin   = $registration['current_checkin'] ?? null;
$kulanzUntil      = $registration['manual_exception_until'] ?? null;
$hasActiveKulanz  = $kulanzUntil !== null && $kulanzUntil !== '' && strtotime((string) $kulanzUntil) > time();
$isTrialUsed      = ($registration['access_status'] ?? '') === 'blue'
                  && (int) ($registration['trial_visits_count'] ?? 0) >= 1
                  && ! $hasActiveKulanz;
$isBlocked        = ! in_array($registration['access_status'] ?? '', ['green', 'blue'], true) || $isTrialUsed;

$verifyUrl = '/verify/' . $registration['qr_token'];

ob_start();
?>
<main class="py-12">
    <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8">

        <div class="flex items-center justify-center mb-8">
            <h2 class="text-xl font-bold text-gray-800 text-center">Registrierungsbestätigung</h2>
        </div>

        <?php if ($currentCheckin === null): ?>
            <div class="bg-white rounded-xl border-2 <?= $c['border'] ?> <?= $c['bg'] ?> p-6 mb-6 text-center shadow-sm">
                <div class="text-5xl mb-3"><?= $c['icon'] ?></div>
                <div class="text-2xl font-bold <?= $c['text'] ?>"><?= htmlspecialchars($c['label'], ENT_QUOTES) ?></div>
                <?php if (! empty($registration['access_reason'])): ?>
                    <div class="mt-2 text-sm <?= $c['text'] ?> opacity-80 font-medium">
                        <?= htmlspecialchars((string) $registration['access_reason'], ENT_QUOTES) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">Deine Registrierung</h3>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between border-b border-gray-100 pb-2">
                    <dt class="text-gray-500">Name</dt>
                    <dd class="font-bold text-gray-900"><?= htmlspecialchars(trim($registration['first_name'] . ' ' . $registration['last_name']), ENT_QUOTES) ?></dd>
                </div>
                <div class="flex justify-between border-b border-gray-100 pb-2">
                    <dt class="text-gray-500">Typ</dt>
                    <dd class="font-bold text-gray-900"><?= $registration['member_type'] === 'member' ? 'Mitglied' : 'Schnuppergast' ?></dd>
                </div>
                <?php if (! empty($registration['member_number'])): ?>
                    <div class="flex justify-between border-b border-gray-100 pb-2">
                        <dt class="text-gray-500">Mitgliedsnummer</dt>
                        <dd class="font-bold text-gray-900"><?= htmlspecialchars((string) $registration['member_number'], ENT_QUOTES) ?></dd>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between pt-1">
                    <dt class="text-gray-500">Haftungsausschluss</dt>
                    <dd class="font-bold text-green-700">Akzeptiert</dd>
                </div>
            </dl>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 text-center">
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">Dein persönlicher QR-Code</h3>
            <div class="flex justify-center mb-5 bg-white p-4 rounded-lg border border-gray-100">
                <img src="<?= htmlspecialchars($qrDataUri, ENT_QUOTES) ?>" alt="QR-Code für Check-in" class="w-[200px] h-[200px]">
            </div>
            <p class="text-sm text-gray-600 leading-relaxed max-w-[280px] mx-auto">
                Checke mit diesem Code beim Hallendienst im Kletterdom ein.<br>
                <span class="text-xs text-gray-400 mt-1 block">Tipp: Speichere diese Seite als Lesezeichen oder mache einen Screenshot.</span>
            </p>
            <div class="mt-4 pt-4 border-t border-gray-100">
                <a href="<?= htmlspecialchars($verifyUrl, ENT_QUOTES) ?>" class="text-xs text-indigo-600 hover:text-indigo-800 hover:underline break-all font-medium">
                    <?= htmlspecialchars($verifyUrl, ENT_QUOTES) ?>
                </a>
            </div>
        </div>

        <?php if ($auth->check()): ?>
            <div class="mt-10 bg-gray-900 rounded-xl shadow-lg border border-gray-800 p-6 text-center">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-5">Personal-Aktion</h3>

                <?php if ($currentCheckin !== null): ?>
                    <div class="bg-green-900/30 border border-green-800 rounded-lg p-4">
                        <div class="text-green-400 font-bold text-lg">Bereits eingecheckt</div>
                        <div class="text-green-500/80 text-sm mt-1">
                            am <?= htmlspecialchars(date('d.m.Y \u\m H:i', strtotime((string) $currentCheckin['checked_in_at'])), ENT_QUOTES) ?> Uhr
                        </div>
                    </div>
                <?php elseif ($isBlocked): ?>
                    <div class="bg-amber-900/30 border border-amber-800 rounded-lg p-4 mb-2">
                        <div class="text-amber-400 font-bold text-lg">Check-in nicht möglich</div>
                        <div class="text-amber-500/80 text-sm mt-2">
                            <?php if (($registration['access_status'] ?? '') === 'red'): ?>
                                Kein Zutritt erlaubt. Bitte beim Hallendienst melden.
                            <?php elseif ($isTrialUsed): ?>
                                Erstbesuch bereits absolviert. Zweiter Besuch nur mit Kulanz durch den Hallendienst.
                            <?php else: ?>
                                Zutritt erfordert manuelle Freigabe. Bitte in der Staff-Übersicht prüfen.
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="POST" action="/verify/<?= htmlspecialchars($registration['qr_token'], ENT_QUOTES) ?>/checkin">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES) ?>">
                        <button type="submit"
                                class="w-full inline-flex items-center justify-center gap-2 bg-green-600 hover:bg-green-500 text-white px-6 py-4 rounded-lg font-bold text-lg shadow transition">
                            Check-in bestätigen
                        </button>
                    </form>
                <?php endif; ?>

                <div class="mt-6 pt-5 border-t border-gray-800">
                    <a href="/hallendienst" class="text-gray-400 hover:text-white text-sm font-medium transition">
                        Zurück zur Staff-Übersicht
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php
$body = ob_get_clean();
echo $view->render('layout', [
    'title'       => 'Registrierungsbestätigung | Kletterdom',
    'body'        => $body,
    'authNavOnly' => true,
]);
