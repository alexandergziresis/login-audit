<?php

declare(strict_types=1);

namespace Iresis\LoginAudit\Listeners;

use Illuminate\Auth\Events\Logout;
use Iresis\LoginAudit\LoginAudit;

final class RecordLogout
{
    public function __construct(private readonly LoginAudit $loginAudit) {}

    public function handle(Logout $event): void
    {
        if ($event->user === null) {
            return;
        }

        $this->loginAudit->recordLogout($event->user, (string) $event->guard);
    }
}
