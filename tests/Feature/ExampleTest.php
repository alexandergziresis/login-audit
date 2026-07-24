<?php

declare(strict_types=1);

use Iresis\LoginAudit\LoginAudit;

it('resolves the singleton', function () {
    expect(app(LoginAudit::class))->toBeInstanceOf(LoginAudit::class);
});

it('returns the same instance from the container', function () {
    expect(app(LoginAudit::class))->toBe(app(LoginAudit::class));
});

it('merges the package config', function () {
    expect(config('login-audit.placeholder'))->toBe('default');
});

it('registers the artisan command', function () {
    $this->artisan('login-audit:placeholder')
        ->expectsOutputToContain('LoginAudit placeholder command executed.')
        ->assertSuccessful();
});
