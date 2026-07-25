<?php

declare(strict_types=1);

namespace Iresis\LoginAudit;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Iresis\LoginAudit\Console\Commands\PruneCommand;
use Iresis\LoginAudit\Console\Commands\SessionsCommand;
use Iresis\LoginAudit\Http\Middleware\UpdateSessionActivity;
use Iresis\LoginAudit\Listeners\RecordFailedLogin;
use Iresis\LoginAudit\Listeners\RecordLogin;
use Iresis\LoginAudit\Listeners\RecordLogout;
use Iresis\LoginAudit\Listeners\RecordOtherDeviceLogout;

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
        $this->registerEventListeners();

        Route::aliasMiddleware('login-audit.activity', UpdateSessionActivity::class);

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
            PruneCommand::class,
            SessionsCommand::class,
        ]);

        AboutCommand::add('Login Audit', fn (): array => [
            'Enabled' => config('login-audit.enabled') ? 'Yes' : 'No',
            'Session Driver' => config('session.driver'),
            'Logs Retention' => $this->retentionSummary('login-audit.retention.logs_days'),
            'Sessions Retention' => $this->retentionSummary('login-audit.retention.sessions_days'),
        ]);
    }

    private function retentionSummary(string $configKey): string
    {
        $days = config($configKey);

        return $days === null ? 'Disabled' : "{$days} days";
    }

    private function registerEventListeners(): void
    {
        Event::listen(Login::class, RecordLogin::class);
        Event::listen(Logout::class, RecordLogout::class);
        Event::listen(Failed::class, RecordFailedLogin::class);
        Event::listen(OtherDeviceLogout::class, RecordOtherDeviceLogout::class);
    }
}
