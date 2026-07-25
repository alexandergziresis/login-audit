<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Iresis\LoginAudit\Models\LoginAuditLog;
use Workbench\App\Models\User;

it('creates the login_audit_logs table with the expected columns', function () {
    expect(Schema::hasTable('login_audit_logs'))->toBeTrue()
        ->and(Schema::hasColumns('login_audit_logs', [
            'id',
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
            'created_at',
        ]))->toBeTrue();
});

it('casts metadata to an array', function () {
    $log = LoginAuditLog::create([
        'event' => 'login',
        'metadata' => ['foo' => 'bar'],
    ]);

    expect($log->fresh()->metadata)->toBe(['foo' => 'bar']);
});

it('associates the authenticatable morph relation', function () {
    $user = User::factory()->create();

    $log = LoginAuditLog::create([
        'event' => 'login',
        'authenticatable_type' => $user::class,
        'authenticatable_id' => $user->id,
    ]);

    expect($log->authenticatable)->toBeInstanceOf(User::class)
        ->and($log->authenticatable->is($user))->toBeTrue();
});

it('scopes records to a tenant', function () {
    LoginAuditLog::create(['event' => 'login', 'tenant_id' => 'tenant-a']);
    LoginAuditLog::create(['event' => 'login', 'tenant_id' => 'tenant-b']);

    expect(LoginAuditLog::forTenant('tenant-a')->count())->toBe(1);
});

it('scopes records to logins and failures', function () {
    LoginAuditLog::create(['event' => 'login']);
    LoginAuditLog::create(['event' => 'failed']);
    LoginAuditLog::create(['event' => 'logout']);

    expect(LoginAuditLog::logins()->count())->toBe(1)
        ->and(LoginAuditLog::failures()->count())->toBe(1);
});

it('prunes logs older than the configured retention', function () {
    config(['login-audit.retention.logs_days' => 30]);

    $old = LoginAuditLog::create(['event' => 'login']);
    $old->forceFill(['created_at' => now()->subDays(31)])->save();

    $recent = LoginAuditLog::create(['event' => 'login']);

    (new LoginAuditLog)->pruneAll();

    expect(LoginAuditLog::query()->find($old->id))->toBeNull()
        ->and(LoginAuditLog::query()->find($recent->id))->not->toBeNull();
});

it('does not prune anything when retention is null', function () {
    config(['login-audit.retention.logs_days' => null]);

    $old = LoginAuditLog::create(['event' => 'login']);
    $old->forceFill(['created_at' => now()->subYears(5)])->save();

    (new LoginAuditLog)->pruneAll();

    expect(LoginAuditLog::query()->find($old->id))->not->toBeNull();
});
