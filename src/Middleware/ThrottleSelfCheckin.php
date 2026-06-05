<?php

declare(strict_types=1);

namespace Kletterdom\Middleware;

use Kletterdom\Http\Request;
use Kletterdom\Http\Response;
use Kletterdom\Http\Throttle;

/**
 * 60 Self-Check-in-Scans pro Minute pro IP — wie heute in routes/web.php.
 */
final class ThrottleSelfCheckin
{
    public function __construct(private readonly Throttle $throttle)
    {
    }

    /** @param array<string,string> $params */
    public function handle(Request $request, array $params): ?Response
    {
        $key = 'self-checkin:' . $request->ip();
        if ($this->throttle->hit($key, 60, 60)) {
            return null;
        }
        return Response::json(['success' => false, 'message' => 'Zu viele Versuche'], 429);
    }
}
