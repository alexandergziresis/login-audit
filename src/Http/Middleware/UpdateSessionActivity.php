<?php

declare(strict_types=1);

namespace Iresis\LoginAudit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Iresis\LoginAudit\Models\LoginAuditSession;
use Symfony\Component\HttpFoundation\Response;

class UpdateSessionActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->touchSession($request);

        return $response;
    }

    private function touchSession(Request $request): void
    {
        if (! config('login-audit.enabled') || ! $request->hasSession()) {
            return;
        }

        /** @var class-string<LoginAuditSession> $model */
        $model = config('login-audit.models.session', LoginAuditSession::class);

        $session = $model::query()
            ->where('session_id', $request->session()->getId())
            ->whereNull('logged_out_at')
            ->first();

        if ($session === null) {
            return;
        }

        $throttle = (int) config('login-audit.activity_throttle', 60);

        if ($session->last_activity_at !== null && $session->last_activity_at->diffInSeconds(now()) < $throttle) {
            return;
        }

        $session->forceFill(['last_activity_at' => now()])->save();
    }
}
