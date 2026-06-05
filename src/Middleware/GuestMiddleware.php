<?php

declare(strict_types=1);

namespace Kletterdom\Middleware;

use Kletterdom\Auth\Auth;
use Kletterdom\Http\Request;
use Kletterdom\Http\Response;

final class GuestMiddleware
{
    public function __construct(private readonly Auth $auth)
    {
    }

    /** @param array<string,string> $params */
    public function handle(Request $request, array $params): ?Response
    {
        return $this->auth->check() ? Response::redirect('/dashboard') : null;
    }
}
