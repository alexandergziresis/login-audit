<?php

declare(strict_types=1);

namespace Iresis\LoginAudit\Listeners;

use Illuminate\Auth\Events\OtherDeviceLogout;
use Iresis\LoginAudit\LoginAudit;

final class RecordOtherDeviceLogout
{
    public function __construct(private readonly LoginAudit $loginAudit) {}

    public function handle(OtherDeviceLogout $event): void
    {
        $this->loginAudit->recordOtherDeviceLogout($event->user, (string) $event->guard);
    }
}
