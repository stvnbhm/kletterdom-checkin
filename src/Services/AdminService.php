<?php

declare(strict_types=1);

namespace Kletterdom\Services;

use Kletterdom\Repositories\CheckinRepository;
use Kletterdom\Repositories\MemberRepository;
use Kletterdom\Repositories\RegistrationRepository;

final class AdminService
{
    public function __construct(
        private readonly RegistrationRepository $registrations,
        private readonly CheckinRepository      $checkins,
        private readonly MemberRepository       $members,
    ) {
    }

    /**
     * @return array{
     *     registrations:array<int,array<string,mixed>>,
     *     stats:array<string,int>,
     *     chart:array{labels:array<int,string>, values:array<int,int>},
     *     pagination:array{page:int,per_page:int,total:int,pages:int}
     * }
     */
    public function dashboard(?string $query, ?string $statusFilter, int $page = 1, int $perPage = 30): array
    {
        $listing = $this->registrations->listForAdmin($query, $statusFilter, $page, $perPage);

        $stats = [
            'total_registrations' => $this->registrations->count(),
            'checked_in_today'    => $this->checkins->countToday(),
            'members'             => $this->members->countByStatus('active'),
            'guests_today'        => $this->checkins->countTodayByMemberType('guest'),
            'inactive_members'    => $this->members->countByStatus('inactive'),
            'stale_registrations' => $this->registrations->countStale(),
        ];

        $chart = $this->buildChartLast30Days();

        $pages = (int) max(1, (int) ceil($listing['total'] / $perPage));

        return [
            'registrations' => $listing['rows'],
            'stats'         => $stats,
            'chart'         => $chart,
            'pagination'    => [
                'page'     => $page,
                'per_page' => $perPage,
                'total'    => $listing['total'],
                'pages'    => $pages,
            ],
        ];
    }

    public function deleteRegistration(int $registrationId): void
    {
        $this->registrations->delete($registrationId);
    }

    public function updateNotes(int $registrationId, ?string $notes): void
    {
        $clean = $notes === null ? null : trim($notes);
        $this->registrations->update($registrationId, [
            'notes' => $clean === '' ? null : $clean,
        ]);
    }

    public function deleteInactiveMembers(): int
    {
        $memberNumbers = $this->members->memberNumbersByStatus('inactive');
        if ($memberNumbers === []) {
            return 0;
        }
        $registrationIds = $this->registrations->idsByMemberNumbers($memberNumbers);
        $this->registrations->deleteMany($registrationIds);
        $this->members->deleteByNumbers($memberNumbers);

        return count($memberNumbers);
    }

    public function deleteStaleRegistrations(): int
    {
        $ids = $this->registrations->staleIds();
        if ($ids === []) {
            return 0;
        }
        $this->registrations->deleteMany($ids);
        return count($ids);
    }

    /** @return array{labels:array<int,string>, values:array<int,int>} */
    private function buildChartLast30Days(): array
    {
        $rows  = $this->checkins->chartLast30Days();
        $byDay = [];
        foreach ($rows as $row) {
            $byDay[(string) $row['day']] = (int) $row['total'];
        }

        $labels = [];
        $values = [];
        for ($i = 29; $i >= 0; $i--) {
            $date     = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('d.m', strtotime($date));
            $values[] = $byDay[$date] ?? 0;
        }
        return ['labels' => $labels, 'values' => $values];
    }
}
