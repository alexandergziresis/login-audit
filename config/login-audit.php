<?php

declare(strict_types=1);
use Iresis\LoginAudit\Models\LoginAuditLog;
use Iresis\LoginAudit\Models\LoginAuditSession;

return [

    /*
    |--------------------------------------------------------------------------
    | Enable Login Audit
    |--------------------------------------------------------------------------
    |
    | When disabled, no listeners write to the audit tables and the
    | activity middleware becomes a no-op.
    |
    */

    'enabled' => env('LOGIN_AUDIT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Tracked Events
    |--------------------------------------------------------------------------
    |
    | Toggle which authentication events are recorded.
    |
    */

    'events' => [
        'login' => true,
        'logout' => true,
        'failed' => true,
        'other_device_logout' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    */

    'table_names' => [
        'logs' => 'login_audit_logs',
        'sessions' => 'login_audit_sessions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Override these with your own Eloquent models (extending the package's
    | base models) to customize behavior, e.g. filling tenant_id on create
    | for multi-tenant applications.
    |
    */

    'models' => [
        'log' => LoginAuditLog::class,
        'session' => LoginAuditSession::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Capture Options
    |--------------------------------------------------------------------------
    */

    'capture' => [
        'ip_address' => true,
        'user_agent' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity Throttle
    |--------------------------------------------------------------------------
    |
    | Minimum number of seconds between last_activity_at updates for the
    | same session when using the UpdateSessionActivity middleware.
    |
    */

    'activity_throttle' => env('LOGIN_AUDIT_ACTIVITY_THROTTLE', 60),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Number of days to keep records before they become prunable via
    | `php artisan login-audit:prune` or the model:prune scheduler.
    | Set to null to disable pruning for that table.
    |
    */

    'retention' => [
        'logs_days' => env('LOGIN_AUDIT_LOGS_RETENTION_DAYS', 90),
        'sessions_days' => env('LOGIN_AUDIT_SESSIONS_RETENTION_DAYS', 90),
    ],

];
