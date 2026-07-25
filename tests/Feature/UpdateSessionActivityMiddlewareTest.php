<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Iresis\LoginAudit\Http\Middleware\UpdateSessionActivity;
use Iresis\LoginAudit\Models\LoginAuditSession;
use Symfony\Component\HttpFoundation\Response;
use Workbench\App\Models\User;

function requestForSession(string $sessionId): Request
{
    $request = Request::create('/');

    $session = app('session')->driver('array');
    $session->setId($sessionId);
    $session->start();
    $request->setLaravelSession($session);

    return $request;
}

function runActivityMiddleware(Request $request): void
{
    (new UpdateSessionActivity)->handle($request, fn (Request $req): Response => new Response);
}

it('updates last_activity_at for the current session', function () {
    $user = User::factory()->create();
    $sessionId = Str::random(40);

    $session = LoginAuditSession::create([
        'authenticatable_type' => $user::class,
        'authenticatable_id' => $user->id,
        'session_id' => $sessionId,
        'login_at' => now()->subMinutes(10),
        'last_activity_at' => now()->subMinutes(10),
    ]);

    runActivityMiddleware(requestForSession($sessionId));

    expect($session->fresh()->last_activity_at->isAfter($session->last_activity_at))->toBeTrue();
});

it('does not update last_activity_at within the throttle window', function () {
    config(['login-audit.activity_throttle' => 60]);

    $user = User::factory()->create();
    $sessionId = Str::random(40);
    $lastActivity = now()->subSeconds(10);

    $session = LoginAuditSession::create([
        'authenticatable_type' => $user::class,
        'authenticatable_id' => $user->id,
        'session_id' => $sessionId,
        'login_at' => now()->subMinutes(10),
        'last_activity_at' => $lastActivity,
    ]);

    runActivityMiddleware(requestForSession($sessionId));

    expect($session->fresh()->last_activity_at->timestamp)->toBe($lastActivity->timestamp);
});

it('does not touch a session that has already been logged out', function () {
    $user = User::factory()->create();
    $sessionId = Str::random(40);
    $loggedOutAt = now()->subMinutes(5);

    $session = LoginAuditSession::create([
        'authenticatable_type' => $user::class,
        'authenticatable_id' => $user->id,
        'session_id' => $sessionId,
        'login_at' => now()->subMinutes(10),
        'last_activity_at' => now()->subMinutes(10),
        'logged_out_at' => $loggedOutAt,
    ]);

    runActivityMiddleware(requestForSession($sessionId));

    expect($session->fresh()->logged_out_at->timestamp)->toBe($loggedOutAt->timestamp);
});

it('does nothing when the package is disabled', function () {
    config(['login-audit.enabled' => false]);

    $user = User::factory()->create();
    $sessionId = Str::random(40);
    $lastActivity = now()->subMinutes(10);

    $session = LoginAuditSession::create([
        'authenticatable_type' => $user::class,
        'authenticatable_id' => $user->id,
        'session_id' => $sessionId,
        'login_at' => now()->subMinutes(10),
        'last_activity_at' => $lastActivity,
    ]);

    runActivityMiddleware(requestForSession($sessionId));

    expect($session->fresh()->last_activity_at->timestamp)->toBe($lastActivity->timestamp);
});

it('does nothing when the request has no session', function () {
    $request = Request::create('/');

    runActivityMiddleware($request);
})->throwsNoExceptions();
