<?php

declare(strict_types=1);

namespace Kletterdom\Auth;

final class RememberCookie
{
    private const COOKIE_NAME = 'kletterdom_remember';

    public function __construct(
        private readonly string $hashKey,
        private readonly bool $secure,
        private readonly string $sameSite,
        private readonly int $rememberDays,
    ) {
    }

    public function lifetimeSeconds(): int
    {
        return max(1, $this->rememberDays) * 86_400;
    }

    public function issue(int $userId): void
    {
        $expiry = time() + $this->lifetimeSeconds();
        $this->setCookie($this->sign($userId, $expiry), $expiry);
    }

    public function forget(): void
    {
        $this->setCookie('', time() - 86_400);
    }

    public function userId(): ?int
    {
        $raw = $_COOKIE[self::COOKIE_NAME] ?? '';
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $parts = explode('.', $raw, 3);
        if (count($parts) !== 3) {
            return null;
        }

        [$idPart, $expiryPart, $mac] = $parts;
        if (! ctype_digit($idPart) || ! ctype_digit($expiryPart)) {
            return null;
        }

        $userId = (int) $idPart;
        $expiry = (int) $expiryPart;
        if ($userId < 1 || $expiry < time()) {
            return null;
        }

        $expected = hash_hmac('sha256', $idPart . '.' . $expiryPart, $this->hashKey);
        if (! hash_equals($expected, $mac)) {
            return null;
        }

        return $userId;
    }

    private function sign(int $userId, int $expiry): string
    {
        $payload = $userId . '.' . $expiry;
        return $payload . '.' . hash_hmac('sha256', $payload, $this->hashKey);
    }

    private function setCookie(string $value, int $expires): void
    {
        setcookie(self::COOKIE_NAME, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $this->secure,
            'httponly' => true,
            'samesite' => $this->sameSite,
        ]);
    }
}
