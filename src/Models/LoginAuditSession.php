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
 * @property string $authenticatable_type
 * @property int|string $authenticatable_id
 * @property string|null $guard
 * @property string $session_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $browser
 * @property string|null $platform
 * @property string|null $device_type
 * @property string|null $device_hash
 * @property Carbon|null $login_at
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $logged_out_at
 * @property-read Model|null $authenticatable
 */
class LoginAuditSession extends Model
{
    use MassPrunable;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'authenticatable_type',
        'authenticatable_id',
        'guard',
        'session_id',
        'ip_address',
        'user_agent',
        'browser',
        'platform',
        'device_type',
        'device_hash',
        'login_at',
        'last_activity_at',
        'logged_out_at',
    ];

    public function getTable(): string
    {
        return config('login-audit.table_names.sessions', 'login_audit_sessions');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'login_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'logged_out_at' => 'datetime',
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
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('logged_out_at');
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = config('login-audit.retention.sessions_days');

        if ($days === null) {
            return static::query()->whereRaw('1 = 0');
        }

        $cutoff = now()->subDays((int) $days);

        return static::query()->whereRaw('COALESCE(last_activity_at, login_at) < ?', [$cutoff]);
    }
}
