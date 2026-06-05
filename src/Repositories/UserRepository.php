<?php

declare(strict_types=1);

namespace Kletterdom\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => strtolower(trim($email))]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function upsert(string $email, string $name, ?string $passwordHash, bool $isAdmin): int
    {
        $now      = date('Y-m-d H:i:s');
        $existing = $this->findByEmail($email);
        $email    = strtolower(trim($email));

        if ($existing === null) {
            if ($passwordHash === null) {
                throw new \InvalidArgumentException('Password hash is required when creating a user.');
            }
            $stmt = $this->db->prepare(
                'INSERT INTO users (name, email, password, is_admin, created_at, updated_at)
                 VALUES (:name, :email, :password, :is_admin, :created_at, :updated_at)'
            );
            $stmt->execute([
                'name'       => $name,
                'email'      => $email,
                'password'   => $passwordHash,
                'is_admin'   => $isAdmin ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            return (int) $this->db->lastInsertId();
        }

        $fields = [
            'name'       => $name,
            'is_admin'   => $isAdmin ? 1 : 0,
            'updated_at' => $now,
        ];
        if ($passwordHash !== null) {
            $fields['password'] = $passwordHash;
        }

        $sets = implode(', ', array_map(static fn ($k) => "{$k} = :{$k}", array_keys($fields)));
        $stmt = $this->db->prepare("UPDATE users SET {$sets} WHERE id = :id");
        $stmt->execute([...$fields, 'id' => (int) $existing['id']]);

        return (int) $existing['id'];
    }
}
