<?php

declare(strict_types=1);

namespace Kletterdom\Services;

use Kletterdom\Repositories\MemberRepository;
use Kletterdom\Repositories\RegistrationRepository;
use Kletterdom\Support\Csv;
use Kletterdom\Support\PrivacyIndex;

/**
 * CSV-Import für Mitglieder. Erwartet die Spalten:
 *   Mitgliedsnummer; Nachname; Status; Betrag offen; Geburtsdatum
 * Synchronisiert nebenbei die Zugangsstatus auf den Registrierungen.
 */
final class MemberImportService
{
    private const INACTIVE_STATUSES = [
        'gelöscht', 'geloescht',
        'ausgetreten',
        'gesperrt',
        'inaktiv',
        'gekündigt', 'gekuendigt',
    ];

    public function __construct(
        private readonly MemberRepository       $members,
        private readonly RegistrationRepository $registrations,
        private readonly PrivacyIndex           $privacy,
    ) {
    }

    /**
     * @return array{
     *     status: 'ok'|'needs_confirmation'|'error',
     *     imported:int,
     *     skipped:int,
     *     missing:int,
     *     missing_member_numbers:array<int,string>,
     *     message:?string
     * }
     */
    public function import(string $filePath, ?int $confirmMissingCount = null): array
    {
        if (! is_readable($filePath)) {
            return $this->error('CSV konnte nicht geöffnet werden.');
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return $this->error('CSV konnte nicht geöffnet werden.');
        }

        try {
            $delimiter = Csv::detectDelimiter($handle);
            $headers   = Csv::readRow($handle, $delimiter);
            if (! is_array($headers)) {
                return $this->error('CSV konnte nicht gelesen werden.');
            }

            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
            $headers    = array_map(static fn ($h): string => trim((string) $h), $headers);

            if (! in_array('Mitgliedsnummer', $headers, true)) {
                return $this->error('CSV-Format ungültig: Spalte "Mitgliedsnummer" fehlt.');
            }

            $imported              = 0;
            $skipped               = 0;
            $importedMemberNumbers = [];

            while (($row = Csv::readRow($handle, $delimiter)) !== false) {
                if (Csv::isEmptyRow($row)) {
                    continue;
                }
                if (count($row) !== count($headers)) {
                    $skipped++;
                    continue;
                }

                $data         = array_combine($headers, $row);
                $memberNumber = trim((string) ($data['Mitgliedsnummer'] ?? ''));
                if ($memberNumber === '') {
                    continue;
                }
                $importedMemberNumbers[] = $memberNumber;

                $paymentStatus = Csv::parseAmount($data['Betrag offen'] ?? null) > 0 ? 'overdue' : 'paid';
                $birthDate     = Csv::parseGermanDate($data['Geburtsdatum'] ?? null);
                $exitDate      = Csv::parseGermanDate($data['Austrittsdatum'] ?? null);
                $statusRaw     = mb_strtolower(trim((string) ($data['Status'] ?? '')));

                $membershipStatus = 'active';
                if (in_array($statusRaw, self::INACTIVE_STATUSES, true)) {
                    $membershipStatus = 'inactive';
                } elseif ($exitDate !== null && strtotime($exitDate) < time()) {
                    $membershipStatus = 'inactive';
                }

                $hash = $this->privacy->buildNameBirthHash(
                    $data['Nachname'] ?? null,
                    $birthDate,
                );

                $this->members->upsert($memberNumber, $membershipStatus, $paymentStatus, $hash);

                if ($hash !== null) {
                    $this->registrations->upgradeGuestsToMember($hash, $memberNumber);
                }

                $this->registrations->syncPaymentByMemberNumbers([$memberNumber], $paymentStatus);

                if ($membershipStatus === 'inactive') {
                    $this->registrations->syncByMemberNumbers(
                        [$memberNumber],
                        'red',
                        'Mitgliedschaft inaktiv',
                        $paymentStatus,
                    );
                } elseif ($paymentStatus === 'overdue') {
                    $this->registrations->syncByMemberNumbers(
                        [$memberNumber],
                        'orange',
                        'Beitrag offen',
                        $paymentStatus,
                    );
                } else {
                    $this->registrations->syncByMemberNumbers(
                        [$memberNumber],
                        'green',
                        'Mitgliedschaft aktiv & bezahlt',
                        $paymentStatus,
                    );
                }

                $imported++;
            }

            if ($imported === 0) {
                return $this->error('CSV wurde gelesen, aber es konnten keine Datensätze importiert werden.');
            }

            $importedMemberNumbers = array_values(array_unique($importedMemberNumbers));
            $missing               = $this->members->memberNumbersNotIn($importedMemberNumbers);
            $missingCount          = count($missing);

            if ($missingCount > 0 && $confirmMissingCount !== $missingCount) {
                return [
                    'status'                 => 'needs_confirmation',
                    'imported'               => $imported,
                    'skipped'                => $skipped,
                    'missing'                => $missingCount,
                    'missing_member_numbers' => $missing,
                    'message'                => "Import abgebrochen: {$missingCount} bestehende Mitglieder fehlen in der CSV. Bitte Import erneut starten und diese Anzahl explizit bestätigen.",
                ];
            }

            if ($missingCount > 0) {
                $this->members->markInactive($missing);
                $this->registrations->syncByMemberNumbers(
                    $missing,
                    'red',
                    'Mitgliedschaft inaktiv',
                    'overdue',
                );
            }

            return [
                'status'                 => 'ok',
                'imported'               => $imported,
                'skipped'                => $skipped,
                'missing'                => $missingCount,
                'missing_member_numbers' => $missing,
                'message'                => null,
            ];
        } finally {
            fclose($handle);
        }
    }

    /** @return array{status:'error',imported:int,skipped:int,missing:int,missing_member_numbers:array<int,string>,message:string} */
    private function error(string $message): array
    {
        return [
            'status'                 => 'error',
            'imported'               => 0,
            'skipped'                => 0,
            'missing'                => 0,
            'missing_member_numbers' => [],
            'message'                => $message,
        ];
    }
}
