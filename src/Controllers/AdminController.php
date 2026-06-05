<?php

declare(strict_types=1);

namespace Kletterdom\Controllers;

use Kletterdom\Http\Flash;
use Kletterdom\Http\Request;
use Kletterdom\Http\Response;
use Kletterdom\Http\View;
use Kletterdom\Repositories\CheckinRepository;
use Kletterdom\Repositories\RegistrationRepository;
use Kletterdom\Services\AdminService;
use Kletterdom\Services\MemberImportService;
use Kletterdom\Support\Csv;
use Kletterdom\Support\Url;

final class AdminController
{
    public function __construct(
        private readonly View                   $view,
        private readonly AdminService           $admin,
        private readonly MemberImportService    $import,
        private readonly RegistrationRepository $registrations,
        private readonly CheckinRepository      $checkins,
        private readonly Flash                  $flash,
    ) {
    }

    public function index(Request $request): Response
    {
        $data = $this->admin->dashboard(
            $request->string('q', '') ?: null,
            $request->string('status', '') ?: null,
            max(1, $request->integer('page', 1)),
        );
        $data['query']        = $request->string('q');
        $data['statusFilter'] = $request->string('status');
        return Response::html($this->view->render('admin/index', $data));
    }

    public function destroyRegistration(Request $request, array $params): Response
    {
        $this->admin->deleteRegistration((int) $params['registration']);
        $this->flash->set('success', 'Registrierung wurde gelöscht.');
        return Response::redirect($this->adminPath($request));
    }

    public function updateNotes(Request $request, array $params): Response
    {
        $this->admin->updateNotes((int) $params['registration'], $request->string('notes'));
        $this->flash->set('success', 'Notiz gespeichert.');
        return Response::redirect($this->adminPath($request));
    }

    public function importMembers(Request $request): Response
    {
        if (! isset($request->files['members_csv']) || ! is_array($request->files['members_csv'])) {
            $this->flash->set('error', 'Bitte CSV-Datei auswählen.');
            return Response::redirect('/admin');
        }

        $file = $request->files['members_csv'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->flash->set('error', 'Bitte CSV-Datei auswählen.');
            return Response::redirect('/admin');
        }
        if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            $this->flash->set('error', 'CSV ist zu groß (max. 5 MB).');
            return Response::redirect('/admin');
        }

        $tmpPath = (string) $file['tmp_name'];
        if (! is_uploaded_file($tmpPath)) {
            $this->flash->set('error', 'Ungültiger Upload.');
            return Response::redirect('/admin');
        }

        $confirm = $request->input('confirm_missing_count');
        $confirm = is_numeric($confirm) ? (int) $confirm : null;

        $result = $this->import->import($tmpPath, $confirm);

        if ($result['status'] === 'needs_confirmation') {
            $this->flash->set('warning', $result['message']);
            $this->flash->set('confirm_missing_count_required', $result['missing']);
            return Response::redirect('/admin');
        }

        if ($result['status'] === 'error') {
            $this->flash->set('error', $result['message'] ?? 'Import fehlgeschlagen.');
            return Response::redirect('/admin');
        }

        $msg = "Mitgliederimport abgeschlossen: {$result['imported']} Datensätze verarbeitet.";
        if (($result['skipped'] ?? 0) > 0) {
            $msg .= " ({$result['skipped']} Zeile(n) mit falscher Spaltenanzahl übersprungen)";
        }
        $this->flash->set('success', $msg);
        return Response::redirect('/admin');
    }

    public function deleteInactiveMembers(Request $request): Response
    {
        $count = $this->admin->deleteInactiveMembers();
        $this->flash->set('success', $count . ' inaktive Mitglieder gelöscht.');
        return Response::redirect('/admin');
    }

    public function deleteStaleRegistrations(Request $request): Response
    {
        $count = $this->admin->deleteStaleRegistrations();
        $this->flash->set('success', $count . ' Registrierung(en) gelöscht (kein Check-in seit 2 Jahren).');
        return Response::redirect('/admin');
    }

    public function exportCheckins(Request $request): Response
    {
        $from = $request->string('from', '') !== ''
            ? $request->string('from') . ' 00:00:00'
            : date('Y-m-d 00:00:00', strtotime('-30 days'));
        $to   = $request->string('to', '') !== ''
            ? $request->string('to') . ' 23:59:59'
            : date('Y-m-d 23:59:59');

        $rows = $this->checkins->exportBetween($from, $to);

        $filename = 'checkins_' . date('Ymd', strtotime($from)) . '_' . date('Ymd', strtotime($to)) . '.csv';
        $body     = $this->buildCheckinCsv($rows);

        return new Response($body, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportRegistrations(Request $request): Response
    {
        $rows         = $this->registrations->allForExport();
        $lastCheckins = $this->checkins->lastCheckins();

        $filename = 'registrierungen_' . date('Ymd_His') . '.csv';
        $body     = $this->buildRegistrationsCsv($rows, $lastCheckins);

        return new Response($body, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function buildCheckinCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");

        Csv::writeRow($handle, [
            'Check-in ID', 'Vorname', 'Nachname', 'Email', 'Adresse', 'Geburtsdatum',
            'Mitgliedstyp', 'Mitgliedsnummer', 'Check-in Zeit', 'Ampelstatus',
            'Zugangsstatus-Grund', 'Schnupperbesuche', 'Aufsicht erforderlich',
        ]);

        foreach ($rows as $r) {
            Csv::writeRow($handle, [
                $r['id'],
                Csv::sanitizeCell((string) ($r['first_name'] ?? '')),
                Csv::sanitizeCell((string) ($r['last_name']  ?? '')),
                Csv::sanitizeCell((string) ($r['email']      ?? '')),
                Csv::sanitizeCell((string) ($r['address']    ?? '')),
                $r['birth_date'] ? date('d.m.Y', strtotime((string) $r['birth_date'])) : '',
                $r['member_type'] === 'member' ? 'Mitglied' : 'Gast',
                Csv::sanitizeCell((string) ($r['member_number'] ?? '')),
                date('d.m.Y H:i', strtotime((string) $r['checked_in_at'])),
                (string) ($r['access_status'] ?? ''),
                Csv::sanitizeCell((string) ($r['access_reason'] ?? '')),
                $r['member_type'] === 'guest' ? (int) ($r['trial_visits_count'] ?? 0) : '',
                ((int) ($r['needs_supervision'] ?? 0) === 1) ? 'Ja' : 'Nein',
            ]);
        }

        rewind($handle);
        $body = (string) stream_get_contents($handle);
        fclose($handle);
        return $body;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string>              $lastCheckins
     */
    private function buildRegistrationsCsv(array $rows, array $lastCheckins): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");

        Csv::writeRow($handle, [
            'Vorname', 'Nachname', 'Geburtsdatum', 'Email', 'Adresse',
            'Mitgliedstyp', 'Mitgliedsnummer', 'Ampelstatus', 'Zugangsstatus-Grund',
            'Zahlungsstatus', 'Schnupperbesuche', 'Aufsicht erforderlich',
            'Aufsicht bestätigt', 'Elternzustimmung nötig', 'Elternzustimmung erhalten am',
            'Manuelle Ausnahme Grund', 'Manuelle Ausnahme bis', 'Waiver akzeptiert',
            'Waiver-Version', 'QR-Token', 'QR-Link', 'Anzahl Check-ins',
            'Letzter Check-in', 'Notiz', 'Registriert am',
        ]);

        foreach ($rows as $reg) {
            $regId       = (int) $reg['id'];
            $lastRaw     = $lastCheckins[$regId] ?? null;
            $lastCheckin = $lastRaw ? date('d.m.Y H:i', strtotime($lastRaw)) : '';

            fputcsv($handle, [
                Csv::sanitizeCell((string) ($reg['first_name'] ?? '')),
                Csv::sanitizeCell((string) ($reg['last_name'] ?? '')),
                $reg['birth_date'] ? date('d.m.Y', strtotime((string) $reg['birth_date'])) : '',
                Csv::sanitizeCell((string) ($reg['email'] ?? '')),
                Csv::sanitizeCell((string) ($reg['address'] ?? '')),
                $reg['member_type'] === 'member' ? 'Mitglied' : 'Gast',
                Csv::sanitizeCell((string) ($reg['member_number'] ?? '')),
                (string) ($reg['access_status'] ?? ''),
                Csv::sanitizeCell((string) ($reg['access_reason'] ?? '')),
                ($reg['payment_status'] ?? '') === 'overdue' ? 'Offen' : 'Bezahlt',
                $reg['member_type'] === 'guest' ? (int) ($reg['trial_visits_count'] ?? 0) : '',
                ((int) ($reg['needs_supervision'] ?? 0) === 1)       ? 'Ja' : 'Nein',
                ((int) ($reg['supervision_confirmed'] ?? 0) === 1)   ? 'Ja' : 'Nein',
                ((int) ($reg['needs_parent_consent'] ?? 0) === 1)    ? 'Ja' : 'Nein',
                $reg['parent_consent_received_at']
                    ? date('d.m.Y H:i', strtotime((string) $reg['parent_consent_received_at']))
                    : '',
                Csv::sanitizeCell((string) ($reg['manual_exception_reason'] ?? '')),
                $reg['manual_exception_until']
                    ? date('d.m.Y', strtotime((string) $reg['manual_exception_until']))
                    : '',
                ((int) ($reg['waiver_accepted'] ?? 0) === 1) ? 'Ja' : 'Nein',
                (string) ($reg['waiver_version'] ?? ''),
                (string) ($reg['qr_token']       ?? ''),
                $reg['qr_token'] ? Url::to('verify/' . $reg['qr_token']) : '',
                (int) ($reg['checkins_count'] ?? 0),
                $lastCheckin,
                Csv::sanitizeCell((string) ($reg['notes'] ?? '')),
                $reg['created_at'] ? date('d.m.Y H:i', strtotime((string) $reg['created_at'])) : '',
            ]);
        }

        rewind($handle);
        $body = (string) stream_get_contents($handle);
        fclose($handle);
        return $body;
    }

    private function adminPath(Request $request): string
    {
        return Url::with('/admin', [
            'q'      => $request->string('q') !== '' ? $request->string('q') : null,
            'status' => $request->string('status') !== '' ? $request->string('status') : null,
            'page'   => $request->integer('page', 1) > 1 ? $request->integer('page') : null,
        ]);
    }
}
