<?php

declare(strict_types=1);

namespace Kletterdom\Services;

use Kletterdom\Repositories\CheckinRepository;
use Kletterdom\Repositories\RegistrationRepository;

/**
 * Aufbereitete Daten für die Hallendienst-Seite. Wird sowohl vom
 * Vollrender als auch vom JSON-Snapshot genutzt — eine Wahrheit.
 */
final class StaffService
{
    public function __construct(
        private readonly RegistrationRepository $registrations,
        private readonly CheckinRepository      $checkins,
    ) {
    }

    /**
     * @return array{
     *     query:?string,
     *     stats:array<string,int>,
     *     registrations:array<int,array<string,mixed>>,
     *     pastCheckinDates:array<int,string>,
     *     pagination:array{page:int,per_page:int,total:int,pages:int}
     * }
     */
    public function buildPageData(?string $query, int $page = 1, int $perPage = 50): array
    {
        $query = $query !== null && trim($query) !== '' ? trim($query) : null;

        $listing = $this->registrations->listForStaff($query, $page, $perPage);
        $rows    = $listing['rows'];

        $guestRegistrationIds = array_column(
            array_filter(
                $rows,
                static fn (array $r): bool => $r['member_type'] === 'guest' && (int) ($r['trial_visits_count'] ?? 0) > 0,
            ),
            'id',
        );

        $pastCheckinDates = $this->checkins->pastCheckinDates(array_map('intval', $guestRegistrationIds));

        $stats = [
            'checkedInToday'     => $this->checkins->countToday(),
            'guestsToday'        => $this->checkins->countTodayByMemberType('guest'),
            'membersToday'       => $this->checkins->countTodayByMemberType('member'),
            'totalRegistrations' => $this->registrations->count(),
        ];

        $pages = (int) max(1, (int) ceil($listing['total'] / $perPage));

        return [
            'query'            => $query,
            'stats'            => $stats,
            'registrations'    => $rows,
            'pastCheckinDates' => $pastCheckinDates,
            'pagination'       => [
                'page'     => $page,
                'per_page' => $perPage,
                'total'    => $listing['total'],
                'pages'    => $pages,
            ],
        ];
    }

    public function checkoutAll(): int
    {
        $now    = date('Y-m-d H:i:s');
        $open   = $this->checkins->openCheckins();
        $closed = 0;

        foreach ($open as $checkin) {
            $this->checkins->closeById((int) $checkin['id'], $now);

            $registration = $this->registrations->find((int) $checkin['registration_id']);
            if ($registration === null) {
                continue;
            }
            $this->registrations->update((int) $registration['id'], ['checked_in_at' => null]);

            $visits = (int) ($registration['trial_visits_count'] ?? 0);
            if ($registration['member_type'] === 'guest' && $visits >= 3) {
                $this->registrations->update((int) $registration['id'], [
                    'access_status' => 'red',
                    'access_reason' => 'Schnupperlimit ausgeschöpft (3/3)',
                ]);
            } elseif ($registration['member_type'] === 'guest' && $visits >= 1) {
                $this->registrations->update((int) $registration['id'], [
                    'access_reason' => 'Schnupperklettern bereits absolviert am ' . date('d.m.Y', strtotime((string) $checkin['checked_in_at'])),
                ]);
            }

            $closed++;
        }

        return $closed;
    }

    public function confirmParentConsent(int $registrationId): void
    {
        $this->registrations->update($registrationId, [
            'parent_consent_received'    => 1,
            'parent_consent_received_at' => date('Y-m-d H:i:s'),
            'needs_parent_consent'       => 0,
        ]);
    }
}
