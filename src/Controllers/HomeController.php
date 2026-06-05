<?php

declare(strict_types=1);

namespace Kletterdom\Controllers;

use Kletterdom\Auth\Auth;
use Kletterdom\Http\Request;
use Kletterdom\Http\Response;
use Kletterdom\Http\View;

final class HomeController
{
    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
    ) {
    }

    public function welcome(Request $request): Response
    {
        if ($this->auth->check()) {
            return Response::redirect('/dashboard');
        }
        return Response::html($this->view->render('home'));
    }

    public function dashboard(Request $request): Response
    {
        return Response::redirect($this->auth->isAdmin() ? '/admin' : '/hallendienst');
    }

    public function privacy(Request $request): Response
    {
        return Response::html($this->view->render('privacy'));
    }
}
