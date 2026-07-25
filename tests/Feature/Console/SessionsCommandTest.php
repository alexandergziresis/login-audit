<?php

declare(strict_types=1);

use Iresis\LoginAudit\Models\LoginAuditSession;
use Workbench\App\Models\User;

it('reports no active sessions when there are none', function () {
    $this->artisan('login-audit:sessions')
        ->expectsOutputToContain('No active sessions found.')
        ->assertSuccessful();
});

it('lists active sessions in a table', function () {
    $user = User::factory()->create();

    LoginAuditSession::create([
        'authenticatable_type' => $user::class,
        'authenticatable_id' => $user->id,
        'guard' => 'web',
        'session_id' => 'active-session',
        'ip_address' => '203.0.113.5',
        'browser' => 'Chrome',
        'platform' => 'Windows',
        'device_type' => 'desktop',
        'login_at' => now(),
        'last_activity_at' => now(),
    ]);

    LoginAuditSession::create([
        'authenticatable_type' => $user::class,
        'authenticatable_id' => $user->id,
        'session_id' => 'closed-session',
        'login_at' => now(),
        'logged_out_at' => now(),
    ]);

    $this->artisan('login-audit:sessions')
        ->expectsOutputToContain('active-session')
        ->doesntExpectOutputToContain('closed-session')
        ->assertSuccessful();
});

it('filters sessions by user and guard', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    LoginAuditSession::create([
        'authenticatable_type' => $user::class,
        'authenticatable_id' => $user->id,
        'guard' => 'web',
        'session_id' => 'web-session',
        'login_at' => now(),
    ]);

    LoginAuditSession::create([
        'authenticatable_type' => $otherUser::class,
        'authenticatable_id' => $otherUser->id,
        'guard' => 'api',
        'session_id' => 'api-session',
        'login_at' => now(),
    ]);

    $this->artisan('login-audit:sessions', ['--user' => (string) $user->id])
        ->expectsOutputToContain('web-session')
        ->doesntExpectOutputToContain('api-session')
        ->assertSuccessful();

    $this->artisan('login-audit:sessions', ['--guard' => 'api'])
        ->expectsOutputToContain('api-session')
        ->doesntExpectOutputToContain('web-session')
        ->assertSuccessful();
});

it('revokes a session by id', function () {
    $user = User::factory()->create();

    $session = LoginAuditSession::create([
        'authenticatable_type' => $user::class,
        'authenticatable_id' => $user->id,
        'session_id' => 'to-revoke',
        'login_at' => now(),
    ]);

    $this->artisan('login-audit:sessions', ['--revoke' => 'to-revoke'])
        ->expectsOutputToContain('Session [to-revoke] revoked.')
        ->assertSuccessful();

    expect($session->fresh()->logged_out_at)->not->toBeNull();
});

it('fails when revoking an unknown session id', function () {
    $this->artisan('login-audit:sessions', ['--revoke' => 'missing-session'])
        ->expectsOutputToContain('No session found with session_id [missing-session].')
        ->assertFailed();
});
