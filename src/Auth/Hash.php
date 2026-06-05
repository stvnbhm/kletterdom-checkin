<?php

declare(strict_types=1);

namespace Kletterdom\Auth;

final class Hash
{
    public static function make(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
