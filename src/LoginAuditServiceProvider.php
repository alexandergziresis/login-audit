<?php

declare(strict_types=1);

namespace Iresis\LoginAudit;

use Illuminate\Support\ServiceProvider;
use Iresis\LoginAudit\Console\Commands\LoginAuditCommand;

class LoginAuditServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/login-audit.php', 'login-audit');

        $this->app->singleton(LoginAudit::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/login-audit.php' => config_path('login-audit.php'),
        ], ['login-audit', 'login-audit-config']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['login-audit', 'login-audit-migrations']);

        $this->commands([
            LoginAuditCommand::class,
        ]);
    }
}
