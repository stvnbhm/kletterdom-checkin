<?php

declare(strict_types=1);

namespace Kletterdom\Http;

final class Session
{
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_start();
    }

    public function regenerate(bool $deleteOld = true): void
    {
        $this->start();
        session_regenerate_id($deleteOld);
    }

    public function extendCookieLifetime(int $lifetimeSeconds): void
    {
        $this->start();
        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires'  => time() + max(60, $lifetimeSeconds),
            'path'     => $params['path'] !== '' ? $params['path'] : '/',
            'domain'   => $params['domain'],
            'secure'   => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'],
                ],
            );
        }

        session_destroy();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function has(string $key): bool
    {
        $this->start();
        return isset($_SESSION[$key]);
    }
}
