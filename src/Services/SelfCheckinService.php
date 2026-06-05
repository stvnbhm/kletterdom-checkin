<?php

declare(strict_types=1);

namespace Kletterdom\Services;

use Kletterdom\Repositories\CheckinRepository;
use Kletterdom\Repositories\RegistrationRepository;

/**
 * Kapselt die Self-Check-in-Regeln (rot/orange/blau/grün +
 * Schnupperlimit) und liefert pro Versuch ein Status-Tripel. Die
 * Logik ist identisch zur Laravel-Variante in App\Services\SelfCheckinService.
 */
final class SelfCheckinService
{
    public const STATUS_SUCCESS            = 'success';
    public const STATUS_ALREADY_CHECKED_IN = 'already_checked_in';
    public const STATUS_NEEDS_STAFF        = 'needs_staff';
    public const STATUS_DENIED             = 'denied';
    public const STATUS_INVALID_QR         = 'invalid_qr';

    public function __construct(
        private readonly CheckinRepository      $checkins,
        private readonly RegistrationRepository $registrations,
    ) {
    }

    /**
     * @param array<string,mixed> $registration
     * @return array{status:string, http_status:int, staff_message:string|null}
     */
    public function attempt(array $registration): array
    {
        $hasActiveKulanz = $this->hasActiveKulanz($registration);

        if ($registration['access_status'] === 'red' && ! $hasActiveKulanz) {
            return [
                'status'        => self::STATUS_DENIED,
                'http_status'   => 403,
                'staff_message' => 'Kein Zutritt erlaubt.',
            ];
        }

        if ($registration['access_status'] === 'orange' && ! $hasActiveKulanz) {
            return [
                'status'        => self::STATUS_NEEDS_STAFF,
                'http_status'   => 403,
                'staff_message' => 'Check-in blockiert – bitte beim Hallendienst melden.',
            ];
        }

        if ($this->checkins->hasOpenForRegistration((int) $registration['id'])) {
            return [
                'status'        => self::STATUS_ALREADY_CHECKED_IN,
                'http_status'   => 422,
                'staff_message' => 'Bereits eingecheckt.',
            ];
        }

        if (! in_array($registration['access_status'], ['green', 'blue'], true)) {
            $status = $registration['access_status'] === 'red'
                ? self::STATUS_DENIED
                : self::STATUS_NEEDS_STAFF;

            return [
                'status'        => $status,
                'http_status'   => 403,
                'staff_message' => $registration['access_status'] === 'red'
                    ? 'Kein Zutritt erlaubt.'
                    : 'Zutritt erfordert manuelle Freigabe durch den Hallendienst.',
            ];
        }

        $this->checkins->create((int) $registration['id'], date('Y-m-d H:i:s'));
        $this->registrations->increment((int) $registration['id'], 'trial_visits_count');

        $reloaded = $this->registrations->find((int) $registration['id']);
        if ($reloaded !== null && $reloaded['member_type'] === 'guest') {
            $this->applyGuestPostCheckinStatus($reloaded);
        }

        return [
            'status'        => self::STATUS_SUCCESS,
            'http_status'   => 200,
            'staff_message' => 'Erfolgreich eingecheckt.',
        ];
    }

    /**
     * @return array{status:string, headline:string, subline:string|null, reset_after_ms:int}
     */
    public function scannerPayload(string $status): array
    {
        return match ($status) {
            self::STATUS_SUCCESS => [
                'status'         => $status,
                'headline'       => 'Erfolgreich eingecheckt',
                'subline'        => 'Willkommen im Kletterdom',
                'reset_after_ms' => 3000,
            ],
            self::STATUS_ALREADY_CHECKED_IN => [
                'status'         => $status,
                'headline'       => 'Bereits eingecheckt',
                'subline'        => null,
                'reset_after_ms' => 5000,
            ],
            self::STATUS_NEEDS_STAFF => [
                'status'         => $status,
                'headline'       => 'Bitte beim Hallendienst melden',
                'subline'        => 'Check-in hier nicht möglich',
                'reset_after_ms' => 5000,
            ],
            self::STATUS_DENIED => [
                'status'         => $status,
                'headline'       => 'Kein Zutritt',
                'subline'        => null,
                'reset_after_ms' => 5000,
            ],
            self::STATUS_INVALID_QR => [
                'status'         => $status,
                'headline'       => 'QR-Code ungültig',
                'subline'        => null,
                'reset_after_ms' => 5000,
            ],
            default => [
                'status'         => 'error',
                'headline'       => 'Technischer Fehler',
                'subline'        => null,
                'reset_after_ms' => 5000,
            ],
        };
    }

    /** @param array<string,mixed> $registration */
    private function hasActiveKulanz(array $registration): bool
    {
        $until = $registration['manual_exception_until'] ?? null;
        if ($until === null || $until === '') {
            return false;
        }
        return strtotime((string) $until) > time();
    }

    /** @param array<string,mixed> $registration */
    private function applyGuestPostCheckinStatus(array $registration): void
    {
        $visits = (int) ($registration['trial_visits_count'] ?? 0);
        if ($visits >= 3) {
            $this->registrations->update((int) $registration['id'], [
                'access_status' => 'red',
                'access_reason' => 'Schnupperlimit ausgeschöpft (3/3)',
            ]);
            return;
        }
        $this->registrations->update((int) $registration['id'], [
            'access_status' => 'orange',
            'access_reason' => 'Schnupperklettern – letzter Besuch am ' . date('d.m.Y H:i') . ' Uhr',
        ]);
    }
}
