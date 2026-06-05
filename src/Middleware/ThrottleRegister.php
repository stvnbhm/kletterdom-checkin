<?php

declare(strict_types=1);

namespace Kletterdom\Middleware;

use Kletterdom\Http\Request;
use Kletterdom\Http\Response;
use Kletterdom\Http\Throttle;

/**
 * 3 Registrierungen pro Minute pro IP — wie heute in routes/web.php.
 */
final class ThrottleRegister
{
    public function __construct(private readonly Throttle $throttle)
    {
    }

    /** @param array<string,string> $params */
    public function handle(Request $request, array $params): ?Response
    {
        $key = 'register:' . $request->ip();
        if ($this->throttle->hit($key, 3, 60)) {
            return null;
        }
        return Response::html('<h1>429 — Zu viele Anfragen</h1><p>Bitte gleich erneut versuchen.</p>', 429);
    }
}
