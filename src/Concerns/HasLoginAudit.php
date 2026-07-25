<?php

declare(strict_types=1);

namespace Iresis\LoginAudit\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Iresis\LoginAudit\Facades\LoginAudit;
use Iresis\LoginAudit\Models\LoginAuditLog;
use Iresis\LoginAudit\Models\LoginAuditSession;
use Iresis\LoginAudit\Support\DeviceSummary;

trait HasLoginAudit
{
    /**
     * @return MorphMany<LoginAuditLog, $this>
     */
    public function loginAudits(): MorphMany
    {
        return $this->morphMany($this->loginAuditLogModel(), 'authenticatable');
    }

    /**
     * @return MorphMany<LoginAuditSession, $this>
     */
    public function auditSessions(): MorphMany
    {
        return $this->morphMany($this->loginAuditSessionModel(), 'authenticatable');
    }

    /**
     * @return MorphMany<LoginAuditSession, $this>
     */
    public function activeAuditSessions(): MorphMany
    {
        return $this->auditSessions()->active();
    }

    /**
     * @return Collection<int, DeviceSummary>
     */
    public function auditDevices(): Collection
    {
        return LoginAudit::devicesFor($this);
    }

    /**
     * @return class-string<LoginAuditLog>
     */
    private function loginAuditLogModel(): string
    {
        /** @var class-string<LoginAuditLog> $model */
        $model = config('login-audit.models.log', LoginAuditLog::class);

        return $model;
    }

    /**
     * @return class-string<LoginAuditSession>
     */
    private function loginAuditSessionModel(): string
    {
        /** @var class-string<LoginAuditSession> $model */
        $model = config('login-audit.models.session', LoginAuditSession::class);

        return $model;
    }
}
