<?php

declare(strict_types=1);

use Kletterdom\Auth\Auth;
use Kletterdom\Bootstrap;
use Kletterdom\Controllers\AdminController;
use Kletterdom\Controllers\AuthController;
use Kletterdom\Controllers\HomeController;
use Kletterdom\Controllers\RegistrationController;
use Kletterdom\Controllers\StaffController;
use Kletterdom\Http\Csrf;
use Kletterdom\Http\Flash;
use Kletterdom\Http\Request;
use Kletterdom\Http\Response;
use Kletterdom\Http\Session;
use Kletterdom\Http\View;
use Kletterdom\Middleware\AdminMiddleware;
use Kletterdom\Middleware\AuthMiddleware;
use Kletterdom\Middleware\CsrfMiddleware;
use Kletterdom\Middleware\GuestMiddleware;
use Kletterdom\Middleware\ThrottleRegister;
use Kletterdom\Middleware\ThrottleSelfCheckin;
use Kletterdom\Repositories\CheckinRepository;
use Kletterdom\Repositories\RegistrationRepository;
use Kletterdom\Router;
use Kletterdom\Services\AdminService;
use Kletterdom\Services\MemberImportService;
use Kletterdom\Services\RegistrationService;
use Kletterdom\Services\SelfCheckinService;
use Kletterdom\Services\StaffService;
use Kletterdom\Support\Qr;

require_once dirname(__DIR__) . '/src/bootstrap.php';

$container = Bootstrap::init();

// Eine einzige Session-Start-Stelle, anschließend ist sie in Csrf/Flash/Auth nutzbar.
$container->get(Session::class)->start();

$router = new Router();

// ── Controller-Factories (Closures lesen ausschließlich aus dem Container) ──────
$home = static function () use ($container): HomeController {
    return new HomeController($container->get(View::class), $container->get(Auth::class));
};
$auth = static function () use ($container): AuthController {
    return new AuthController($container->get(View::class), $container->get(Auth::class), $container->get(Flash::class));
};
$registration = static function () use ($container): RegistrationController {
    return new RegistrationController(
        $container->get(View::class),
        $container->get(RegistrationService::class),
        $container->get(RegistrationRepository::class),
        $container->get(SelfCheckinService::class),
        $container->get(Session::class),
        $container->get(Flash::class),
        $container->get(Qr::class),
    );
};
$staff = static function () use ($container): StaffController {
    return new StaffController(
        $container->get(View::class),
        $container->get(StaffService::class),
        $container->get(RegistrationService::class),
        $container->get(RegistrationRepository::class),
        $container->get(Flash::class),
    );
};
$admin = static function () use ($container): AdminController {
    return new AdminController(
        $container->get(View::class),
        $container->get(AdminService::class),
        $container->get(MemberImportService::class),
        $container->get(RegistrationRepository::class),
        $container->get(CheckinRepository::class),
        $container->get(Flash::class),
    );
};

// ── Public ─────────────────────────────────────────────────────────────────────
$router->add('GET',  '/',                      static fn ($c, Request $r)                 => $home()->welcome($r));
$router->add('GET',  '/halle-register',        static fn ($c, Request $r)                 => $registration()->showForm($r));
$router->add('POST', '/halle-register',        static fn ($c, Request $r)                 => $registration()->store($r),
    [CsrfMiddleware::class, ThrottleRegister::class]);
$router->add('GET',  '/verify/{token}',        static fn ($c, Request $r, array $p)       => $registration()->verify($r, $p));
$router->add('GET',  '/datenschutzerklaerung', static fn ($c, Request $r)                 => $home()->privacy($r));

// ── Auth ──────────────────────────────────────────────────────────────────────
$router->add('GET',  '/login',  static fn ($c, Request $r) => $auth()->showLogin($r),  [GuestMiddleware::class]);
$router->add('POST', '/login',  static fn ($c, Request $r) => $auth()->login($r),      [CsrfMiddleware::class, GuestMiddleware::class]);
$router->add('POST', '/logout', static fn ($c, Request $r) => $auth()->logout($r),     [CsrfMiddleware::class, AuthMiddleware::class]);

// ── Dashboard ─────────────────────────────────────────────────────────────────
$router->add('GET', '/dashboard', static fn ($c, Request $r) => $home()->dashboard($r), [AuthMiddleware::class]);

// ── Self-Check-in (Self-Service-Terminal) ─────────────────────────────────────
$router->add('GET',  '/self-checkin',      static fn ($c, Request $r) => $registration()->selfCheckin($r),     [AuthMiddleware::class]);
$router->add('POST', '/self-checkin/scan', static fn ($c, Request $r) => $registration()->selfCheckinScan($r), [CsrfMiddleware::class, AuthMiddleware::class, ThrottleSelfCheckin::class]);

// ── Hallendienst ──────────────────────────────────────────────────────────────
$router->add('GET',  '/hallendienst',                                static fn ($c, Request $r)             => $staff()->index($r),         [AuthMiddleware::class]);
$router->add('GET',  '/hallendienst/snapshot',                       static fn ($c, Request $r)             => $staff()->snapshot($r),      [AuthMiddleware::class]);
$router->add('POST', '/hallendienst/{registration}/check-in',        static fn ($c, Request $r, array $p)   => $staff()->checkin($r, $p),   [CsrfMiddleware::class, AuthMiddleware::class]);
$router->add('POST', '/hallendienst/{registration}/checkout',        static fn ($c, Request $r, array $p)   => $staff()->checkout($r, $p),  [CsrfMiddleware::class, AuthMiddleware::class]);
$router->add('POST', '/hallendienst/{registration}/parent-consent',  static fn ($c, Request $r, array $p)   => $staff()->parentConsent($r, $p), [CsrfMiddleware::class, AuthMiddleware::class]);
$router->add('POST', '/hallendienst/checkout-all',                   static fn ($c, Request $r)             => $staff()->checkoutAll($r),   [CsrfMiddleware::class, AuthMiddleware::class]);

// Staff-Check-in via QR-Code-URL (für Scanner auf Hallendienst-Seite + verify-Seite)
$router->add('POST', '/verify/{token}/checkin', static fn ($c, Request $r, array $p) => $registration()->staffCheckinFromVerify($r, $p),
    [CsrfMiddleware::class, AuthMiddleware::class]);

// ── Admin ─────────────────────────────────────────────────────────────────────
$router->add('GET',    '/admin',                                       static fn ($c, Request $r)           => $admin()->index($r),               [AdminMiddleware::class]);
$router->add('POST',   '/admin/import-members',                        static fn ($c, Request $r)           => $admin()->importMembers($r),       [CsrfMiddleware::class, AdminMiddleware::class]);
$router->add('GET',    '/admin/export-checkins',                       static fn ($c, Request $r)           => $admin()->exportCheckins($r),      [AdminMiddleware::class]);
$router->add('GET',    '/admin/export-registrations',                  static fn ($c, Request $r)           => $admin()->exportRegistrations($r), [AdminMiddleware::class]);
$router->add('DELETE', '/admin/registrations/{registration}',          static fn ($c, Request $r, array $p) => $admin()->destroyRegistration($r, $p), [CsrfMiddleware::class, AdminMiddleware::class]);
$router->add('PATCH',  '/admin/registrations/{registration}/notes',    static fn ($c, Request $r, array $p) => $admin()->updateNotes($r, $p),     [CsrfMiddleware::class, AdminMiddleware::class]);
$router->add('DELETE', '/admin/inactive-members',                      static fn ($c, Request $r)           => $admin()->deleteInactiveMembers($r), [CsrfMiddleware::class, AdminMiddleware::class]);
$router->add('DELETE', '/admin/stale-registrations',                   static fn ($c, Request $r)           => $admin()->deleteStaleRegistrations($r), [CsrfMiddleware::class, AdminMiddleware::class]);

// ── Dispatch + globale Fehlerbehandlung ───────────────────────────────────────
$request = Request::capture();

try {
    $response = $router->dispatch($container, $request);
} catch (\Throwable $e) {
    error_log('Unhandled exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

    $config = $container->get('config');
    if ($request->expectsJson()) {
        $response = Response::json([
            'success' => false,
            'message' => ($config['debug'] ?? false) ? $e->getMessage() : 'Unerwarteter Serverfehler.',
        ], 500);
    } else {
        $body = ($config['debug'] ?? false)
            ? '<h1>500</h1><pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</pre>'
            : '<h1>500 — Es ist ein Fehler aufgetreten</h1>';
        $response = Response::html($body, 500);
    }
}

$response->send();
