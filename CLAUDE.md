# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Package Overview

Login Audit (`iresis/login-audit`) is a Laravel package. It listens to Laravel's native auth events (`Login`, `Logout`, `Failed`, `OtherDeviceLogout`) and records them automatically to two tables — `login_audit_logs` (event history) and `login_audit_sessions` (session/device tracking) — with no wiring required in the host app's login flow. Keep the package focused, idiomatic, and easy for Laravel developers to install, test, and maintain.

## Package Conventions

- Use Laravel-native package APIs and the existing service provider shape before adding abstractions.
- Keep package names, namespaces, Composer metadata, publish tags, documentation, and examples aligned with `iresis/login-audit`.
- Add only the files and dependencies needed for the package behavior being implemented.
- Prefer explicit Laravel package code over helper abstractions unless the extension point is real.
- Keep tests focused on observable package behavior through public APIs, service provider wiring, commands, routes, published resources, and documentation promises.
- Resolve `LoginAuditLog`/`LoginAuditSession` through `config('login-audit.models.log'|'session')` rather than referencing the classes directly — this is what lets host apps swap in tenant-aware subclasses (see README's "Multi-tenant applications" section).

## Commands

- Full validation (analyse + lint:check + test:types + test:unit): `composer test`
- Static analysis (Larastan, level 7): `composer analyse`
- Formatting check: `composer lint:check` — apply fixes: `composer lint`
- Type coverage (must stay 100%): `composer test:types`
- All Pest tests: `composer test:unit` (i.e. `vendor/bin/pest --parallel`)
- A single test file: `vendor/bin/pest tests/Feature/AuditsLoginEventsTest.php`
- Tests matching a name: `vendor/bin/pest --filter="record a login"`
- Build the workbench test app: `composer build`
- Serve the workbench app: `composer serve`

Tests run against an ephemeral Testbench/Workbench Laravel app under `workbench/`, not a real host app; `composer build` (and the `prepare`/`test` scripts) regenerate it from `testbench.yaml`.

## Architecture

**Event flow**: `LoginAuditServiceProvider::registerEventListeners()` wires Laravel's four native auth events to listeners in `src/Listeners/`. Each listener is a thin adapter that pulls the `Authenticatable`/guard off the event and delegates to the single `LoginAudit` service (`src/LoginAudit.php`, registered as a singleton). All audit logic — recording, session open/close, revocation, device grouping — lives in that one class; listeners hold no logic themselves.

**Two tables, one write path**: `login_audit_logs` is an append-only row per event, written by `LoginAudit::log()`. `login_audit_sessions` holds one row per framework session: opened on login (`openSession()`), closed on logout (`closeCurrentSession()`) or "logout other devices" (`closeOtherSessions()`). Its `session_id` is keyed to the *post-login* Laravel session ID — Laravel regenerates the session ID during login for fixation protection, so this differs from the ID the browser sent with the login request itself.

**Device fingerprinting**: `Support\DeviceDetails::fromUserAgent()` parses the request's user agent into browser/platform/device-type via regex, with no external dependency. `DeviceDetails::hash()` (browser|platform|device_type) becomes `device_hash` on sessions, which `LoginAudit::devicesFor()` groups by to build `Support\DeviceSummary` value objects.

**Session revocation only reaches outside the request lifecycle for the `database` session driver**: `LoginAudit::revokeSession()` always closes the audit row, but `forgetFrameworkSession()` can only force-invalidate the framework session itself (a raw delete against the sessions table) when `SESSION_DRIVER=database` — other drivers can't be reached from outside their own request.

**Facade and trait are thin wrappers**: `Facades\LoginAudit` resolves the `LoginAudit` singleton. `Concerns\HasLoginAudit` (opt-in on the host's Authenticatable model) adds `loginAudits`/`auditSessions`/`activeAuditSessions` morph relations plus `auditDevices()`, all delegating back to the same service or config-resolved models rather than duplicating logic.

**Pruning is Laravel-native**: both models use `MassPrunable` with a `prunable()` query driven by `config('login-audit.retention.*_days')` (`null` disables pruning via an always-false `whereRaw`). `Console\Commands\PruneCommand` and Laravel's own `model:prune` scheduler both operate against the same query.

**Activity tracking is separate, opt-in middleware**: `Http\Middleware\UpdateSessionActivity` (aliased as `login-audit.activity`) only touches `last_activity_at`, throttled by `config('login-audit.activity_throttle')`; it never opens or closes sessions.

## Local Skills

- `package-scaffold`: use when adding package capabilities or wiring them through the service provider, including commands, migrations, routes, config, views, translations, assets, middleware, publish tags, workbench files, and console-only behavior.
- `package-testing`: use when adding or changing package tests with Pest 4 and Orchestra Testbench.
- `package-release`: use when preparing changelog, release notes, tags, or GitHub release workflow changes.
- `package-compatibility`: use when reviewing code, dependencies, or CI against the PHP and Laravel support matrix.
