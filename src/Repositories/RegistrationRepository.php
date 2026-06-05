<?php

declare(strict_types=1);

namespace Kletterdom\Repositories;

use PDO;

final class RegistrationRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM registrations WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public function findByToken(string $token): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM registrations WHERE qr_token = :t LIMIT 1');
        $stmt->execute(['t' => $token]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public function findByMemberNumber(string $memberNumber): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM registrations WHERE member_number = :n LIMIT 1');
        $stmt->execute(['n' => $memberNumber]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public function findByNameBirthHash(string $hash): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM registrations WHERE name_birth_hash = :h LIMIT 1');
        $stmt->execute(['h' => $hash]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public function findGuestByNameBirthHash(string $hash): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM registrations WHERE name_birth_hash = :h AND member_type = 'guest' LIMIT 1"
        );
        $stmt->execute(['h' => $hash]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $now              = date('Y-m-d H:i:s');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $columns      = array_keys($data);
        $placeholders = array_map(static fn ($c): string => ':' . $c, $columns);

        $sql  = 'INSERT INTO registrations (' . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void
    {
        if ($data === []) {
            return;
        }
        $data['updated_at'] = date('Y-m-d H:i:s');

        $sets = implode(', ', array_map(static fn ($c) => "{$c} = :{$c}", array_keys($data)));
        $sql  = "UPDATE registrations SET {$sets} WHERE id = :__id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([...$data, '__id' => $id]);
    }

    public function increment(int $id, string $column, int $by = 1): void
    {
        $allowed = ['trial_visits_count'];
        if (! in_array($column, $allowed, true)) {
            throw new \InvalidArgumentException("Increment not allowed on column {$column}");
        }
        $stmt = $this->db->prepare(
            "UPDATE registrations SET {$column} = {$column} + :by, updated_at = :now WHERE id = :id"
        );
        $stmt->execute(['by' => $by, 'now' => date('Y-m-d H:i:s'), 'id' => $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM registrations WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** @param array<int,int> $ids */
    public function deleteMany(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("DELETE FROM registrations WHERE id IN ({$placeholders})");
        $stmt->execute(array_values($ids));
        return $stmt->rowCount();
    }

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM registrations')->fetchColumn();
    }

    /**
     * Hallendienst-Liste: ohne Suche → nur aktuell eingecheckte Personen.
     * Mit Suche → alle treffenden Personen (egal ob eingecheckt).
     *
     * @return array{rows:array<int,array<string,mixed>>, total:int}
     */
    public function listForStaff(?string $query, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where  = [];
        $params = [];

        if ($query !== null && $query !== '') {
            $like               = '%' . $query . '%';
            $where[]            = '(r.first_name LIKE :like
                                     OR r.last_name  LIKE :like
                                     OR r.member_number LIKE :like
                                     OR r.notes      LIKE :like)';
            $params['like']     = $like;
        } else {
            $where[] = 'EXISTS (SELECT 1 FROM checkins ci
                                 WHERE ci.registration_id = r.id
                                   AND ci.checked_out_at IS NULL)';
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $countSql  = "SELECT COUNT(*) FROM registrations r {$whereSql}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT r.*,
                       (SELECT MAX(ci.checked_in_at) FROM checkins ci
                          WHERE ci.registration_id = r.id AND ci.checked_out_at IS NULL) AS current_checkin_at,
                       (SELECT COUNT(*) FROM checkins ci WHERE ci.registration_id = r.id)        AS checkins_count,
                       (SELECT 1 FROM members m WHERE m.member_number = r.member_number LIMIT 1) AS member_match
                FROM registrations r
                {$whereSql}
                ORDER BY current_checkin_at DESC, r.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Admin-Liste: Komma-separierte Suchterme, Status-Filter,
     * Paginierung. Direkt per LIKE auf Klartext-PII.
     *
     * @return array{rows:array<int,array<string,mixed>>, total:int}
     */
    public function listForAdmin(?string $query, ?string $statusFilter, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where  = [];
        $params = [];

        $terms = array_values(array_filter(array_map(
            'trim',
            $query === null ? [] : explode(',', $query),
        )));

        if ($terms !== []) {
            $groupParts = [];
            foreach ($terms as $i => $term) {
                $like              = '%' . $term . '%';
                $key               = 'q' . $i;
                $params[$key]      = $like;
                $groupParts[]      = "(r.first_name LIKE :{$key} OR r.last_name LIKE :{$key} OR r.member_number LIKE :{$key} OR r.notes LIKE :{$key})";
            }
            $where[] = '(' . implode(' OR ', $groupParts) . ')';
        }

        if ($statusFilter === 'guest') {
            $where[] = "r.member_type = 'guest'";
        } elseif (in_array($statusFilter, ['green', 'blue', 'orange', 'red'], true)) {
            $where[]            = 'r.access_status = :status';
            $params['status']   = $statusFilter;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM registrations r {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT r.*,
                       (SELECT COUNT(*) FROM checkins ci WHERE ci.registration_id = r.id) AS checkins_count
                FROM registrations r
                {$whereSql}
                ORDER BY r.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<int,array<string,mixed>> */
    public function allForExport(): array
    {
        return $this->db->query(
            'SELECT r.*, (SELECT COUNT(*) FROM checkins c WHERE c.registration_id = r.id) AS checkins_count
             FROM registrations r
             ORDER BY r.last_name, r.first_name'
        )->fetchAll();
    }

    /** @param array<int,string> $memberNumbers */
    public function syncByMemberNumbers(array $memberNumbers, string $accessStatus, string $accessReason, string $paymentStatus): void
    {
        if ($memberNumbers === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($memberNumbers), '?'));
        $stmt = $this->db->prepare(
            "UPDATE registrations
             SET access_status = ?, access_reason = ?, payment_status = ?, updated_at = ?
             WHERE member_number IN ({$placeholders})"
        );
        $stmt->execute([$accessStatus, $accessReason, $paymentStatus, date('Y-m-d H:i:s'), ...array_values($memberNumbers)]);
    }

    /** @param array<int,string> $memberNumbers */
    public function syncPaymentByMemberNumbers(array $memberNumbers, string $paymentStatus): void
    {
        if ($memberNumbers === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($memberNumbers), '?'));
        $stmt = $this->db->prepare(
            "UPDATE registrations
             SET payment_status = ?, updated_at = ?
             WHERE member_number IN ({$placeholders})"
        );
        $stmt->execute([$paymentStatus, date('Y-m-d H:i:s'), ...array_values($memberNumbers)]);
    }

    /** @param array<int,string> $memberNumbers */
    public function upgradeGuestsToMember(string $nameBirthHash, string $memberNumber): void
    {
        $stmt = $this->db->prepare(
            "UPDATE registrations
             SET member_type = 'member', member_number = :n, updated_at = :now
             WHERE name_birth_hash = :h AND member_type = 'guest'"
        );
        $stmt->execute(['n' => $memberNumber, 'h' => $nameBirthHash, 'now' => date('Y-m-d H:i:s')]);
    }

    /** @param array<int,string> $memberNumbers
     *  @return array<int,int>
     */
    public function idsByMemberNumbers(array $memberNumbers): array
    {
        if ($memberNumbers === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($memberNumbers), '?'));
        $stmt = $this->db->prepare("SELECT id FROM registrations WHERE member_number IN ({$placeholders})");
        $stmt->execute(array_values($memberNumbers));
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Veraltete Registrierungen ohne aktiven Check-in, deren letzter
     * Check-in (oder created_at, falls noch keiner) mehr als 2 Jahre
     * zurückliegt.
     *
     * @return array<int,int>
     */
    public function staleIds(): array
    {
        $cutoff = date('Y-m-d H:i:s', strtotime('-2 years'));
        $stmt   = $this->db->prepare(
            'SELECT r.id FROM registrations r
             WHERE NOT EXISTS (
                 SELECT 1 FROM checkins c
                 WHERE c.registration_id = r.id AND c.checked_out_at IS NULL
             )
             AND COALESCE(
                 (SELECT MAX(c.checked_in_at) FROM checkins c WHERE c.registration_id = r.id),
                 r.created_at
             ) < :cut'
        );
        $stmt->execute(['cut' => $cutoff]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function countStale(): int
    {
        return count($this->staleIds());
    }

    /** @return array<string,mixed>|null  Aktiver Check-in oder null */
    public function currentCheckin(int $registrationId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM checkins
             WHERE registration_id = :r AND checked_out_at IS NULL
             ORDER BY checked_in_at DESC LIMIT 1'
        );
        $stmt->execute(['r' => $registrationId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
