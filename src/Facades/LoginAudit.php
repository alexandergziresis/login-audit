<?php

declare(strict_types=1);

namespace Iresis\LoginAudit\Facades;

use Illuminate\Support\Facades\Facade;
use Iresis\LoginAudit\LoginAudit as LoginAuditService;

/**
 * @method static \Illuminate\Database\Eloquent\Collection<int, \Iresis\LoginAudit\Models\LoginAuditSession> sessionsFor(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static \Illuminate\Database\Eloquent\Collection<int, \Iresis\LoginAudit\Models\LoginAuditSession> activeSessionsFor(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static \Illuminate\Support\Collection<int, \Iresis\LoginAudit\Support\DeviceSummary> devicesFor(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static void revokeSession(\Iresis\LoginAudit\Models\LoginAuditSession $session)
 * @method static void revokeOtherSessions(\Illuminate\Contracts\Auth\Authenticatable $user, ?string $exceptSessionId = null)
 *
 * @see LoginAuditService
 */
class LoginAudit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LoginAuditService::class;
    }
}
