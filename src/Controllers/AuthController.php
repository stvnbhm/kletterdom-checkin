<?php

declare(strict_types=1);

namespace Kletterdom\Controllers;

use Kletterdom\Auth\Auth;
use Kletterdom\Http\Flash;
use Kletterdom\Http\Request;
use Kletterdom\Http\Response;
use Kletterdom\Http\Validator;
use Kletterdom\Http\View;

final class AuthController
{
    public function __construct(
        private readonly View  $view,
        private readonly Auth  $auth,
        private readonly Flash $flash,
    ) {
    }

    public function showLogin(Request $request): Response
    {
        return Response::html($this->view->render('login'));
    }

    public function login(Request $request): Response
    {
        $validator = new Validator($request->post);
        $validator->required('email', 'E-Mail ist erforderlich.')
                  ->email('email', 'Bitte eine gültige E-Mail eingeben.')
                  ->required('password', 'Passwort ist erforderlich.');

        if ($validator->fails()) {
            $this->flash->setErrors($validator->errors());
            $this->flash->withOld(['email' => $request->string('email')]);
            return Response::redirect('/login');
        }

        if (! $this->auth->attempt($request->string('email'), $request->string('password'))) {
            $this->flash->setErrors(['email' => 'Anmeldung fehlgeschlagen.']);
            $this->flash->withOld(['email' => $request->string('email')]);
            return Response::redirect('/login');
        }

        return Response::redirect('/dashboard');
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout();
        return Response::redirect('/');
    }
}
