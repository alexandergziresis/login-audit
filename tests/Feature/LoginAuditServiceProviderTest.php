<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Iresis\LoginAudit\Facades\LoginAudit as LoginAuditFacade;
use Iresis\LoginAudit\LoginAudit;

it('resolves the singleton', function () {
    expect(app(LoginAudit::class))->toBeInstanceOf(LoginAudit::class);
});

it('resolves the singleton through the facade', function () {
    expect(LoginAuditFacade::getFacadeRoot())->toBe(app(LoginAudit::class));
});

it('registers the activity middleware alias', function () {
    $router = $this->app->make('router');

    expect($router->getMiddleware())->toHaveKey('login-audit.activity');
});

it('returns the same instance from the container', function () {
    expect(app(LoginAudit::class))->toBe(app(LoginAudit::class));
});

it('merges the package config', function () {
    expect(config('login-audit.enabled'))->toBeTrue()
        ->and(config('login-audit.table_names.logs'))->toBe('login_audit_logs')
        ->and(config('login-audit.table_names.sessions'))->toBe('login_audit_sessions')
        ->and(config('login-audit.events.login'))->toBeTrue()
        ->and(config('login-audit.events.logout'))->toBeTrue()
        ->and(config('login-audit.events.failed'))->toBeTrue()
        ->and(config('login-audit.retention.logs_days'))->toBe(90)
        ->and(config('login-audit.retention.sessions_days'))->toBe(90);
});

it('registers the artisan commands', function () {
    $commands = array_keys(Artisan::all());

    expect($commands)->toContain('login-audit:prune')
        ->toContain('login-audit:sessions');
});

it('adds a section to the about command', function () {
    $this->artisan('about')
        ->expectsOutputToContain('Login Audit')
        ->assertSuccessful();
});
