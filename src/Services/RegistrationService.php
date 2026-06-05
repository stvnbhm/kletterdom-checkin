<?php

declare(strict_types=1);

namespace Kletterdom\Services;

use DateTimeImmutable;
use Kletterdom\Repositories\CheckinRepository;
use Kletterdom\Repositories\MemberRepository;
use Kletterdom\Repositories\RegistrationRepository;
use Kletterdom\Support\PrivacyIndex;

/**
 * Bestimmt Zugangsstatus + Persistierung für eine Registrierung.
 * Identische Geschäftsregeln wie der Laravel-RegistrationController,
 * aber rein als reine Funktionen über das Repository.
 */
final class RegistrationService
{
    public function __construct(
        private readonly RegistrationRepository $registrations,
        private readonly MemberRepository       $members,
        private readonly PrivacyIndex           $privacy,
        private readonly CheckinRepository      $checkins,
    ) {
    }

    /**
     * @param array{first_name:string,last_name:string,birth_date:string,email:string,address:?string,
     *              member_type:string,member_number:?string,supervision_confirmed:bool} $input
     * @return array{registration:array<string,mixed>, fresh:bool, member_mismatch:bool,
     *               existing_block:?string}
     */
    public function register(array $input): array
    {
        $birthDate = new DateTimeImmutable($input['birth_date']);
        $age       = (int) $birthDate->diff(new DateTimeImmutable('today'))->y;

        $needsSupervision   = $age < 14;
        $needsParentConsent = $age >= 14 && $age < 18;

        $nameBirthHash = $this->privacy->buildNameBirthHash($input['last_name'], $input['birth_date']);

        $member      = null;
        $mismatch    = false;
        if ($input['member_type'] === 'member') {
            $member = $this->members->findByNumber((string) $input['member_number']);

            if ($member !== null) {
                if (($member['name_birth_hash'] ?? null) !== $nameBirthHash) {
                    return ['registration' => [], 'fresh' => false, 'member_mismatch' => true, 'existing_block' => null];
                }
            } elseif ($nameBirthHash !== null && $this->members->existsByNameBirthHash($nameBirthHash)) {
                return ['registration' => [], 'fresh' => false, 'member_mismatch' => true, 'existing_block' => null];
            }
        }

        $existing = $this->findExisting($input, $nameBirthHash);

        $access = $this->determineAccessStatus($input, $member, $existing, $needsSupervision, $needsParentConsent);

        if ($existing !== null) {
            $isUpgrade = $existing['member_type'] === 'guest' && $input['member_type'] === 'member';
            if (! $isUpgrade) {
                if ($existing['member_type'] === 'guest') {
                    $visits = (int) ($existing['trial_visits_count'] ?? 0);
                    if ($visits >= 3) {
                        return ['registration' => $existing, 'fresh' => false, 'member_mismatch' => false,
                                'existing_block' => 'Du hast das Schnupper-Limit bereits vollständig ausgeschöpft. Eine weitere Registrierung als Gast ist nicht möglich.'];
                    }
                    if ($visits >= 1) {
                        return ['registration' => $existing, 'fresh' => false, 'member_mismatch' => false,
                                'existing_block' => 'Du bist bereits als Schnuppergast registriert. Ein zweites Mal ist nur nach Absprache mit dem Hallendienst möglich.'];
                    }
                } else {
                    return ['registration' => $existing, 'fresh' => false, 'member_mismatch' => false, 'existing_block' => null];
                }
            }

            $this->registrations->update((int) $existing['id'], [
                'member_type'                => $input['member_type'],
                'member_number'              => $input['member_number'] ?: null,
                'waiver_accepted'            => 1,
                'birth_date'                 => $input['birth_date'],
                'email'                      => $input['email'],
                'address'                    => $input['member_type'] === 'guest' ? $input['address'] : null,
                'access_status'              => $access['status'],
                'access_reason'              => $access['reason'],
                'payment_status'             => $access['payment_status'],
                'needs_supervision'          => $needsSupervision ? 1 : 0,
                'needs_parent_consent'       => $needsParentConsent ? 1 : 0,
                'parent_consent_received'    => $needsParentConsent ? (int) ($existing['parent_consent_received'] ?? 0) : 0,
                'parent_consent_received_at' => $needsParentConsent ? ($existing['parent_consent_received_at'] ?? null) : null,
                'supervision_confirmed'      => ($needsSupervision && $input['supervision_confirmed']) ? 1 : 0,
            ]);

            $registration = $this->registrations->find((int) $existing['id']);
            return ['registration' => $registration ?? $existing, 'fresh' => false, 'member_mismatch' => false, 'existing_block' => null];
        }

        $id = $this->registrations->create([
            'first_name'                 => $input['first_name'],
            'last_name'                  => $input['last_name'],
            'birth_date'                 => $input['birth_date'],
            'email'                      => $input['email'],
            'address'                    => $input['member_type'] === 'guest' ? $input['address'] : null,
            'member_type'                => $input['member_type'],
            'member_number'              => $input['member_number'] ?: null,
            'waiver_accepted'            => 1,
            'waiver_version'             => 'v1',
            'payment_status'             => $access['payment_status'],
            'access_status'              => $access['status'],
            'access_reason'              => $access['reason'],
            'trial_visits_count'         => 0,
            'needs_supervision'          => $needsSupervision ? 1 : 0,
            'needs_parent_consent'       => $needsParentConsent ? 1 : 0,
            'parent_consent_received'    => 0,
            'parent_consent_received_at' => null,
            'supervision_confirmed'      => ($needsSupervision && $input['supervision_confirmed']) ? 1 : 0,
            'qr_token'                   => $this->uuid(),
            'name_birth_hash'            => $nameBirthHash,
        ]);

        $registration = $this->registrations->find($id);
        return ['registration' => $registration ?? [], 'fresh' => true, 'member_mismatch' => false, 'existing_block' => null];
    }

    /** @param array<string,mixed> $registration */
    public function staffCheckin(array $registration, ?string $reason): array
    {
        $regId  = (int) $registration['id'];
        $visits = (int) ($registration['trial_visits_count'] ?? 0);

        if ($this->registrations->currentCheckin($regId) !== null) {
            return ['ok' => false, 'message' => $registration['first_name'] . ' ' . $registration['last_name'] . ' ist bereits eingecheckt.'];
        }

        if ($registration['access_status'] === 'red') {
            return ['ok' => false, 'message' => 'Check-in verweigert – ' . $registration['first_name'] . ' hat keinen Zutritt (Status: rot).'];
        }

        if ($registration['member_type'] === 'guest' && $visits >= 3) {
            return ['ok' => false, 'message' => 'Check-in verweigert – Schnupperlimit (3 Besuche) ausgeschöpft.'];
        }

        $member            = $registration['member_number'] !== null
            ? $this->members->findByNumber((string) $registration['member_number'])
            : null;
        $isUnverifiedMember = $registration['member_type'] === 'member' && $member === null;

        $requiresModal = $registration['access_status'] === 'orange'
            || ($registration['member_type'] === 'guest' && $visits >= 1);

        $reason = $reason !== null ? trim(strip_tags($reason)) : '';
        if ($requiresModal && $reason === '') {
            return ['ok' => false, 'message' => 'Check-in verweigert – Bestätigung mit Grund erforderlich.'];
        }

        if ($isUnverifiedMember) {
            $totalCheckins = $this->checkinsCount($regId);
            if ($totalCheckins >= 3) {
                $this->registrations->update($regId, [
                    'access_status' => 'red',
                    'access_reason' => 'Mitgliedsnummer nicht im System – Limit erreicht',
                ]);
                return ['ok' => false, 'message' => 'Check-in verweigert – Mitgliedsnummer nicht gefunden. Limit von 3 Besuchen ausgeschöpft.'];
            }
        }

        if ($reason !== '') {
            $this->registrations->update($regId, ['manual_exception_reason' => $reason]);
        }

        $this->openCheckin($regId);
        $this->registrations->increment($regId, 'trial_visits_count');

        $reloaded = $this->registrations->find($regId);
        if ($reloaded !== null && $reloaded['member_type'] === 'guest') {
            $newVisits = (int) ($reloaded['trial_visits_count'] ?? 0);
            if ($newVisits >= 3) {
                $this->registrations->update($regId, [
                    'access_status' => 'red',
                    'access_reason' => 'Schnupperlimit ausgeschöpft (3/3)',
                ]);
            } else {
                $this->registrations->update($regId, [
                    'access_status' => 'orange',
                    'access_reason' => 'Schnupperklettern bereits absolviert am ' . date('d.m.Y'),
                ]);
            }
        }

        if ($isUnverifiedMember && $this->checkinsCount($regId) >= 3) {
            $this->registrations->update($regId, [
                'access_status' => 'red',
                'access_reason' => 'Mitgliedsnummer nicht im System – Limit erreicht',
            ]);
        }

        return ['ok' => true, 'message' => '✓ ' . $registration['first_name'] . ' ' . $registration['last_name'] . ' eingecheckt.'];
    }

    /** @param array<string,mixed> $registration */
    public function staffCheckout(array $registration): array
    {
        $regId   = (int) $registration['id'];
        $closed  = $this->closeOpenCheckin($regId);

        if ($closed === null) {
            return ['ok' => false, 'message' => "Kein offener Check-in für {$registration['first_name']} {$registration['last_name']} gefunden."];
        }

        $this->registrations->update($regId, ['checked_in_at' => null]);

        if ($registration['member_type'] === 'guest') {
            $visits = (int) ($registration['trial_visits_count'] ?? 0);
            if ($visits >= 3) {
                $this->registrations->update($regId, [
                    'access_status' => 'red',
                    'access_reason' => 'Schnupperlimit ausgeschöpft (3/3)',
                ]);
            } elseif ($visits >= 1) {
                $this->registrations->update($regId, [
                    'access_reason' => 'Schnupperklettern bereits absolviert am ' . date('d.m.Y', strtotime((string) $closed['checked_in_at'])),
                ]);
            }
        }

        $member = $registration['member_number'] !== null
            ? $this->members->findByNumber((string) $registration['member_number'])
            : null;
        if ($registration['member_type'] === 'member' && $member === null
            && (int) ($registration['trial_visits_count'] ?? 0) >= 3) {
            $this->registrations->update($regId, [
                'access_status' => 'red',
                'access_reason' => 'Mitgliedsnummer nicht im System (Limit erreicht)',
            ]);
        }

        return ['ok' => true, 'message' => "{$registration['first_name']} {$registration['last_name']} ausgecheckt."];
    }

    /**
     * @param array<string,mixed>      $input
     * @param ?array<string,mixed>     $member
     * @param ?array<string,mixed>     $existing
     * @return array{status:string, reason:?string, payment_status:string}
     */
    private function determineAccessStatus(array $input, ?array $member, ?array $existing, bool $needsSupervision, bool $needsParentConsent): array
    {
        $accessStatus  = 'red';
        $accessReason  = null;
        $paymentStatus = 'paid';

        if ($input['member_type'] === 'guest') {
            $existingVisits = (int) ($existing['trial_visits_count'] ?? 0);
            if ($existingVisits >= 3) {
                $accessStatus = 'red';
                $accessReason = 'Schnupperlimit ausgeschöpft (3/3)';
            } elseif ($existingVisits >= 1) {
                $accessStatus = 'blue';
                $accessReason = 'Schnupperklettern: Besuch ' . ($existingVisits + 1) . ' von 3';
            } else {
                $accessStatus = 'blue';
                $accessReason = null;
            }
        } elseif ($input['member_type'] === 'member') {
            if ($member === null) {
                $accessStatus  = 'orange';
                $accessReason  = 'Mitglied noch unbestätigt / nicht in Datenbank';
                $paymentStatus = 'overdue';
            } elseif (($member['membership_status'] ?? null) !== 'active') {
                $accessStatus  = 'red';
                $accessReason  = 'Mitgliedschaft inaktiv';
                $paymentStatus = 'overdue';
            } elseif (($member['payment_status'] ?? null) === 'overdue') {
                $accessStatus  = 'orange';
                $accessReason  = 'Beitrag offen';
                $paymentStatus = 'overdue';
            } else {
                $accessStatus = 'green';
                $accessReason = null;
            }
        }

        if ($needsSupervision && $input['member_type'] === 'member' && $accessStatus !== 'red'
            && ! $input['supervision_confirmed']) {
            $accessStatus = 'orange';
        }

        if ($needsParentConsent) {
            $note         = 'Jugendlicher (14–17)';
            $accessReason = $accessReason !== null && $accessReason !== '' ? $accessReason . ' · ' . $note : $note;
        }

        return ['status' => $accessStatus, 'reason' => $accessReason, 'payment_status' => $paymentStatus];
    }

    /** @param array<string,mixed> $input */
    private function findExisting(array $input, ?string $nameBirthHash): ?array
    {
        if ($input['member_type'] === 'member' && ($input['member_number'] ?? '') !== '') {
            $existing = $this->registrations->findByMemberNumber((string) $input['member_number']);
            if ($existing !== null) {
                return $existing;
            }
        }

        if ($nameBirthHash !== null) {
            $existing = $this->registrations->findByNameBirthHash($nameBirthHash);
            if ($existing !== null) {
                return $existing;
            }
            if ($input['member_type'] === 'member') {
                return $this->registrations->findGuestByNameBirthHash($nameBirthHash);
            }
        }

        return null;
    }

    private function openCheckin(int $registrationId): void
    {
        $this->checkins->create($registrationId, date('Y-m-d H:i:s'));
    }

    private function closeOpenCheckin(int $registrationId): ?array
    {
        return $this->checkins->closeOpen($registrationId, date('Y-m-d H:i:s'));
    }

    private function checkinsCount(int $registrationId): int
    {
        return $this->checkins->countForRegistration($registrationId);
    }

    private function uuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
