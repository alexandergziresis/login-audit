<?php

declare(strict_types=1);

// Mirrors the package's own migration so the workbench app (composer serve)
// has the login-audit tables without requiring a vendor:publish step.
return require __DIR__.'/../../../database/migrations/2026_01_01_000000_create_login_audit_logs_table.php';
