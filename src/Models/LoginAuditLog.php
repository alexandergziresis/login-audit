<?php

declare(strict_types=1);

namespace Iresis\LoginAudit\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $tenant_id
 * @property string|null $authenticatable_type
 * @property int|string|null $authenticatable_id
 * @property string|null $guard
 * @property string $event
 * @property string|null $identifier
 * @property string|null $session_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $browser
 * @property string|null $platform
 * @property string|null $device_type
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property-read Model|null $authenticatable
 */
class LoginAuditLog extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'authenticatable_type',
        'authenticatable_id',
        'guard',
        'event',
        'identifier',
        'session_id',
        'ip_address',
        'user_agent',
        'browser',
        'platform',
        'device_type',
        'metadata',
    ];

    public function getTable(): string
    {
        return config('login-audit.table_names.logs', 'login_audit_logs');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForTenant(Builder $query, ?string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeLogins(Builder $query): Builder
    {
        return $query->where('event', 'login');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFailures(Builder $query): Builder
    {
        return $query->where('event', 'failed');
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = config('login-audit.retention.logs_days');

        if ($days === null) {
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()->where('created_at', '<', now()->subDays((int) $days));
    }
}
