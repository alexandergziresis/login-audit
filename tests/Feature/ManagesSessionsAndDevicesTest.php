<?php

declare(strict_types=1);

use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Iresis\LoginAudit\Facades\LoginAudit;
use Iresis\LoginAudit\LoginAudit as LoginAuditService;
use Iresis\LoginAudit\Models\LoginAuditSession;
use Workbench\App\Models\User;

function bindAuditedRequest(string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/125.0.0.0 Safari/537.36', string $ip = '203.0.113.5'): string
{
    // Force the "array" driver regardless of the app's configured default so
    // this helper and Auth::login()'s own SessionGuard (which resolves the
    // session store via the app's default driver) always share one store.
    config(['session.driver' => 'array']);

    $request = Request::create('/', 'GET', server: [
        'HTTP_USER_AGENT' => $userAgent,
        'REMOTE_ADDR' => $ip,
    ]);

    $session = app('session')->driver('array');
    $session->setId(Str::random(40));
    $session->start();
    $request->setLaravelSession($session);

    app()->instance('request', $request);

    return $session->getId();
}

it('creates an active session row on login', function () {
    bindAuditedRequest();
    $user = User::factory()->create();

    Auth::login($user);

    // Laravel regenerates the session ID during login (session fixation
    // protection), so the stored session_id reflects the post-login ID.
    $session = LoginAuditSession::query()->sole();

    expect($session->session_id)->toBe(session()->getId())
        ->and($session->authenticatable_id)->toBe($user->id)
        ->and($session->browser)->toBe('Chrome')
        ->and($session->logged_out_at)->toBeNull();
});

it('closes the session row on logout', function () {
    bindAuditedRequest();
    $user = User::factory()->create();

    Auth::login($user);
    Auth::logout();

    $session = LoginAuditSession::query()->sole();

    expect($session->logged_out_at)->not->toBeNull();
});

it('closes other sessions but keeps the current one on other device logout', function () {
    bindAuditedRequest();
    $user = User::factory()->create();

    Auth::login($user);
    $currentSessionId = LoginAuditSession::query()->sole()->session_id;

    LoginAuditSession::create([
        'authenticatable_type' => $user::class,
        'authenticatable_id' => $user->id,
        'session_id' => 'other-device-session',
        'login_at' => now(),
    ]);

    event(new OtherDeviceLogout('web', $user));

    expect(LoginAuditSession::query()->where('session_id', $currentSessionId)->sole()->logged_out_at)->toBeNull()
        ->and(LoginAuditSession::query()->where('session_id', 'other-device-session')->sole()->logged_out_at)->not->toBeNull();
});

it('lists sessions and active sessions for a user via the facade', function () {
    bindAuditedRequest();
    $user = User::factory()->create();

    Auth::login($user);
    Auth::logout();

    bindAuditedRequest();
    Auth::login($user);

    expect(LoginAudit::sessionsFor($user))->toHaveCount(2)
        ->and(LoginAudit::activeSessionsFor($user))->toHaveCount(1);
});

it('groups sessions into devices for a user', function () {
    bindAuditedRequest('Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/125.0.0.0 Safari/537.36');
    $user = User::factory()->create();
    Auth::login($user);
    Auth::logout();

    bindAuditedRequest('Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/125.0.0.0 Safari/537.36');
    Auth::login($user);

    bindAuditedRequest('Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) Version/17.5 Mobile/15E148 Safari/604.1');
    Auth::login($user);

    $devices = LoginAudit::devicesFor($user);

    expect($devices)->toHaveCount(2);

    $desktop = $devices->firstWhere('deviceType', 'desktop');
    $mobile = $devices->firstWhere('deviceType', 'mobile');

    expect($desktop->sessionsCount)->toBe(2)
        ->and($desktop->activeSessionsCount)->toBe(1)
        ->and($mobile->sessionsCount)->toBe(1)
        ->and($mobile->activeSessionsCount)->toBe(1);
});

it('revokes a session and deletes its database session row when using the database driver', function () {
    config(['session.driver' => 'database', 'session.table' => 'sessions']);

    if (Schema::hasTable('sessions')) {
        DB::table('sessions')->truncate();
    } else {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->text('payload');
            $table->integer('last_activity')->index();
        });
    }

    $user = User::factory()->create();

    DB::table('sessions')->insert([
        'id' => 'db-session-1',
        'payload' => base64_encode('data'),
        'last_activity' => time(),
    ]);

    $session = LoginAuditSession::create([
        'authenticatable_type' => $user::class,
        'authenticatable_id' => $user->id,
        'session_id' => 'db-session-1',
        'login_at' => now(),
    ]);

    app(LoginAuditService::class)->revokeSession($session);

    expect($session->fresh()->logged_out_at)->not->toBeNull()
        ->and(DB::table('sessions')->where('id', 'db-session-1')->exists())->toBeFalse();
});

it('revokes a session without touching the framework session store on non-database drivers', function () {
    config(['session.driver' => 'array']);

    $user = User::factory()->create();

    $session = LoginAuditSession::create([
        'authenticatable_type' => $user::class,
        'authenticatable_id' => $user->id,
        'session_id' => 'array-session-1',
        'login_at' => now(),
    ]);

    app(LoginAuditService::class)->revokeSession($session);

    expect($session->fresh()->logged_out_at)->not->toBeNull();
});

it('revokes every other active session for a user', function () {
    $user = User::factory()->create();

    $current = LoginAuditSession::create([
        'authenticatable_type' => $user::class,
        'authenticatable_id' => $user->id,
        'session_id' => 'current-session',
        'login_at' => now(),
    ]);

    $other = LoginAuditSession::create([
        'authenticatable_type' => $user::class,
        'authenticatable_id' => $user->id,
        'session_id' => 'other-session',
        'login_at' => now(),
    ]);

    app(LoginAuditService::class)->revokeOtherSessions($user, 'current-session');

    expect($current->fresh()->logged_out_at)->toBeNull()
        ->and($other->fresh()->logged_out_at)->not->toBeNull();
});

it('exposes sessions and devices through the HasLoginAudit trait', function () {
    bindAuditedRequest();
    $user = User::factory()->create();

    Auth::login($user);
    Auth::logout();

    bindAuditedRequest();
    Auth::login($user);

    expect($user->auditSessions)->toHaveCount(2)
        ->and($user->activeAuditSessions()->count())->toBe(1)
        ->and($user->auditDevices())->toHaveCount(1)
        ->and($user->loginAudits()->count())->toBeGreaterThan(0);
});
