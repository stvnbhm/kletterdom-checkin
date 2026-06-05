<?php

declare(strict_types=1);

namespace Kletterdom\Repositories;

use PDO;

final class MemberRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function findByNumber(string $memberNumber): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM members WHERE member_number = :n LIMIT 1');
        $stmt->execute(['n' => $memberNumber]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function existsByNameBirthHash(string $hash): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM members WHERE name_birth_hash = :h LIMIT 1');
        $stmt->execute(['h' => $hash]);
        return (bool) $stmt->fetchColumn();
    }

    public function upsert(string $memberNumber, string $membershipStatus, string $paymentStatus, ?string $nameBirthHash): void
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare(
            'INSERT INTO members (member_number, membership_status, payment_status, name_birth_hash, last_imported_at, created_at, updated_at)
             VALUES (:n, :ms, :ps, :h, :imported_at, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                 membership_status = VALUES(membership_status),
                 payment_status    = VALUES(payment_status),
                 name_birth_hash   = VALUES(name_birth_hash),
                 last_imported_at  = VALUES(last_imported_at),
                 updated_at        = VALUES(updated_at)'
        );
        $stmt->execute([
            'n'           => $memberNumber,
            'ms'          => $membershipStatus,
            'ps'          => $paymentStatus,
            'h'           => $nameBirthHash,
            'imported_at' => $now,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }

    /** @return array<int,string> */
    public function memberNumbersNotIn(array $memberNumbers): array
    {
        if ($memberNumbers === []) {
            return $this->db->query('SELECT member_number FROM members')->fetchAll(PDO::FETCH_COLUMN);
        }
        $placeholders = implode(',', array_fill(0, count($memberNumbers), '?'));
        $stmt = $this->db->prepare("SELECT member_number FROM members WHERE member_number NOT IN ({$placeholders})");
        $stmt->execute(array_values($memberNumbers));
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @param array<int,string> $memberNumbers */
    public function markInactive(array $memberNumbers): void
    {
        if ($memberNumbers === []) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $placeholders = implode(',', array_fill(0, count($memberNumbers), '?'));
        $stmt = $this->db->prepare(
            "UPDATE members
             SET membership_status = 'inactive', last_imported_at = ?, updated_at = ?
             WHERE member_number IN ({$placeholders})"
        );
        $stmt->execute([$now, $now, ...array_values($memberNumbers)]);
    }

    public function countByStatus(string $status): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM members WHERE membership_status = :s');
        $stmt->execute(['s' => $status]);
        return (int) $stmt->fetchColumn();
    }

    /** @return array<int,string> */
    public function memberNumbersByStatus(string $status): array
    {
        $stmt = $this->db->prepare('SELECT member_number FROM members WHERE membership_status = :s');
        $stmt->execute(['s' => $status]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @param array<int,string> $memberNumbers */
    public function deleteByNumbers(array $memberNumbers): int
    {
        if ($memberNumbers === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($memberNumbers), '?'));
        $stmt = $this->db->prepare("DELETE FROM members WHERE member_number IN ({$placeholders})");
        $stmt->execute(array_values($memberNumbers));
        return $stmt->rowCount();
    }
}
