<?php

declare(strict_types=1);

namespace Kletterdom\Controllers;

use Kletterdom\Http\Flash;
use Kletterdom\Http\Request;
use Kletterdom\Http\Response;
use Kletterdom\Http\View;
use Kletterdom\Repositories\RegistrationRepository;
use Kletterdom\Services\RegistrationService;
use Kletterdom\Services\StaffService;
use Kletterdom\Support\Url;

final class StaffController
{
    public function __construct(
        private readonly View                   $view,
        private readonly StaffService           $staff,
        private readonly RegistrationService    $registrationService,
        private readonly RegistrationRepository $registrations,
        private readonly Flash                  $flash,
    ) {
    }

    public function index(Request $request): Response
    {
        $data = $this->pageData($request);
        return Response::html($this->view->render('staff/index', $data));
    }

    public function snapshot(Request $request): Response
    {
        $data = $this->pageData($request);
        return Response::json([
            'stats' => $this->view->render('staff/stats', $data),
            'list'  => $this->view->render('staff/list', $data),
        ]);
    }

    public function checkin(Request $request, array $params): Response
    {
        $registration = $this->registrations->find((int) $params['registration']);
        if ($registration === null) {
            return Response::html('<h1>404</h1>', 404);
        }

        $result = $this->registrationService->staffCheckin($registration, $request->string('reason'));
        $this->flash->set($result['ok'] ? 'success' : 'error', $result['message']);

        return Response::redirect($this->staffPath($request));
    }

    public function checkout(Request $request, array $params): Response
    {
        $registration = $this->registrations->find((int) $params['registration']);
        if ($registration === null) {
            return Response::html('<h1>404</h1>', 404);
        }
        $result = $this->registrationService->staffCheckout($registration);
        $this->flash->set($result['ok'] ? 'success' : 'error', $result['message']);
        return Response::redirect($this->staffPath($request));
    }

    public function checkoutAll(Request $request): Response
    {
        $count = $this->staff->checkoutAll();
        $word  = $count === 1 ? 'Person' : 'Personen';
        $this->flash->set('success', "✓ {$count} {$word} ausgecheckt.");
        return Response::redirect($this->staffPath($request));
    }

    public function parentConsent(Request $request, array $params): Response
    {
        $this->staff->confirmParentConsent((int) $params['registration']);
        $this->flash->set('success', 'Einverständniserklärung wurde bestätigt.');
        return Response::redirect($this->staffPath($request));
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
    private function pageData(Request $request): array
    {
        $page  = max(1, $request->integer('page', 1));
        $query = $request->string('q');
        return $this->staff->buildPageData($query !== '' ? $query : null, $page);
    }

    private function staffPath(Request $request): string
    {
        $q = trim($request->string('q'));
        return Url::with('/hallendienst', ['q' => $q !== '' ? $q : null]);
    }
}
