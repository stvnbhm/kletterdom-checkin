<?php

declare(strict_types=1);

namespace Kletterdom\Middleware;

use Kletterdom\Auth\Auth;
use Kletterdom\Http\Request;
use Kletterdom\Http\Response;

final class AdminMiddleware
{
    public function __construct(private readonly Auth $auth)
    {
    }

    /** @param array<string,string> $params */
    public function handle(Request $request, array $params): ?Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->auth->isAdmin()) {
            return Response::html('<h1>403 — Forbidden</h1>', 403);
        }
        return null;
    }
}
