<?php

declare(strict_types=1);

namespace Iresis\LoginAudit\Listeners;

use Illuminate\Auth\Events\Login;
use Iresis\LoginAudit\LoginAudit;

final class RecordLogin
{
    public function __construct(private readonly LoginAudit $loginAudit) {}

    public function handle(Login $event): void
    {
        $this->loginAudit->recordLogin($event->user, (string) $event->guard);
    }
}
