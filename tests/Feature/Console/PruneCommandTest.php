<?php

declare(strict_types=1);

use Iresis\LoginAudit\Models\LoginAuditLog;
use Iresis\LoginAudit\Models\LoginAuditSession;
use Workbench\App\Models\User;

it('prunes logs and sessions past their configured retention and reports the counts', function () {
    config([
        'login-audit.retention.logs_days' => 30,
        'login-audit.retention.sessions_days' => 30,
    ]);

    $user = User::factory()->create();

    $oldLog = LoginAuditLog::create(['event' => 'login']);
    $oldLog->forceFill(['created_at' => now()->subDays(31)])->save();

    LoginAuditLog::create(['event' => 'login']);

    $oldSession = LoginAuditSession::create([
        'authenticatable_type' => $user::class,
        'authenticatable_id' => $user->id,
        'session_id' => 'old-session',
        'login_at' => now()->subDays(45),
        'last_activity_at' => now()->subDays(31),
    ]);

    LoginAuditSession::create([
        'authenticatable_type' => $user::class,
        'authenticatable_id' => $user->id,
        'session_id' => 'recent-session',
        'login_at' => now(),
    ]);

    $this->artisan('login-audit:prune')
        ->expectsOutputToContain('Pruned 1 login audit log(s) and 1 session(s).')
        ->assertSuccessful();

    expect(LoginAuditLog::query()->find($oldLog->id))->toBeNull()
        ->and(LoginAuditLog::query()->count())->toBe(1)
        ->and(LoginAuditSession::query()->find($oldSession->id))->toBeNull()
        ->and(LoginAuditSession::query()->count())->toBe(1);
});

it('prunes nothing when retention is disabled', function () {
    config([
        'login-audit.retention.logs_days' => null,
        'login-audit.retention.sessions_days' => null,
    ]);

    $log = LoginAuditLog::create(['event' => 'login']);
    $log->forceFill(['created_at' => now()->subYears(5)])->save();

    $this->artisan('login-audit:prune')
        ->expectsOutputToContain('Pruned 0 login audit log(s) and 0 session(s).')
        ->assertSuccessful();

    expect(LoginAuditLog::query()->find($log->id))->not->toBeNull();
});
