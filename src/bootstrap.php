<?php

declare(strict_types=1);

namespace Kletterdom;

use Kletterdom\Auth\Auth;
use Kletterdom\Auth\RememberCookie;
use Kletterdom\Database\Connection;
use Kletterdom\Http\Csrf;
use Kletterdom\Http\Flash;
use Kletterdom\Http\Session;
use Kletterdom\Http\Throttle;
use Kletterdom\Http\View;
use Kletterdom\Middleware\AdminMiddleware;
use Kletterdom\Middleware\AuthMiddleware;
use Kletterdom\Middleware\CsrfMiddleware;
use Kletterdom\Middleware\GuestMiddleware;
use Kletterdom\Middleware\ThrottleRegister;
use Kletterdom\Middleware\ThrottleSelfCheckin;
use Kletterdom\Repositories\CheckinRepository;
use Kletterdom\Repositories\MemberRepository;
use Kletterdom\Repositories\RegistrationRepository;
use Kletterdom\Repositories\UserRepository;
use Kletterdom\Services\AdminService;
use Kletterdom\Services\MemberImportService;
use Kletterdom\Services\RegistrationService;
use Kletterdom\Services\SelfCheckinService;
use Kletterdom\Services\StaffService;
use Kletterdom\Support\PrivacyIndex;
use Kletterdom\Support\Qr;
use PDO;
use RuntimeException;

require_once __DIR__ . '/../vendor/autoload.php';

final class Bootstrap
{
    public static function loadEnv(string $envFile): void
    {
        if (! is_readable($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (! str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            if ($key === '' || array_key_exists($key, $_ENV)) {
                continue;
            }

            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    public static function init(): Container
    {
        $root = dirname(__DIR__);

        self::loadEnv($root . '/.env');

        $config = require $root . '/config/app.php';
        $dbConf = require $root . '/config/database.php';

        date_default_timezone_set($config['timezone']);

        if (! is_dir($config['session']['save_path'])) {
            @mkdir($config['session']['save_path'], 0777, true);
        }

        self::configureSession($config['session']);

        if (($config['hash_key'] ?? '') === '') {
            throw new RuntimeException('HASH_KEY is not configured in .env');
        }

        $container = new Container();
        $container->set('config', static fn () => $config);

        $container->set(PrivacyIndex::class, static fn () => new PrivacyIndex($config['hash_key']));
        $container->set(Session::class,      static fn () => new Session());
        $container->set(Csrf::class,         static fn (Container $c) => new Csrf($c->get(Session::class)));
        $container->set(Flash::class,        static fn (Container $c) => new Flash($c->get(Session::class)));

        $container->set(PDO::class, static fn () => Connection::pdo($dbConf));

        $container->set(UserRepository::class,         static fn (Container $c) => new UserRepository($c->get(PDO::class)));
        $container->set(MemberRepository::class,       static fn (Container $c) => new MemberRepository($c->get(PDO::class)));
        $container->set(RegistrationRepository::class, static fn (Container $c) => new RegistrationRepository($c->get(PDO::class)));
        $container->set(CheckinRepository::class,      static fn (Container $c) => new CheckinRepository($c->get(PDO::class)));

        $container->set(RememberCookie::class, static fn () => new RememberCookie(
            $config['hash_key'],
            $config['session']['secure_cookie'],
            $config['session']['same_site'],
            $config['session']['remember_days'],
        ));

        $container->set(Auth::class, static fn (Container $c) => new Auth(
            $c->get(Session::class),
            $c->get(UserRepository::class),
            $c->get(RememberCookie::class),
            $config['session']['lifetime_minutes'] * 60,
        ));

        $container->set(Qr::class, static fn () => new Qr());

        $container->set(SelfCheckinService::class, static fn (Container $c) => new SelfCheckinService(
            $c->get(CheckinRepository::class),
            $c->get(RegistrationRepository::class),
        ));

        $container->set(RegistrationService::class, static fn (Container $c) => new RegistrationService(
            $c->get(RegistrationRepository::class),
            $c->get(MemberRepository::class),
            $c->get(PrivacyIndex::class),
            $c->get(CheckinRepository::class),
        ));

        $container->set(StaffService::class, static fn (Container $c) => new StaffService(
            $c->get(RegistrationRepository::class),
            $c->get(CheckinRepository::class),
        ));

        $container->set(AdminService::class, static fn (Container $c) => new AdminService(
            $c->get(RegistrationRepository::class),
            $c->get(CheckinRepository::class),
            $c->get(MemberRepository::class),
        ));

        $container->set(MemberImportService::class, static fn (Container $c) => new MemberImportService(
            $c->get(MemberRepository::class),
            $c->get(RegistrationRepository::class),
            $c->get(PrivacyIndex::class),
        ));

        $container->set(Throttle::class, static fn () => new Throttle($root . '/storage/throttle'));

        $container->set(View::class, static function (Container $c) use ($root): View {
            $view = new View($root . '/templates');
            $view->share('auth', $c->get(Auth::class));
            $view->share('csrf', $c->get(Csrf::class));
            $view->share('flash', $c->get(Flash::class));
            return $view;
        });

        $container->set(CsrfMiddleware::class,        static fn (Container $c) => new CsrfMiddleware($c->get(Csrf::class)));
        $container->set(AuthMiddleware::class,        static fn (Container $c) => new AuthMiddleware($c->get(Auth::class)));
        $container->set(AdminMiddleware::class,       static fn (Container $c) => new AdminMiddleware($c->get(Auth::class)));
        $container->set(GuestMiddleware::class,       static fn (Container $c) => new GuestMiddleware($c->get(Auth::class)));
        $container->set(ThrottleRegister::class,      static fn (Container $c) => new ThrottleRegister($c->get(Throttle::class)));
        $container->set(ThrottleSelfCheckin::class,   static fn (Container $c) => new ThrottleSelfCheckin($c->get(Throttle::class)));

        return $container;
    }

    /** @param array{lifetime_minutes:int, remember_days:int, secure_cookie:bool, same_site:string, save_path:string} $session */
    private static function configureSession(array $session): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $lifetime = $session['lifetime_minutes'] * 60;

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        ini_set('session.save_handler', 'files');
        session_save_path($session['save_path']);
        session_name('kletterdom_session');
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $session['secure_cookie'],
            'httponly' => true,
            'samesite' => $session['same_site'],
        ]);
    }
}
