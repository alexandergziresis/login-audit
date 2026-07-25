<?php

declare(strict_types=1);

namespace Iresis\LoginAudit\Support;

use Illuminate\Support\Carbon;

final class DeviceSummary
{
    public function __construct(
        public readonly string $deviceHash,
        public readonly ?string $browser,
        public readonly ?string $platform,
        public readonly ?string $deviceType,
        public readonly ?Carbon $firstSeenAt,
        public readonly ?Carbon $lastSeenAt,
        public readonly int $sessionsCount,
        public readonly int $activeSessionsCount,
    ) {}
}
