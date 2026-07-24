<?php

declare(strict_types=1);

namespace Iresis\LoginAudit\Console\Commands;

use Illuminate\Console\Command;

class LoginAuditCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'login-audit:placeholder';

    /**
     * The command description.
     */
    protected $description = 'Placeholder Artisan command shipped by the package login-audit.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('LoginAudit placeholder command executed.');

        return self::SUCCESS;
    }
}
