<?php

declare(strict_types=1);

use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Iresis\LoginAudit\Models\LoginAuditLog;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->app->instance('request', Request::create('/', 'GET', server: [
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/125.0.0.0 Safari/537.36',
        'REMOTE_ADDR' => '203.0.113.5',
    ]));
});

it('records a login event with ip, user agent and device details', function () {
    $user = User::factory()->create();

    Auth::login($user);

    $log = LoginAuditLog::query()->sole();

    expect($log->event)->toBe('login')
        ->and($log->guard)->toBe('web')
        ->and($log->authenticatable_type)->toBe($user::class)
        ->and($log->authenticatable_id)->toBe($user->id)
        ->and($log->ip_address)->toBe('203.0.113.5')
        ->and($log->browser)->toBe('Chrome')
        ->and($log->platform)->toBe('Windows')
        ->and($log->device_type)->toBe('desktop');
});

it('records a logout event', function () {
    $user = User::factory()->create();

    Auth::login($user);
    Auth::logout();

    expect(LoginAuditLog::logins()->count())->toBe(1)
        ->and(LoginAuditLog::query()->where('event', 'logout')->count())->toBe(1);
});

it('records a failed login attempt for a known user with an identifier', function () {
    $user = User::factory()->create();

    Auth::attempt(['email' => $user->email, 'password' => 'wrong-password']);

    $log = LoginAuditLog::failures()->sole();

    expect($log->authenticatable_type)->toBe($user::class)
        ->and($log->authenticatable_id)->toBe($user->id)
        ->and($log->identifier)->toBe($user->email);
});

it('records a failed login attempt for an unknown user without an authenticatable', function () {
    Auth::attempt(['email' => 'missing@example.com', 'password' => 'whatever']);

    $log = LoginAuditLog::failures()->sole();

    expect($log->authenticatable_type)->toBeNull()
        ->and($log->authenticatable_id)->toBeNull()
        ->and($log->identifier)->toBe('missing@example.com');
});

it('records an other device logout event', function () {
    $user = User::factory()->create();

    Auth::login($user);
    event(new OtherDeviceLogout('web', $user));

    expect(LoginAuditLog::query()->where('event', 'other_device_logout')->count())->toBe(1);
});

it('does not record anything when the package is disabled', function () {
    config(['login-audit.enabled' => false]);

    Auth::login(User::factory()->create());

    expect(LoginAuditLog::query()->count())->toBe(0);
});

it('does not record login events when the login toggle is disabled', function () {
    config(['login-audit.events.login' => false]);

    Auth::login(User::factory()->create());

    expect(LoginAuditLog::query()->count())->toBe(0);
});
