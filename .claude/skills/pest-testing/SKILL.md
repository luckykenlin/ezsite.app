---
name: pest-testing
description: "Write and organize Pest tests for this project. Activate when adding or modifying tests, when a change needs coverage, when the CI coverage gate (--exactly=100.0) fails, or when deciding where a test belongs. Covers the all-Postgres backend, the type-first tests/ folder taxonomy (Unit vs Feature, mirroring app/ and grouped by concern), naming conventions, the InteractsWithTenancy trait, factory states, datasets, arch tests, the `to array` shape convention, and the 100% coverage requirement."
metadata:
  author: ezsite
---

# Pest Testing

This skill is the **single source of truth** for test layout and conventions.
`CLAUDE.md` and `.claude/docs/tenancy.md` only point here — when the test setup
changes, update this file and leave those as pointers, so the three don't drift.

This project runs Pest with a **hard 100% code-coverage gate**. Every runtime
line must be exercised by a test or `composer test:unit` (and therefore CI)
fails. Before finishing any change to `app/`, add or update tests so coverage
stays at 100%.

**Coverage is a floor, not the goal.** The gate counts lines, not value — cover
each line by exercising real behavior with a real assertion. If a line is only
reachable by contrivance, treat that as a signal to question the code (dead
branch? wrong abstraction?), not a license to paint it green with a hollow
`expect(true)->toBeTrue()`. A defensible defensive branch deserves a test that
documents *why* it exists (see the rollback guard in `RlsPolicyTest`).

## Commands

- `composer test:unit` — the coverage gate CI runs:
  `XDEBUG_MODE=coverage pest --parallel --coverage --exactly=100.0`.
- `php artisan test --compact {path|--filter=...}` — fast iteration on specific
  tests. Prefer a path or `--filter` over the whole suite.
- After editing PHP, run `vendor/bin/pint --dirty --format agent`.

When the coverage run fails it prints `File .. <uncovered lines> / <pct>%` for
each short file (e.g. `Models/Business .. 50, 69 / 80.0%`). The listed line
numbers are exactly what needs covering — write a test that hits those branches.

## One backend: real Postgres + RLS

The **entire** suite runs against a real Postgres database with RLS — the same
engine as production — configured in `tests/Pest.php` and `phpunit.xml`
(`DB_CONNECTION=pgsql`, `DB_DATABASE=ezsite_testing`). There is no sqlite tier.
Each test gets its own `migrate:fresh` (one database per parallel token); the
`InteractsWithTenancy` trait, `freezeTime()`, stray-request/process guards, and
the `database` cache store are applied to every test.

**Why `migrate:fresh` per test and not `RefreshDatabase` transactions?** A
deliberate choice: one uniform lifecycle for the whole suite, and it sidesteps
unverified interactions between RLS (per-connection role switching + the
`my.current_tenant` session variable) and a wrapping transaction. The cost is a
per-test schema rebuild — real but modest at this suite size (a few seconds
parallel). If you ever move non-tenancy tests onto transactions for speed,
**first prove the RLS isolation tests still hold** under that lifecycle before
trusting them.

Key mechanic — **the test connection's role bypasses RLS** (`DB_USERNAME=postgres`,
a superuser; even `FORCE ROW LEVEL SECURITY` is bypassed):

- Tests that do NOT call `tenancy()->initialize()` run as that role → they
  read/write every table freely, RLS is invisible to them.
- Tests that call `tenancy()->initialize($tenant)` switch to the restricted RLS
  role → they observe real per-tenant isolation. `afterEach` ends tenancy so the
  role/session can't leak into the next test.

So "does this test need Postgres?" is never the question — everything is
Postgres. The only question is whether it needs to *initialize tenancy* to
exercise RLS.

> **Hard requirement (load-bearing):** the test DB role MUST be a SUPERUSER or
> have BYPASSRLS. Non-tenancy tests create rows in RLS-protected tables
> (posts/pages/locations) without initializing tenancy and depend on bypassing
> RLS; a non-privileged role makes them fail with opaque policy violations.
> `Feature/Tenancy/DatabaseRoleTest` asserts this invariant so the failure is
> loud and self-explaining instead of mysterious.

## Folder taxonomy (type-first, mirrors `app/`)

```
tests/
├── Concerns/InteractsWithTenancy.php   trait: centralDomain(), actingAsTenantPanelMember(), createTenantHomePage()
├── Datasets/TenantDomains.php          the 'tenant_domains' dataset (subdomain + custom domain)
├── Arch/ConventionsTest.php            arch() rules that enforce CLAUDE.md conventions
├── Unit/                               single class under test, no HTTP/routing
│   └── Models/{Model}Test.php          mirrors app/Models 1:1
└── Feature/                            through the framework (HTTP, Livewire/Filament, events, RLS behavior)
    ├── Filament/
    │   ├── Central/                    central-panel resources (mirrors app/Filament/Resources)
    │   └── Tenant/                     tenant-panel resources (mirrors app/Filament/Tenant)
    └── Tenancy/                        tenancy domain behaviors
        ├── ConfigTest                  model bindings in config
        ├── RlsPolicyTest               policy coverage + sync-on-migrate
        ├── RlsIsolationTest            per-tenant row isolation
        ├── DomainIdentificationTest    subdomain / custom-domain resolution
        └── PanelAccessTest             canAccessPanel gating + tenant sidebar
```

`Pest.php` binds the setup to `->in('Feature', 'Unit')`; `Arch/` runs without the
DB setup (arch expectations need no app/DB).

### Where a test belongs (decision order)

1. Testing one class's logic in isolation (no HTTP/routing)? → `Unit/<mirror
   path>`. Model behavior (relations, casts, slug, soft-delete cascade,
   `toArray()` shape, DB constraints) → `Unit/Models/{Model}Test.php`. These run
   as superuser, so they create tenant-scoped rows without initializing tenancy.
2. A flow through the framework (HTTP, Livewire/Filament, events)? → `Feature/`,
   in the concern subfolder that mirrors `app/` (`Filament/Central`,
   `Filament/Tenant`, `Tenancy/`).
3. RLS isolation / identification / panel gating? → `Feature/Tenancy/`, using
   the trait helpers and `tenancy()->initialize()`.

## Naming

- **One subject under test (SUT) per file, named after it**: `PostResource.php`
  → `PostResourceTest.php`; `Business.php` → `BusinessTest.php`.
- **Cross-cutting behavior with no single SUT → name by the concern, not the
  models involved**: `RlsIsolationTest`, `DomainIdentificationTest`,
  `PanelAccessTest`. Never bundle models into an `XAndYTest`.
- **`test()`/`it()` descriptions state behavior, not method names**: CRUD reads
  `can <verb> a <noun>`; rules read `<subject> <present-tense behavior>` (e.g.
  `a tenant sees only its own posts`). Don't restate the class name.

## Shared helpers, datasets, factory states

- **`Tests\Concerns\InteractsWithTenancy`** (auto-used by every test via
  `Pest.php`): `$this->centralDomain()`, `$this->actingAsTenantPanelMember()` (creates a
  tenant, signs in a member, initializes tenancy, points Filament at the tenant
  panel, returns the tenant), `$this->createTenantHomePage($tenant)`. Put new
  shared tenancy setup here — NOT as free functions in a test file, and NOT in a
  nested `Pest.php` (nested `beforeEach` does not bind in this project).
- **Dataset `tenant_domains`** collapses subdomain-vs-custom-domain cases:
  `it(...)->with('tenant_domains')` yields `[$domain, $isCustom]`.
- **Factory states** cut boilerplate: `User::factory()->superAdmin()`,
  `User::factory()->memberOf($tenant)`, `Tenant::factory()->withDomain('acme')`.

## Architecture tests

`tests/Arch/ConventionsTest.php` turns CLAUDE.md conventions into `arch()`
assertions (strict types everywhere, models & actions `final`, actions expose
`handle()`, no `dd`/`dump`/`ray`). Add an expectation here when you introduce a
new structural convention rather than relying on review.

## The `to array` shape convention

Every model test file includes a `to array` test asserting the exact key order
of `toArray()` — this locks the serialized shape and catches accidental
`$hidden`/`$appends`/column changes. Column order follows the migration.

```php
test('to array', function (): void {
    $business = Business::factory()->create();
    $business = Business::query()->findOrFail($business->getKey()); // re-fetch, don't use the created instance

    expect(array_keys($business->toArray()))->toBe([
        'id', 'tenant_id', 'name', /* ...migration column order... */, 'deleted_at',
    ]);
});
```

Always **re-fetch via `Model::query()->findOrFail($id)`** rather than asserting
on the just-created instance. Freshly saved `Tenant`/`Domain` instances leak a
lazy-loaded `tenant` relation into `toArray()` (VirtualColumn saved-listener),
and `->refresh()` does not clear it — a clean query does. Follow the same
pattern everywhere for consistency.

## Factories

- Always create models through factories; never hand-build with `new`.
- Factories self-provide their FKs — `BusinessFactory` makes its own `Tenant`,
  `LocationFactory` makes its own `Tenant` + `Business` (with matching
  `tenant_id`). Pass explicit ids only when a test needs two models to share a
  tenant: `Location::factory()->create(['tenant_id' => $b->tenant_id, 'business_id' => $b->id])`.
- Models are globally unguarded (`nunomaduro/essentials`), so factory `create`
  with arbitrary attributes works; never add `$fillable`/`#[Fillable]`.

## Covering branches (worked example)

`Business::booted()` has a `deleting` listener that returns early on force-delete:

```php
self::deleting(function (Business $business): void {
    if ($business->isForceDeleting()) {
        return;                       // <- this branch needs its own test
    }
    $business->locations()->delete(); // <- soft-delete cascade branch
});
```

- Soft delete → assert locations are soft-deleted and restore brings them back.
- Force delete → assert the early-return runs; because `locations.business_id`
  is `cascadeOnDelete()`, force-deleting the business removes the rows at the DB
  level, so `Location::withTrashed()->count()` is `0` (they are hard-deleted,
  not soft-deleted). Assert the actual DB behavior, not what you assumed.

Both branches live in `tests/Unit/Models/BusinessTest.php` — they run as the
superuser (no `tenancy()->initialize()`), so RLS never gets in the way.

## Filament

- This project drives Livewire with the **`Livewire::test(...)`** facade (not
  the `livewire()` helper), plus `callAction`/`fillForm`/`assertHasNoFormErrors`.
- **`Livewire::test(SomeResourcePage::class)` instantiates the component
  directly and BYPASSES the panel's `canAccessPanel` HTTP gate.** So a
  central-panel component test can act as a plain `User::factory()->create()` —
  it does NOT need `->superAdmin()`, and the existing central tests don't use it.
- The `canAccessPanel` gate is a separate concern, exercised at the HTTP level
  in `Feature/Tenancy/PanelAccessTest` (via `$this->get('…/admin')`) — that is
  where `->superAdmin()` / `->memberOf($tenant)` actually matter.
- Tenant-panel component tests still call
  `$this->tenant = $this->actingAsTenantPanelMember()` in `beforeEach` — not for
  panel access, but because the component needs tenancy initialized (RLS active)
  and Filament pointed at the tenant panel with the current tenant set.
- Central-panel resource tests → `Feature/Filament/Central/`; tenant-panel
  resource tests → `Feature/Filament/Tenant/`.
