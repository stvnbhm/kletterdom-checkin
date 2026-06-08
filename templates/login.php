<?php
/** @var \Kletterdom\Http\Csrf  $csrf
 *  @var \Kletterdom\Http\Flash $flash
 */
$old = $flash->old('email', '');
ob_start();
?>
<main class="min-h-screen bg-gray-100 flex flex-col justify-center items-center p-6">
    <div class="w-full sm:max-w-md bg-white shadow-md rounded-lg px-6 py-8">
        <h1 class="text-lg font-semibold text-gray-800 mb-6">Hallendienst-Login</h1>

        <form method="POST" action="/login" class="space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES) ?>">

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">E-Mail</label>
                <input id="email" type="email" name="email" required autofocus autocomplete="username"
                       value="<?= htmlspecialchars((string) $old, ENT_QUOTES) ?>"
                       class="block mt-1 w-full p-2 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Passwort</label>
                <input id="password" type="password" name="password" required autocomplete="current-password"
                       class="block mt-1 w-full p-2 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div class="flex items-center">
                <input id="remember" type="checkbox" name="remember" value="1"
                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                <label for="remember" class="ml-2 text-sm text-gray-700">Angemeldet bleiben</label>
            </div>

            <div class="flex justify-end">
                <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2 px-4 rounded-lg transition">
                    Anmelden
                </button>
            </div>
        </form>

        <p class="mt-6 text-xs text-gray-500">Passwort vergessen? Wende dich an einen Administrator.</p>
    </div>
</main>
<?php
$body = ob_get_clean();
echo $view->render('layout', [
    'title'   => 'Login | Kletterdom',
    'body'    => $body,
    'hideNav' => true,
]);
