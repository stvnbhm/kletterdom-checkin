<?php

declare(strict_types=1);

namespace Kletterdom\Repositories;

use PDO;

final class CheckinRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function create(int $registrationId, string $checkedInAt): int
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare(
            'INSERT INTO checkins (registration_id, checked_in_at, created_at, updated_at)
             VALUES (:r, :t, :created_at, :updated_at)'
        );
        $stmt->execute([
            'r'          => $registrationId,
            't'          => $checkedInAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function closeOpen(int $registrationId, string $checkedOutAt): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM checkins
             WHERE registration_id = :r AND checked_out_at IS NULL
             ORDER BY checked_in_at DESC LIMIT 1'
        );
        $stmt->execute(['r' => $registrationId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $now = date('Y-m-d H:i:s');
        $upd = $this->db->prepare(
            'UPDATE checkins SET checked_out_at = :out, updated_at = :now WHERE id = :id'
        );
        $upd->execute(['out' => $checkedOutAt, 'now' => $now, 'id' => (int) $row['id']]);

        $row['checked_out_at'] = $checkedOutAt;
        return $row;
    }

    /** @return array<int,array<string,mixed>> */
    public function openCheckins(): array
    {
        return $this->db->query(
            'SELECT * FROM checkins WHERE checked_out_at IS NULL ORDER BY checked_in_at'
        )->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function expiredOpen(string $cutoff): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM checkins
             WHERE checked_out_at IS NULL AND checked_in_at <= :cut'
        );
        $stmt->execute(['cut' => $cutoff]);
        return $stmt->fetchAll();
    }

    public function closeById(int $id, string $checkedOutAt): void
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare(
            'UPDATE checkins SET checked_out_at = :out, updated_at = :now WHERE id = :id'
        );
        $stmt->execute(['out' => $checkedOutAt, 'now' => $now, 'id' => $id]);
    }

    public function hasOpenForRegistration(int $registrationId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM checkins WHERE registration_id = :r AND checked_out_at IS NULL LIMIT 1'
        );
        $stmt->execute(['r' => $registrationId]);
        return (bool) $stmt->fetchColumn();
    }

    public function countForRegistration(int $registrationId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM checkins WHERE registration_id = :r');
        $stmt->execute(['r' => $registrationId]);
        return (int) $stmt->fetchColumn();
    }

    public function countToday(): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM checkins WHERE DATE(checked_in_at) = CURDATE()'
        );
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public function countTodayByMemberType(string $type): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM checkins c
             JOIN registrations r ON r.id = c.registration_id
             WHERE DATE(c.checked_in_at) = CURDATE() AND r.member_type = :t'
        );
        $stmt->execute(['t' => $type]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<int,int> $registrationIds
     * @return array<int,string> registrationId => "01.02.2025 und am 12.02.2025"
     */
    public function pastCheckinDates(array $registrationIds): array
    {
        if ($registrationIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($registrationIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT registration_id, checked_in_at FROM checkins
             WHERE registration_id IN ({$placeholders})
             ORDER BY checked_in_at"
        );
        $stmt->execute(array_values($registrationIds));

        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $rid           = (int) $row['registration_id'];
            $grouped[$rid] = $grouped[$rid] ?? [];
            $grouped[$rid][] = date('d.m.Y', strtotime((string) $row['checked_in_at']));
        }

        $result = [];
        foreach ($grouped as $rid => $dates) {
            $result[$rid] = implode(' und am ', $dates);
        }
        return $result;
    }

    /** @return array<int,array<string,mixed>> */
    public function chartLast30Days(): array
    {
        return $this->db->query(
            "SELECT DATE(checked_in_at) AS day, COUNT(*) AS total
             FROM checkins
             WHERE checked_in_at >= (NOW() - INTERVAL 29 DAY)
             GROUP BY day
             ORDER BY day"
        )->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function exportBetween(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, r.first_name, r.last_name, r.email, r.address, r.birth_date,
                    r.member_type, r.member_number, r.access_status, r.access_reason,
                    r.trial_visits_count, r.needs_supervision
             FROM checkins c
             JOIN registrations r ON r.id = c.registration_id
             WHERE c.checked_in_at BETWEEN :from AND :to
             ORDER BY c.checked_in_at'
        );
        $stmt->execute(['from' => $from, 'to' => $to]);
        return $stmt->fetchAll();
    }

    /** @return array<int,string> registration_id => last_checked_in_at */
    public function lastCheckins(): array
    {
        $rows = $this->db->query(
            'SELECT registration_id, MAX(checked_in_at) AS last_checked_in_at
             FROM checkins GROUP BY registration_id'
        )->fetchAll();
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['registration_id']] = (string) $r['last_checked_in_at'];
        }
        return $map;
    }
}
