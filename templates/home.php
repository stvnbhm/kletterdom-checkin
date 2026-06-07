<?php
ob_start();
?>
<div class="flex min-h-screen flex-col">
    <main class="flex flex-grow items-center justify-center p-6">
        <div class="w-full max-w-xl rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm">
            <div class="mx-auto mb-8 flex justify-center">
                <img src="/assets/images/logo.png" alt="ÖTK Langenlois Alpinsport Logo" class="h-24 w-auto object-contain" onerror="this.style.display='none'">
            </div>

            <h1 class="mb-2 text-2xl font-bold text-gray-900">Willkommen!</h1>
            <p class="mb-8 text-sm leading-relaxed text-gray-600">
                Bitte registriere dich für den Zutritt zur Kletterhalle. Wenn du noch kein Vereinsmitglied bist, kannst du hier direkt dem ÖTK beitreten.
            </p>

            <div class="space-y-4">
                <a href="/halle-register"
                   class="flex w-full items-center justify-center rounded-lg border border-transparent bg-indigo-600 px-6 py-3.5 font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                    Registrierung Kletterdom
                </a>
                <a href="https://beitritt.oetk.at" target="_blank" rel="noopener noreferrer"
                   class="flex w-full items-center justify-center rounded-lg border border-gray-300 bg-white px-6 py-3.5 font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
                    ÖTK Mitglied werden
                </a>
            </div>
        </div>
    </main>

    <footer class="shrink-0 py-6 text-center">
        <a href="/login" class="text-xs font-medium text-gray-400 transition hover:text-gray-600">Hallendienst Login</a>
    </footer>
</div>
<?php
$body = ob_get_clean();
echo $view->render('layout', [
    'title'   => 'Startseite Kletterdom',
    'body'    => $body,
    'hideNav' => true,
]);
