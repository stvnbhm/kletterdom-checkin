<?php

declare(strict_types=1);

namespace Kletterdom\Middleware;

use Kletterdom\Http\Csrf;
use Kletterdom\Http\Request;
use Kletterdom\Http\Response;

final class CsrfMiddleware
{
    public function __construct(private readonly Csrf $csrf)
    {
    }

    /** @param array<string,string> $params */
    public function handle(Request $request, array $params): ?Response
    {
        if (in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return null;
        }

        if (! $this->csrf->verify($request->csrfToken())) {
            if ($request->expectsJson()) {
                return Response::json(['success' => false, 'message' => 'Sitzung abgelaufen.'], 419);
            }
            return Response::html('<h1>419 — Sitzung abgelaufen</h1><p>Bitte Seite neu laden.</p>', 419);
        }

        return null;
    }
}
