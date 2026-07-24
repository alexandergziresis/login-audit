<?php

declare(strict_types=1);

namespace Iresis\LoginAudit\Tests;

use Iresis\LoginAudit\LoginAuditServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LoginAuditServiceProvider::class,
        ];
    }
}
