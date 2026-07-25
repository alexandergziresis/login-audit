<?php

declare(strict_types=1);

namespace Iresis\LoginAudit\Console\Commands;

use Illuminate\Console\Command;
use Iresis\LoginAudit\LoginAudit;
use Iresis\LoginAudit\Models\LoginAuditSession;

class SessionsCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'login-audit:sessions
        {--user= : Filter active sessions by authenticatable ID}
        {--guard= : Filter active sessions by guard name}
        {--revoke= : Revoke the session matching the given session ID}';

    /**
     * The command description.
     */
    protected $description = 'List active login audit sessions or revoke a specific session.';

    /**
     * Execute the console command.
     */
    public function handle(LoginAudit $loginAudit): int
    {
        $revoke = $this->option('revoke');

        if (is_string($revoke) && $revoke !== '') {
            return $this->revoke($loginAudit, $revoke);
        }

        return $this->list();
    }

    private function revoke(LoginAudit $loginAudit, string $sessionId): int
    {
        $session = $this->sessionModel()::query()->where('session_id', $sessionId)->first();

        if ($session === null) {
            $this->error("No session found with session_id [{$sessionId}].");

            return self::FAILURE;
        }

        $loginAudit->revokeSession($session);

        $this->info("Session [{$sessionId}] revoked.");

        return self::SUCCESS;
    }

    private function list(): int
    {
        $query = $this->sessionModel()::query()->active();

        $user = $this->option('user');

        if (is_string($user) && $user !== '') {
            $query->where('authenticatable_id', $user);
        }

        $guard = $this->option('guard');

        if (is_string($guard) && $guard !== '') {
            $query->where('guard', $guard);
        }

        $sessions = $query->orderByDesc('last_activity_at')->get();

        if ($sessions->isEmpty()) {
            $this->info('No active sessions found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Session ID', 'User', 'Guard', 'IP Address', 'Browser', 'Platform', 'Device', 'Login At', 'Last Activity'],
            $sessions->map(fn (LoginAuditSession $session): array => [
                $session->session_id,
                $session->authenticatable_type.'#'.$session->authenticatable_id,
                $session->guard,
                $session->ip_address,
                $session->browser,
                $session->platform,
                $session->device_type,
                $session->login_at?->toDateTimeString(),
                $session->last_activity_at?->toDateTimeString(),
            ])->all(),
        );

        return self::SUCCESS;
    }

    /**
     * @return class-string<LoginAuditSession>
     */
    private function sessionModel(): string
    {
        /** @var class-string<LoginAuditSession> $model */
        $model = config('login-audit.models.session', LoginAuditSession::class);

        return $model;
    }
}
