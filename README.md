<div align="center">
    <h1>Login Audit</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/iresis/login-audit"><img src="https://img.shields.io/packagist/v/iresis/login-audit.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/iresis/login-audit"><img src="https://img.shields.io/packagist/php-v/iresis/login-audit.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/iresis/login-audit"><img src="https://badge.laravel.cloud/badge/iresis/login-audit?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/iresis/login-audit/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/iresis/login-audit/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/iresis/login-audit"><img src="https://img.shields.io/packagist/dt/iresis/login-audit.svg?style=flat-square" alt="Total Downloads"></a>
</p>



## Installation

You can install the package via Composer:

```bash
composer require iresis/login-audit
```

You may publish all of the package's resources at once:

```bash
php artisan vendor:publish --tag="login-audit"
```

Or, you may publish each resource individually:

### Publishing the Configuration File

```bash
php artisan vendor:publish --tag="login-audit-config"
```

### Publishing and Running the Migrations

```bash
php artisan vendor:publish --tag="login-audit-migrations"
php artisan migrate
```

## Usage

Login Audit listens to Laravel's native authentication events (`Login`, `Logout`, `Failed`, `OtherDeviceLogout`) and records them automatically once installed — no extra wiring required in your login flow.

### What gets recorded

Every login, logout, failed attempt, and "logout other devices" event is written to the `login_audit_logs` table, along with the resolved IP address, user agent, browser, platform, and device type. Failed attempts store the submitted `email`/`username`/`login` credential as `identifier` (never the password) and, when the credentials matched an existing user, that user's morph reference.

Successful logins also open a row in `login_audit_sessions`, keyed by the framework session ID, which is closed (`logged_out_at`) on logout. This table is the basis for the sessions/devices API below.

### Configuration

Toggle what gets tracked, rename tables, swap in your own models, and set retention in `config/login-audit.php`:

```php
return [
    'enabled' => true,

    'events' => [
        'login' => true,
        'logout' => true,
        'failed' => true,
        'other_device_logout' => true,
    ],

    'models' => [
        'log' => \Iresis\LoginAudit\Models\LoginAuditLog::class,
        'session' => \Iresis\LoginAudit\Models\LoginAuditSession::class,
    ],

    'retention' => [
        'logs_days' => 90,
        'sessions_days' => 90,
    ],
];
```

### Multi-tenant applications (`tenant_id`)

Both tables include a nullable, indexed `tenant_id` column, and both models expose a `forTenant($tenantId)` scope. The package does not resolve the current tenant for you — publish the config and point `models.log`/`models.session` at your own subclasses that fill `tenant_id` (e.g. via a `creating` hook or a global scope tied to your tenancy package of choice):

```php
class TenantLoginAuditLog extends \Iresis\LoginAudit\Models\LoginAuditLog
{
    protected static function booted(): void
    {
        static::creating(fn ($log) => $log->tenant_id ??= currentTenantId());
    }
}
```

```php
// config/login-audit.php
'models' => [
    'log' => \App\Models\TenantLoginAuditLog::class,
    'session' => \App\Models\TenantLoginAuditSession::class,
],
```

Then scope queries with `LoginAuditLog::forTenant($tenantId)->get()`.

### Sessions and devices

Add the `HasLoginAudit` trait to your authenticatable model to get convenience relations:

```php
use Iresis\LoginAudit\Concerns\HasLoginAudit;

class User extends Authenticatable
{
    use HasLoginAudit;
}

$user->loginAudits;          // all recorded login/logout/failed events for this user
$user->auditSessions;        // all recorded sessions
$user->activeAuditSessions;  // sessions that haven't been logged out
$user->auditDevices();       // sessions grouped by device (browser/platform/device type)
```

The same operations are available through the `LoginAudit` facade or by injecting `Iresis\LoginAudit\LoginAudit`:

```php
use Iresis\LoginAudit\Facades\LoginAudit;

LoginAudit::sessionsFor($user);
LoginAudit::activeSessionsFor($user);
LoginAudit::devicesFor($user); // Collection<DeviceSummary>

LoginAudit::revokeSession($session);
LoginAudit::revokeOtherSessions($user, exceptSessionId: $currentSessionId);
```

`revokeSession()`/`revokeOtherSessions()` always mark the audit row as logged out. They can only force-invalidate the underlying framework session (so the device is actually kicked out on its next request) when `SESSION_DRIVER=database`, since that's the only driver this package can reach into from outside that session's own request lifecycle. With other drivers (`file`, `cookie`, `array`, `redis`, ...), only the audit bookkeeping is updated — build your own invalidation on top if you need it (e.g. a "logged out remotely" flag your middleware checks).

> **Note:** Laravel regenerates the session ID during login as session-fixation protection, so the session ID stored in `login_audit_sessions` reflects the *post-login* ID, not the one the browser sent with its login request.

### Keeping "last activity" fresh

Apply the `login-audit.activity` middleware alias to routes/groups where you want `last_activity_at` kept up to date (throttled by `login-audit.activity_throttle`, 60 seconds by default):

```php
Route::middleware(['auth', 'login-audit.activity'])->group(function () {
    // ...
});
```

### Artisan commands

```bash
# List active sessions (optionally filtered)
php artisan login-audit:sessions
php artisan login-audit:sessions --user=1 --guard=web

# Revoke a specific session
php artisan login-audit:sessions --revoke=<session_id>

# Prune logs/sessions past their configured retention
php artisan login-audit:prune
```

Schedule pruning in `routes/console.php` if you want it to run automatically:

```php
Schedule::command('login-audit:prune')->daily();
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Login Audit! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Alexander](https://github.com/iresis)
- [All Contributors](../../contributors)

## License

Login Audit is open-sourced software licensed under the [MIT license](LICENSE.md).
