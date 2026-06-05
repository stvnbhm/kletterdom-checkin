<?php

declare(strict_types=1);

namespace Kletterdom\Controllers;

use Kletterdom\Http\Flash;
use Kletterdom\Http\Request;
use Kletterdom\Http\Response;
use Kletterdom\Http\Session;
use Kletterdom\Http\Validator;
use Kletterdom\Http\View;
use Kletterdom\Repositories\RegistrationRepository;
use Kletterdom\Services\RegistrationService;
use Kletterdom\Services\SelfCheckinService;
use Kletterdom\Support\Qr;
use Kletterdom\Support\Url;

final class RegistrationController
{
    public function __construct(
        private readonly View                   $view,
        private readonly RegistrationService    $service,
        private readonly RegistrationRepository $registrations,
        private readonly SelfCheckinService     $selfCheckin,
        private readonly Session                $session,
        private readonly Flash                  $flash,
        private readonly Qr                     $qr,
    ) {
    }

    public function showForm(Request $request): Response
    {
        $this->session->set('register_form_started_at', time());
        return Response::html($this->view->render('register'));
    }

    public function store(Request $request): Response
    {
        if ($request->string('website') !== '' || $request->string('fax_number') !== '') {
            error_log('Spam blockiert: Honeypot-Feld ausgefüllt');
            return $this->backWithError(['first_name' => 'Die Registrierung konnte nicht verarbeitet werden. Bitte versuche es erneut.']);
        }

        $startedAt    = (int) $this->session->get('register_form_started_at', 0);
        $secondsTaken = time() - $startedAt;
        if ($startedAt > 0 && $secondsTaken < 3) {
            error_log('Spam blockiert: Formular zu schnell abgesendet');
            return $this->backWithError(['first_name' => 'Die Registrierung konnte nicht verarbeitet werden. Bitte versuche es erneut.']);
        }

        $validator = $this->validate($request);
        if ($validator->fails()) {
            return $this->backWithError($validator->errors(), $request->post);
        }

        $age = $this->ageFromBirth($request->string('birth_date'));
        if ($age < 14 && ! $request->boolean('supervision_confirmed')) {
            return $this->backWithError([
                'supervision_confirmed' => 'Für Kinder unter 14 Jahren muss bestätigt werden, dass Klettern nur unter Aufsicht erfolgt.',
            ], $request->post);
        }

        $result = $this->service->register([
            'first_name'             => strip_tags($request->string('first_name')),
            'last_name'              => strip_tags($request->string('last_name')),
            'birth_date'             => $request->string('birth_date'),
            'email'                  => $request->string('email'),
            'address'                => strip_tags($request->string('address')),
            'member_type'            => $request->string('member_type', 'guest'),
            'member_number'          => strip_tags($request->string('member_number')),
            'supervision_confirmed'  => $request->boolean('supervision_confirmed'),
        ]);

        if ($result['member_mismatch']) {
            return $this->backWithError([
                'member_number' => 'Die Mitgliedsnummer stimmt nicht mit den angegebenen Daten (Nachname + Geburtsdatum) überein. Bitte prüfen!',
            ], $request->post);
        }

        if ($result['existing_block'] !== null) {
            return $this->backWithError(['first_name' => $result['existing_block']], $request->post);
        }

        $token = (string) $result['registration']['qr_token'];
        $this->flash->set('success', 'Registrierung erfolgreich!');
        return Response::redirect('/verify/' . $token);
    }

    public function verify(Request $request, array $params): Response
    {
        $registration = $this->registrations->findByToken($params['token']);
        if ($registration === null) {
            return Response::html('<h1>404 — Registrierung nicht gefunden</h1>', 404);
        }
        $registration['current_checkin'] = $this->registrations->currentCheckin((int) $registration['id']);

        return Response::html($this->view->render('verify', [
            'registration' => $registration,
            'qrDataUri'    => $this->qr->dataUri(Url::to('verify/' . $registration['qr_token'])),
        ]));
    }

    public function selfCheckinScan(Request $request): Response
    {
        $validator = new Validator($request->post);
        $validator->required('token', 'Token fehlt.')
                  ->maxLength('token', 64, 'Token zu lang.');
        if ($validator->fails()) {
            return Response::json($this->selfCheckin->scannerPayload(SelfCheckinService::STATUS_INVALID_QR), 404);
        }

        $token        = trim($request->string('token'));
        $registration = $this->registrations->findByToken($token);
        if ($registration === null) {
            return Response::json($this->selfCheckin->scannerPayload(SelfCheckinService::STATUS_INVALID_QR), 404);
        }

        try {
            $result = $this->selfCheckin->attempt($registration);
        } catch (\Throwable $e) {
            error_log('SelfCheckin attempt failed: ' . $e->getMessage());
            return Response::json($this->selfCheckin->scannerPayload('error'), 500);
        }

        return Response::json($this->selfCheckin->scannerPayload($result['status']), $result['http_status']);
    }

    public function staffCheckinFromVerify(Request $request, array $params): Response
    {
        $registration = $this->registrations->findByToken($params['token']);
        if ($registration === null) {
            $msg = 'QR-Code ungültig oder abgelaufen.';
            return $request->expectsJson()
                ? Response::json(['success' => false, 'message' => $msg], 404)
                : Response::html('<h1>404</h1><p>' . htmlspecialchars($msg, ENT_QUOTES) . '</p>', 404);
        }

        $result = $this->selfCheckin->attempt($registration);

        if ($result['status'] !== SelfCheckinService::STATUS_SUCCESS) {
            $message = $this->staffCheckinFailMessage($registration, $result);

            if ($request->expectsJson()) {
                return Response::json(['success' => false, 'message' => $message], $result['http_status']);
            }

            $this->flash->setErrors(['checkin' => $message]);
            return Response::redirect('/verify/' . $registration['qr_token']);
        }

        $message = $registration['first_name'] . ' ' . $registration['last_name'] . ' wurde erfolgreich eingecheckt.';
        if ($request->expectsJson()) {
            return Response::json(['success' => true, 'message' => $message]);
        }

        $this->flash->set('success', $message);
        return Response::redirect('/verify/' . $registration['qr_token']);
    }

    private function staffCheckinFailMessage(array $registration, array $result): string
    {
        if ($result['status'] === SelfCheckinService::STATUS_ALREADY_CHECKED_IN) {
            $checkin = $this->registrations->currentCheckin((int) $registration['id']);
            if ($checkin !== null) {
                return $registration['first_name'] . ' ' . $registration['last_name']
                    . ' ist bereits seit ' . date('H:i', strtotime((string) $checkin['checked_in_at']))
                    . ' Uhr eingecheckt.';
            }
        }
        if ($result['status'] === SelfCheckinService::STATUS_NEEDS_STAFF
            && $registration['access_status'] === 'orange') {
            return 'Check-in blockiert! Status ist ORANGE. Kulanz erforderlich: '
                . ($registration['access_reason'] ?? 'Unbekannt') . '.';
        }
        return $result['staff_message'] ?? 'Check-in nicht möglich.';
    }

    public function selfCheckin(Request $request): Response
    {
        return Response::html($this->view->render('self-checkin'));
    }

    private function validate(Request $request): Validator
    {
        $validator = new Validator($request->post);
        $validator->required('first_name', 'Vorname ist erforderlich.')
                  ->maxLength('first_name', 255, 'Vorname zu lang.')
                  ->required('last_name', 'Nachname ist erforderlich.')
                  ->maxLength('last_name', 255, 'Nachname zu lang.')
                  ->required('birth_date', 'Das Geburtsdatum ist erforderlich, um doppelte Registrierungen zu vermeiden.')
                  ->dateBetween('birth_date', '1900-01-01', date('Y-m-d'), 'Geburtsdatum ungültig.')
                  ->required('email', 'E-Mail ist erforderlich.')
                  ->email('email', 'Bitte eine gültige E-Mail eingeben.')
                  ->maxLength('email', 255, 'E-Mail zu lang.')
                  ->required('member_type', 'Bitte Mitglied oder Gast wählen.')
                  ->in('member_type', ['member', 'guest'], 'Ungültiger Typ.')
                  ->accepted('waiver_accepted', 'Haftungsausschluss muss akzeptiert werden.')
                  ->accepted('rules_accepted', 'Hallenordnung muss akzeptiert werden.')
                  ->required('hp_time', 'Formular-Zeit fehlt.')
                  ->empty('website', 'Spam blockiert.')
                  ->empty('fax_number', 'Spam blockiert.');

        $type = $request->string('member_type');
        if ($type === 'member') {
            $validator->required('member_number', 'Bitte Mitgliedsnummer angeben.')
                      ->regex('member_number', '/^\d{2}-\d{5}$/', 'Die Mitgliedsnummer muss im Format XX-XXXXX eingegeben werden (z.B. 12-34567).');
        } elseif ($type === 'guest') {
            $validator->required('address', 'Bitte Adresse angeben.')
                      ->maxLength('address', 500, 'Adresse zu lang.');
        }

        return $validator;
    }

    private function backWithError(array $errors, array $old = []): Response
    {
        $this->flash->setErrors($errors);
        if ($old !== []) {
            $this->flash->withOld($old);
        }
        return Response::redirect('/halle-register');
    }

    private function ageFromBirth(string $birthDate): int
    {
        try {
            $birth = new \DateTimeImmutable($birthDate);
            return (int) $birth->diff(new \DateTimeImmutable('today'))->y;
        } catch (\Throwable) {
            return 0;
        }
    }
}
