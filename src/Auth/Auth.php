<?php

declare(strict_types=1);

namespace Kletterdom\Auth;

use Kletterdom\Http\Session;
use Kletterdom\Repositories\UserRepository;

final class Auth
{
    private const SESSION_KEY = 'auth_user_id';

    /** @var array<string,mixed>|null */
    private ?array $user = null;

    public function __construct(
        private readonly Session        $session,
        private readonly UserRepository $users,
    ) {
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            return false;
        }
        if (! Hash::verify($password, (string) $user['password'])) {
            return false;
        }

        $this->session->regenerate();
        $this->session->set(self::SESSION_KEY, (int) $user['id']);
        $this->user = $user;

        return true;
    }

    public function logout(): void
    {
        $this->session->forget(self::SESSION_KEY);
        $this->session->regenerate();
        $this->user = null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function isAdmin(): bool
    {
        $user = $this->user();
        return $user !== null && (int) ($user['is_admin'] ?? 0) === 1;
    }

    /** @return array<string,mixed>|null */
    public function user(): ?array
    {
        if ($this->user !== null) {
            return $this->user;
        }
        $id = $this->session->get(self::SESSION_KEY);
        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            return null;
        }
        $user = $this->users->find((int) $id);
        if ($user === null) {
            $this->session->forget(self::SESSION_KEY);
            return null;
        }
        return $this->user = $user;
    }
}
