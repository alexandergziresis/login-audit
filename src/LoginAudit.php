<?php

declare(strict_types=1);

namespace Iresis\LoginAudit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Iresis\LoginAudit\Models\LoginAuditLog;
use Iresis\LoginAudit\Models\LoginAuditSession;
use Iresis\LoginAudit\Support\DeviceDetails;
use Iresis\LoginAudit\Support\DeviceSummary;

class LoginAudit
{
    public function recordLogin(Authenticatable $user, string $guard): void
    {
        if (! $this->shouldRecord('login')) {
            return;
        }

        $this->log('login', $guard, $user);
        $this->openSession($user, $guard);
    }

    public function recordLogout(Authenticatable $user, string $guard): void
    {
        if (! $this->shouldRecord('logout')) {
            return;
        }

        $this->log('logout', $guard, $user);
        $this->closeCurrentSession();
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function recordFailedLogin(?Authenticatable $user, string $guard, array $credentials): void
    {
        if (! $this->shouldRecord('failed')) {
            return;
        }

        $this->log('failed', $guard, $user, $this->identifierFromCredentials($credentials));
    }

    public function recordOtherDeviceLogout(Authenticatable $user, string $guard): void
    {
        if (! $this->shouldRecord('other_device_logout')) {
            return;
        }

        $this->log('other_device_logout', $guard, $user);
        $this->closeOtherSessions($user);
    }

    /**
     * All recorded sessions for the given authenticatable, most recent first.
     *
     * @return EloquentCollection<int, LoginAuditSession>
     */
    public function sessionsFor(Authenticatable $user): EloquentCollection
    {
        return $this->sessionsQuery($user)->orderByDesc('login_at')->get();
    }

    /**
     * Sessions for the given authenticatable that have not been logged out.
     *
     * @return EloquentCollection<int, LoginAuditSession>
     */
    public function activeSessionsFor(Authenticatable $user): EloquentCollection
    {
        return $this->sessionsQuery($user)->active()->orderByDesc('last_activity_at')->get();
    }

    /**
     * Sessions grouped by device signature (browser/platform/device type).
     *
     * @return Collection<int, DeviceSummary>
     */
    public function devicesFor(Authenticatable $user): Collection
    {
        $sessions = $this->sessionsQuery($user)->orderBy('login_at')->get();

        $devices = [];

        foreach ($sessions->groupBy('device_hash') as $deviceHash => $group) {
            $first = $group->first();

            $devices[] = new DeviceSummary(
                deviceHash: (string) $deviceHash,
                browser: $first?->browser,
                platform: $first?->platform,
                deviceType: $first?->device_type,
                firstSeenAt: $this->toCarbon($group->min('login_at')),
                lastSeenAt: $this->toCarbon($group->max('last_activity_at') ?? $group->max('login_at')),
                sessionsCount: $group->count(),
                activeSessionsCount: $group->whereNull('logged_out_at')->count(),
            );
        }

        return collect($devices);
    }

    /**
     * Mark a session as logged out and, when possible, invalidate it at the
     * framework level (only supported by the "database" session driver).
     */
    public function revokeSession(LoginAuditSession $session): void
    {
        if ($session->logged_out_at === null) {
            $session->forceFill(['logged_out_at' => now()])->save();
        }

        $this->forgetFrameworkSession($session->session_id);
    }

    /**
     * Revoke every active session for the given authenticatable except the
     * one matching $exceptSessionId (defaults to the current session).
     */
    public function revokeOtherSessions(Authenticatable $user, ?string $exceptSessionId = null): void
    {
        $exceptSessionId ??= $this->currentSessionId();

        $sessions = $this->sessionsQuery($user)
            ->active()
            ->when($exceptSessionId !== null, fn (Builder $query): Builder => $query->where('session_id', '!=', $exceptSessionId))
            ->get();

        foreach ($sessions as $session) {
            $this->revokeSession($session);
        }
    }

    private function shouldRecord(string $event): bool
    {
        return (bool) config('login-audit.enabled') && (bool) config("login-audit.events.{$event}");
    }

    private function log(string $event, string $guard, ?Authenticatable $user, ?string $identifier = null): void
    {
        $request = $this->request();
        $device = DeviceDetails::fromUserAgent($request->userAgent());

        $this->logModel()::create([
            'authenticatable_type' => $user === null ? null : $user::class,
            'authenticatable_id' => $user?->getAuthIdentifier(),
            'guard' => $guard,
            'event' => $event,
            'identifier' => $identifier,
            'session_id' => $this->currentSessionId(),
            'ip_address' => config('login-audit.capture.ip_address') ? $request->ip() : null,
            'user_agent' => config('login-audit.capture.user_agent') ? $device->userAgent : null,
            'browser' => $device->browser,
            'platform' => $device->platform,
            'device_type' => $device->deviceType,
        ]);
    }

    private function openSession(Authenticatable $user, string $guard): void
    {
        $sessionId = $this->currentSessionId();

        if ($sessionId === null) {
            return;
        }

        $request = $this->request();
        $device = DeviceDetails::fromUserAgent($request->userAgent());

        $this->sessionModel()::updateOrCreate(
            ['session_id' => $sessionId],
            [
                'authenticatable_type' => $user::class,
                'authenticatable_id' => $user->getAuthIdentifier(),
                'guard' => $guard,
                'ip_address' => config('login-audit.capture.ip_address') ? $request->ip() : null,
                'user_agent' => config('login-audit.capture.user_agent') ? $device->userAgent : null,
                'browser' => $device->browser,
                'platform' => $device->platform,
                'device_type' => $device->deviceType,
                'device_hash' => $device->hash(),
                'login_at' => now(),
                'last_activity_at' => now(),
                'logged_out_at' => null,
            ],
        );
    }

    private function closeCurrentSession(): void
    {
        $sessionId = $this->currentSessionId();

        if ($sessionId === null) {
            return;
        }

        $this->sessionModel()::query()
            ->where('session_id', $sessionId)
            ->whereNull('logged_out_at')
            ->update(['logged_out_at' => now()]);
    }

    private function closeOtherSessions(Authenticatable $user): void
    {
        $currentSessionId = $this->currentSessionId();

        $this->sessionsQuery($user)
            ->active()
            ->when($currentSessionId !== null, fn (Builder $query): Builder => $query->where('session_id', '!=', $currentSessionId))
            ->update(['logged_out_at' => now()]);
    }

    private function forgetFrameworkSession(?string $sessionId): void
    {
        if ($sessionId === null || config('session.driver') !== 'database') {
            return;
        }

        /** @var string $table */
        $table = config('session.table', 'sessions');

        DB::connection(config('session.connection'))->table($table)->where('id', $sessionId)->delete();
    }

    /**
     * @return Builder<LoginAuditSession>
     */
    private function sessionsQuery(Authenticatable $user): Builder
    {
        return $this->sessionModel()::query()
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', $user->getAuthIdentifier());
    }

    private function currentSessionId(): ?string
    {
        $request = $this->request();

        return $request->hasSession() ? $request->session()->getId() : null;
    }

    private function request(): Request
    {
        return app('request');
    }

    private function toCarbon(mixed $value): ?Carbon
    {
        return $value instanceof Carbon ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    private function identifierFromCredentials(array $credentials): ?string
    {
        foreach (['email', 'username', 'login'] as $key) {
            if (isset($credentials[$key]) && is_string($credentials[$key])) {
                return $credentials[$key];
            }
        }

        return null;
    }

    /**
     * @return class-string<LoginAuditLog>
     */
    private function logModel(): string
    {
        /** @var class-string<LoginAuditLog> $model */
        $model = config('login-audit.models.log', LoginAuditLog::class);

        return $model;
    }

    /**
     * @return class-string<LoginAuditSession>
     */
    private function sessionModel(): string
    {
        /** @var class-string<LoginAuditSession> $model */
        $model = config('login-audit.models.session', LoginAuditSession::class);

        return $model;
    }
}
