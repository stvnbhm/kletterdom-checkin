<?php
/** @var \Kletterdom\Http\Csrf  $csrf
 *  @var \Kletterdom\Http\Flash $flash
 */
$old = static fn (string $key, string $default = ''): string => htmlspecialchars((string) $flash->old($key, $default), ENT_QUOTES);
$today = date('Y-m-d');
ob_start();
?>
<style>
    details > summary { list-style: none; }
    details > summary::-webkit-details-marker { display: none; }
</style>

<main class="min-h-screen py-12 flex flex-col justify-center sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-3xl text-center mb-6">
        <h2 class="text-3xl font-extrabold text-gray-900">Registrierung Kletterdom</h2>
        <p class="mt-2 text-sm text-gray-600">Bitte fülle das Formular aus, um dich für den Hallenbesuch zu registrieren.</p>
    </div>

    <div class="sm:mx-auto sm:w-full sm:max-w-3xl">
        <div class="bg-white py-8 px-4 shadow-xl sm:rounded-xl sm:px-10 border border-gray-100">

            <form method="POST" action="/halle-register" class="space-y-6" novalidate>
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES) ?>">
                <input type="hidden" name="hp_time" value="<?= time() ?>">

                <div class="sr-only" aria-hidden="true">
                    <label for="website">Website</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" value="<?= $old('website') ?>">
                    <label for="fax_number">Faxnummer</label>
                    <input type="text" id="fax_number" name="fax_number" tabindex="-1" autocomplete="off" value="<?= $old('fax_number') ?>">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700">Vorname</label>
                        <input type="text" id="first_name" name="first_name" required
                               value="<?= $old('first_name') ?>"
                               class="p-2 mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700">Nachname</label>
                        <input type="text" id="last_name" name="last_name" required
                               value="<?= $old('last_name') ?>"
                               class="p-2 mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="birth_date" class="block text-sm font-medium text-gray-700">Geburtsdatum</label>
                        <input type="date" id="birth_date" name="birth_date" required
                               min="1900-01-01" max="<?= $today ?>"
                               value="<?= $old('birth_date') ?>"
                               class="p-2 mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">

                        <div id="minor-note" class="hidden mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                            <p class="font-semibold mb-1">Hinweis für Minderjährige</p>
                            <p>Für Kinder von 14 bis 18 Jahren muss beim Hallenbesuch ohne Aufsichtsperson eine unterschriebene Einverständniserklärung der Eltern mitgebracht werden.</p>
                            <a href="https://www.oetk-langenlois.at/fileadmin/Einverstaendniserklaerung-14-18.pdf" target="_blank" rel="noopener" class="mt-2 inline-flex font-medium text-amber-900 underline hover:text-amber-700">Einverständniserklärung herunterladen</a>
                        </div>

                        <div id="supervision-wrapper" class="hidden mt-3">
                            <label class="inline-flex items-start bg-amber-50 border border-amber-200 rounded-lg p-3 cursor-pointer">
                                <input type="checkbox" id="supervision_confirmed" name="supervision_confirmed" value="1"
                                       class="mt-0.5 rounded border-gray-300 text-amber-600 shadow-sm focus:ring-amber-500"
                                       <?= $flash->old('supervision_confirmed') ? 'checked' : '' ?>>
                                <span class="ml-2 text-sm text-amber-900 leading-snug">
                                    Ich bestätige, dass Kinder unter 14 Jahren nur unter Aufsicht klettern dürfen und beim Besuch eine erziehungsberechtigte oder aufsichtsführende Person dabei ist.
                                </span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">E-Mail</label>
                        <input type="email" id="email" name="email" required
                               value="<?= $old('email') ?>"
                               class="p-2 mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                </div>

                <fieldset>
                    <legend class="block text-sm font-medium text-gray-700 mb-2">Typ</legend>
                    <div class="flex gap-6">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="radio" name="member_type" value="member" class="text-indigo-600 border-gray-300 focus:ring-indigo-500"
                                   <?= ($flash->old('member_type', 'member') === 'member') ? 'checked' : '' ?>>
                            <span class="ml-2 text-gray-700 text-sm">Mitglied</span>
                        </label>
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="radio" name="member_type" value="guest" class="text-indigo-600 border-gray-300 focus:ring-indigo-500"
                                   <?= ($flash->old('member_type') === 'guest') ? 'checked' : '' ?>>
                            <span class="ml-2 text-gray-700 text-sm">Gast / Schnuppern</span>
                        </label>
                    </div>
                </fieldset>

                <div id="member-number-wrapper">
                    <label for="member_number" class="block text-sm font-medium text-gray-700">Mitgliedsnummer</label>
                    <input type="text" id="member_number" name="member_number" placeholder="z. B. 23-34567"
                           pattern="\d{2}-\d{5}" maxlength="8" inputmode="numeric" autocomplete="off"
                           value="<?= $old('member_number') ?>"
                           class="p-2 mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <p class="mt-1 text-xs text-gray-500">Bitte die Vereins-Mitgliedsnummer eingeben.</p>
                </div>

                <div id="guest-address-wrapper" class="<?= $flash->old('member_type', 'member') === 'guest' ? '' : 'hidden' ?>">
                    <label for="address" class="block text-sm font-medium text-gray-700">Adresse</label>
                    <textarea id="address" name="address" rows="3"
                              class="p-2 mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"><?= $old('address') ?></textarea>
                    <p class="mt-1 text-xs text-gray-500">Nur für Gäste erforderlich.</p>
                </div>

                <div class="pt-6 border-t border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Wichtige Informationen</h3>

                    <details class="group border border-gray-200 rounded-lg bg-gray-50 mb-3 overflow-hidden">
                        <summary class="flex justify-between items-center font-semibold cursor-pointer p-4 text-gray-800 hover:bg-gray-100 transition-colors">
                            <span>🛡️ Haftungsausschluss &amp; Sicherheit</span>
                            <span class="transition group-open:rotate-180">▾</span>
                        </summary>
                        <div class="p-4 border-t border-gray-200 text-sm text-gray-700 bg-white space-y-2">
                            <p>Klettern und Bouldern sind mit Sturz- und Verletzungsrisiken verbunden. Ich nutze die Anlage eigenverantwortlich.</p>
                            <ul class="list-disc pl-5 space-y-1 mt-2">
                                <li><strong>Bouldern:</strong> Ich bouldere nur über Matten, halte Sturzräume frei und übersteige keine Maximalhöhen.</li>
                                <li><strong>Toprope:</strong> Nur wenn ich Gurt, Anseilen und Sicherungsgerät sicher beherrsche.</li>
                                <li><strong>Vorstieg:</strong> Nur wenn ich Gurt, Einbinden, Clippen und das Halten von Stürzen sicher beherrsche.</li>
                            </ul>
                            <p class="text-amber-700 font-medium mt-2">Falls ich eine dieser Voraussetzungen nicht erfülle, klettere ich nur unter Aufsicht einer geschulten Person.</p>
                        </div>
                    </details>

                    <details class="group border border-gray-200 rounded-lg bg-gray-50 mb-6 overflow-hidden">
                        <summary class="flex justify-between items-center font-semibold cursor-pointer p-4 text-gray-800 hover:bg-gray-100 transition-colors">
                            <span>📋 Hallenordnung ÖTK-Langenlois</span>
                            <span class="transition group-open:rotate-180">▾</span>
                        </summary>
                        <div class="p-4 border-t border-gray-200 text-sm text-gray-700 bg-white grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <h4 class="font-semibold text-indigo-700 mb-1">🧹 Verhalten &amp; Sauberkeit</h4>
                                <ul class="list-disc pl-4 space-y-1 text-xs">
                                    <li>Dom sauber hinterlassen (Gurte &amp; Schuhe aufräumen)</li>
                                    <li>Kein Essen im Dom; Getränke nur verschlossen</li>
                                </ul>
                            </div>
                            <div>
                                <h4 class="font-semibold text-indigo-700 mb-1">🧗 Kletterregeln</h4>
                                <ul class="list-disc pl-4 space-y-1 text-xs">
                                    <li>Bouldern: Rote Linie beachten, nicht übereinander</li>
                                    <li>Chalk-Balls erst ab VI+; nur mit Kletterschuhen</li>
                                </ul>
                            </div>
                            <div>
                                <h4 class="font-semibold text-indigo-700 mb-1">🪝 Material</h4>
                                <ul class="list-disc pl-4 space-y-1 text-xs">
                                    <li>Leih-Gurte &amp; Karabiner an vorgesehene Haken</li>
                                    <li>Bälle/Stäbe/Tiere nur für Kursbetrieb</li>
                                </ul>
                            </div>
                            <div>
                                <h4 class="font-semibold text-indigo-700 mb-1">📅 Termine &amp; Beiträge</h4>
                                <ul class="list-disc pl-4 space-y-1 text-xs">
                                    <li>Klettertreff &amp; Kurse nur für ÖTK-Mitglieder</li>
                                    <li>Benützungsbeitrag: <strong>4 €</strong> Erwachsene · <strong>2 €</strong> unter 18 J. · <strong>10 €</strong> Familie</li>
                                </ul>
                            </div>
                        </div>
                    </details>

                    <div class="space-y-4 bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <label class="flex items-start cursor-pointer">
                            <input type="checkbox" name="rules_accepted" value="1" required
                                   class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                   <?= $flash->old('rules_accepted') ? 'checked' : '' ?>>
                            <span class="ml-3 text-sm text-gray-800">
                                Ich habe die <strong>Hallenordnung</strong> gelesen und verpflichte mich, diese sowie die Anweisungen des Personals einzuhalten.
                            </span>
                        </label>
                        <label class="flex items-start cursor-pointer">
                            <input type="checkbox" name="waiver_accepted" value="1" required
                                   class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                   <?= $flash->old('waiver_accepted') ? 'checked' : '' ?>>
                            <span class="ml-3 text-sm text-gray-800">
                                Ich bestätige, dass ich die <strong>Risiken des Kletterns</strong> kenne und nur jene Bereiche selbständig nutze, für die ich ausreichend geschult bin (andernfalls nur unter Aufsicht).
                            </span>
                        </label>
                    </div>
                </div>

                <div class="mt-4 rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-xs text-blue-700">
                    🔒 <strong>Datenschutz:</strong> Wir speichern deine Daten gemäß DSGVO. Sie werden ausschließlich für den Hallenbetrieb verwendet und auf Wunsch sofort gelöscht. Details findest du in unserer <a href="/datenschutzerklaerung" target="_blank" rel="noopener" class="font-semibold underline hover:text-blue-900">Datenschutzerklärung</a>.
                </div>

                <div class="pt-4">
                    <button type="submit"
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                        Registrierung absenden
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
(function () {
    var memberInput   = document.getElementById('member_number');
    var memberWrapper = document.getElementById('member-number-wrapper');
    var guestWrapper  = document.getElementById('guest-address-wrapper');
    var addressInput  = document.getElementById('address');
    var birthInput    = document.getElementById('birth_date');
    var minorNote     = document.getElementById('minor-note');
    var supervision   = document.getElementById('supervision-wrapper');
    var supCheckbox   = document.getElementById('supervision_confirmed');

    if (memberInput) {
        memberInput.addEventListener('input', function (e) {
            var val = e.target.value.replace(/\D/g, '');
            if (val.length > 2) {
                val = val.slice(0, 2) + '-' + val.slice(2, 7);
            }
            e.target.value = val;
        });
    }

    function syncMemberType() {
        var selected = document.querySelector('input[name="member_type"]:checked');
        var type     = selected ? selected.value : 'member';
        if (type === 'guest') {
            memberWrapper.classList.add('hidden');
            memberInput.required = false;
            memberInput.value = '';
            guestWrapper.classList.remove('hidden');
            addressInput.required = true;
        } else {
            memberWrapper.classList.remove('hidden');
            memberInput.required = true;
            guestWrapper.classList.add('hidden');
            addressInput.required = false;
            addressInput.value = '';
        }
    }

    function syncAge() {
        if (!birthInput || !birthInput.value) {
            minorNote.classList.add('hidden');
            supervision.classList.add('hidden');
            supCheckbox.required = false;
            supCheckbox.checked  = false;
            return;
        }
        var birth = new Date(birthInput.value);
        var today = new Date();
        var age   = today.getFullYear() - birth.getFullYear();
        var diffM = today.getMonth() - birth.getMonth();
        if (diffM < 0 || (diffM === 0 && today.getDate() < birth.getDate())) age--;

        if (age < 14) {
            minorNote.classList.add('hidden');
            supervision.classList.remove('hidden');
            supCheckbox.required = true;
        } else if (age < 18) {
            minorNote.classList.remove('hidden');
            supervision.classList.add('hidden');
            supCheckbox.required = false;
            supCheckbox.checked  = false;
        } else {
            minorNote.classList.add('hidden');
            supervision.classList.add('hidden');
            supCheckbox.required = false;
            supCheckbox.checked  = false;
        }
    }

    document.querySelectorAll('input[name="member_type"]').forEach(function (radio) {
        radio.addEventListener('change', syncMemberType);
    });
    birthInput && birthInput.addEventListener('change', syncAge);
    window.addEventListener('pageshow', function () { syncMemberType(); syncAge(); });

    syncMemberType();
    syncAge();
})();
</script>
<?php
$body = ob_get_clean();
echo $view->render('layout', [
    'title'   => 'Registrierung | Kletterdom',
    'body'    => $body,
    'hideNav' => true,
]);
