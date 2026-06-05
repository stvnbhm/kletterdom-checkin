<?php

declare(strict_types=1);

namespace Kletterdom\Middleware;

use Kletterdom\Auth\Auth;
use Kletterdom\Http\Request;
use Kletterdom\Http\Response;

final class AuthMiddleware
{
    public function __construct(private readonly Auth $auth)
    {
    }

    /** @param array<string,string> $params */
    public function handle(Request $request, array $params): ?Response
    {
        if ($this->auth->check()) {
            return null;
        }
        if ($request->expectsJson()) {
            return Response::json(['success' => false, 'message' => 'Login erforderlich.'], 401);
        }
        return Response::redirect('/login');
    }
}
