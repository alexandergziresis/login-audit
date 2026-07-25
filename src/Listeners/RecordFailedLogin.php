<?php

declare(strict_types=1);

namespace Iresis\LoginAudit\Listeners;

use Illuminate\Auth\Events\Failed;
use Iresis\LoginAudit\LoginAudit;

final class RecordFailedLogin
{
    public function __construct(private readonly LoginAudit $loginAudit) {}

    public function handle(Failed $event): void
    {
        $this->loginAudit->recordFailedLogin($event->user, (string) $event->guard, $event->credentials);
    }
}
