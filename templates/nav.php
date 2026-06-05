<?php
/** @var \Kletterdom\Auth\Auth $auth
 *  @var \Kletterdom\Http\Csrf $csrf
 */
$user    = $auth->user();
$isAdmin = $auth->isAdmin();
?>
<nav class="bg-white border-b border-gray-100" data-component="nav">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16 items-center">

            <div class="flex items-center gap-6">
                <a href="/dashboard" class="text-sm font-semibold text-gray-800">Kletterdom</a>

                <div class="hidden sm:flex items-center gap-4">
                    <?php if ($isAdmin): ?>
                        <a href="/admin" class="text-sm font-medium text-gray-600 hover:text-gray-900">🛠️ Admin</a>
                    <?php endif; ?>
                    <a href="/hallendienst" class="text-sm font-medium text-gray-600 hover:text-gray-900">🧗 Check-In</a>
                    <a href="/self-checkin" target="_blank" rel="noopener" class="text-sm font-medium text-gray-600 hover:text-gray-900">📷 Self-Check-in</a>
                    <a href="/halle-register" target="_blank" rel="noopener" class="text-sm font-medium text-gray-600 hover:text-gray-900">📝 Registrierung</a>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span class="hidden sm:inline text-sm text-gray-500"><?= htmlspecialchars((string) ($user['name'] ?? ''), ENT_QUOTES) ?></span>
                <form method="POST" action="/logout">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf->token(), ENT_QUOTES) ?>">
                    <button type="submit"
                            class="text-sm text-gray-500 hover:text-gray-800 underline">
                        Logout
                    </button>
                </form>

                <button type="button"
                        onclick="document.getElementById('mobile-nav').classList.toggle('hidden')"
                        class="sm:hidden inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>

        <div id="mobile-nav" class="hidden sm:hidden pb-3 space-y-1">
            <?php if ($isAdmin): ?>
                <a href="/admin" class="block px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">🛠️ Admin</a>
            <?php endif; ?>
            <a href="/hallendienst" class="block px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">🧗 Check-In</a>
            <a href="/self-checkin" target="_blank" rel="noopener" class="block px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">📷 Self-Check-in</a>
            <a href="/halle-register" target="_blank" rel="noopener" class="block px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">📝 Registrierung</a>
        </div>
    </div>
</nav>
