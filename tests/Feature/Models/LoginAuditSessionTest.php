<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Iresis\LoginAudit\Models\LoginAuditSession;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

function makeAuditSession(User $user, array $attributes = []): LoginAuditSession
{
    return LoginAuditSession::create(array_merge([
        'authenticatable_type' => $user::class,
        'authenticatable_id' => $user->id,
        'login_at' => now(),
    ], $attributes));
}

it('creates the login_audit_sessions table with the expected columns', function () {
    expect(Schema::hasTable('login_audit_sessions'))->toBeTrue()
        ->and(Schema::hasColumns('login_audit_sessions', [
            'id',
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
        ]))->toBeTrue();
});

it('associates the authenticatable morph relation', function () {
    $session = makeAuditSession($this->user, ['session_id' => 'sess-1']);

    expect($session->authenticatable)->toBeInstanceOf(User::class)
        ->and($session->authenticatable->is($this->user))->toBeTrue();
});

it('scopes records to a tenant', function () {
    makeAuditSession($this->user, ['session_id' => 'sess-a', 'tenant_id' => 'tenant-a']);
    makeAuditSession($this->user, ['session_id' => 'sess-b', 'tenant_id' => 'tenant-b']);

    expect(LoginAuditSession::forTenant('tenant-a')->count())->toBe(1);
});

it('scopes active sessions to those without logged_out_at', function () {
    makeAuditSession($this->user, ['session_id' => 'sess-active']);
    makeAuditSession($this->user, ['session_id' => 'sess-closed', 'logged_out_at' => now()]);

    expect(LoginAuditSession::active()->count())->toBe(1);
});

it('prunes sessions inactive past the configured retention', function () {
    config(['login-audit.retention.sessions_days' => 30]);

    $old = makeAuditSession($this->user, ['session_id' => 'sess-old', 'login_at' => now()->subDays(45)]);
    $old->forceFill(['last_activity_at' => now()->subDays(31)])->save();

    $recent = makeAuditSession($this->user, ['session_id' => 'sess-recent']);

    (new LoginAuditSession)->pruneAll();

    expect(LoginAuditSession::query()->find($old->id))->toBeNull()
        ->and(LoginAuditSession::query()->find($recent->id))->not->toBeNull();
});

it('does not prune anything when retention is null', function () {
    config(['login-audit.retention.sessions_days' => null]);

    $old = makeAuditSession($this->user, ['session_id' => 'sess-old', 'login_at' => now()->subYears(5)]);

    (new LoginAuditSession)->pruneAll();

    expect(LoginAuditSession::query()->find($old->id))->not->toBeNull();
});
