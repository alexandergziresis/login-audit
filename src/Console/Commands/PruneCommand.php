<?php

declare(strict_types=1);

namespace Iresis\LoginAudit\Console\Commands;

use Illuminate\Console\Command;
use Iresis\LoginAudit\Models\LoginAuditLog;
use Iresis\LoginAudit\Models\LoginAuditSession;

class PruneCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'login-audit:prune';

    /**
     * The command description.
     */
    protected $description = 'Prune login audit logs and sessions past their configured retention period.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var class-string<LoginAuditLog> $logModel */
        $logModel = config('login-audit.models.log', LoginAuditLog::class);

        /** @var class-string<LoginAuditSession> $sessionModel */
        $sessionModel = config('login-audit.models.session', LoginAuditSession::class);

        $prunedLogs = (new $logModel)->pruneAll();
        $prunedSessions = (new $sessionModel)->pruneAll();

        $this->info("Pruned {$prunedLogs} login audit log(s) and {$prunedSessions} session(s).");

        return self::SUCCESS;
    }
}
