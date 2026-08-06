---
name: pest-testing
description: "Write and organize Pest 5 tests for this project. Activate whenever a test is written, edited, fixed, or refactored — including tests that broke after a code change, adding assertions or datasets, converting PHPUnit to Pest, TDD, browser/smoke tests, arch tests, and when the CI coverage gate (--exactly=100.0) fails or you're deciding where a test belongs. Covers the all-Postgres backend, the type-first tests/ folder taxonomy (Unit vs Feature, mirroring app/ and grouped by concern), naming conventions, the InteractsWithTenancy trait, factory states, datasets, arch tests, Livewire/Filament component tests, Tia (--tia), the per-model test template (relations, side-effects, casts, constraints, `to array`), and the 100% coverage requirement. Do not use for factories, seeders, migrations, controllers, models, or non-test PHP code."
metadata:
  author: ezsite
---

# Pest Testing

This skill is the **single source of truth** for test layout and conventions.
`CLAUDE.md` and `.claude/docs/tenancy.md` only point here — when the test setup
changes, update this file and leave those as pointers, so the three don't drift.

> Maintenance note: this file is **project-authored** and its home is
> `.ai/skills/pest-testing/SKILL.md`. `.claude/skills/pest-testing` is a symlink
> Boost creates; edit the `.ai/` copy. Living here is what stops
> `php artisan boost:update` from overwriting us with the stock Pest skill —
> Boost discovers `.ai/skills/*` last and a user skill wins. Do not move it back
> under `.claude/skills/`; see `.ai/rules/skills.md`.

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
  `XDEBUG_MODE=coverage pest --parallel --processes=10 --coverage --exactly=100.0 --tia --exclude-testsuite=Browser`.
- `php artisan test --compact {path|--filter=...}` — fast iteration on specific
  tests. Prefer a path or `--filter` over the whole suite.
- `composer test:browser` — the browser suite (see below). Excluded from
  `test:unit` and `test:type-coverage` with `--exclude-testsuite=Browser`;
  keep it that way.
- `composer test:tia:clear` — purge the Tia graph (see the Tia gotcha below).
- `npm run test:unit` — vitest over `resources/js/**/*.test.ts`.
- After editing PHP, run `vendor/bin/pint --dirty --format agent`.

When the coverage run fails it prints `File .. <uncovered lines> / <pct>%` for
each short file (e.g. `Models/Business .. 50, 69 / 80.0%`). The listed line
numbers are exactly what needs covering — write a test that hits those branches.

### Gotcha: `--tia` reports false coverage drops after a refactor

The Tia (test impact analysis) graph lives **outside the repo**, in
`~/.pest/tia/<project-hash>/`, so it never shows in `git status`. After files
move, get renamed, change namespace, or gain/lose tests, the graph goes stale
and the gate reports a **false** coverage drop while every test still passes
(seen twice: `91.3%` then `88.9%` where the truth was `100.0%` both times).

The files Tia lists as under-covered are exactly the ones whose test mapping
changed. Confirm with a non-Tia run — if that says 100% and `--tia` does not,
the graph is stale, not the tests — then fix it with `composer test:tia:clear`
(or `rm -rf ~/.pest`). The next run prints a fresh-graph notice and the real
number.

### Creating test files

`php artisan make:test --pest {name}` — `{name}` must **not** repeat the suite
directory, or it nests:

- Wrong: `make:test --pest Feature/PostResourceTest` → `tests/Feature/Feature/…`
- Right: `make:test --pest PostResourceTest` → `tests/Feature/PostResourceTest.php`
- Right: `make:test --pest --unit BusinessTest` → `tests/Unit/BusinessTest.php`

Then move it into the correct concern subfolder (see the taxonomy below); the
generator only knows about the two suite roots.

**Do NOT delete tests without approval** — they are core application code.

## One backend: real Postgres + RLS

The **entire** suite runs against a real Postgres database with RLS — the same
engine as production — configured in `tests/Pest.php` and `phpunit.xml`
(`DB_CONNECTION=pgsql`, `DB_DATABASE=ezsite_testing`). There is no sqlite tier.
Each test gets an empty database (one database per parallel token): the schema
is migrated **once per worker process** and every later test starts with a
`TRUNCATE ... RESTART IDENTITY CASCADE` over every table but `migrations`. The
`InteractsWithTenancy` trait, `freezeTime()`, stray-request/process guards, and
the `database` cache store are applied to every test.

**Do not reach for `RefreshDatabase`.** The generic Pest/Laravel advice to use
it does not apply here — this project's `Pest.php` owns the database lifecycle,
and the reasons are below.

**Why truncation, and not `migrate:fresh` per test or `RefreshDatabase`
transactions?** It used to be `migrate:fresh` per test, which cost ~250ms of
setup — 18 migrations plus the full `tenants:rls` regeneration its
`MigrationsEnded` listener fires — and that, not the tests, was almost the
entire runtime: the `test:unit` gate went from ~290s to ~130s for the same 2126
tests (239s → 55s with coverage off, so Xdebug's overhead was never the
problem).
Transactions are the other obvious option and are **wrong here**: tenancy runs
its queries on a separate connection under the restricted RLS role, so a
transaction opened on the superuser connection would neither cover nor roll back
what that one writes, and the isolation tests would quietly stop testing
anything. Truncation is connection-agnostic, so the RLS lifecycle is unchanged.

Two invariants keep this safe, because the schema now outlives a test:

- **No migration may seed rows** — they would be truncated away, so anything a
  test expects to exist must come from a factory or a seeder it calls itself.
- **No test may create its own table** — it would survive into every later test
  in that worker (and trip `RlsPolicyTest`'s "every table is RLS-protected"
  guard). Assert against the real schema instead.

**`--processes` is capped by Postgres, not by CPU** — which is why `test:unit`
pins it explicitly. Peak connection count is worker-bound, not test-bound: each
worker holds pgsql + tenant + tenant_host + database-cache connections, and all
workers spike together at startup. Past the server's `max_connections` the suite
dies with `FATAL: sorry, too many clients already`.

The pin has moved, so **read the current value out of `composer.json` rather than
quoting a number from memory**. History: at 8 workers the peak was 96/100 and 9+
blew through it; the per-worker connection cost then dropped (`a0ceaab` replaced
`migrate:fresh`-per-test with migrate-once-per-worker + truncate), and `10` now
passes clean at `max_connections = 100` (verified 2026-08-06: 2126 tests, 100.0%,
~107s). Untested above 10. If `too many clients` ever returns, the durable fix is
raising `max_connections` (200), not shaving workers — but re-measure before
raising the pin again, because the ceiling is a property of the current lifecycle,
not a constant.

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
- **Match the surrounding file's choice of `test()` vs `it()`** rather than
  switching styles mid-directory.

## Assertion style

- Prefer the specific response assertion over `assertStatus()`:
  `assertSuccessful()` not `assertStatus(200)`, `assertNotFound()` not
  `assertStatus(404)`, `assertForbidden()` not `assertStatus(403)`.
- Prefer a Pest 5 validation matcher over a hand-rolled regex where one exists
  (all support `.not`): `toBeEmail()`, `toBeUlid()`, `toBeIpAddress()`,
  `toBeMacAddress()`, `toBeHostname()`, `toBeDomain()`, `toBeBase64()`,
  `toBeHexadecimal()`. `toBeUlid()`/`toBeDomain()` are the useful ones here —
  tenant ids and `Domain->domain`.
- **Don't reach for Mockery to double a first-party class.** Write an anonymous
  class implementing its interface and bind it with `app()->instance()`; hoist a
  double used by more than one file into a `tests/Concerns` trait
  (`MakesStockPhotos` is the reference). Prefer a class's own static `::fake()`
  where it has one (`PageEditorAgent::fake([...])`). Mockery is for framework
  contracts you cannot cheaply implement.
- Reach for a dataset whenever the same body repeats over inputs (validation
  rules, the `tenant_domains` subdomain/custom-domain pair). Datasets are also
  how you cover two tenants — see `one tenant per test` below.

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

## The browser suite (`tests/Browser`)

A real Chromium over the compiled bundle, for the editor's TypeScript. Small on
purpose — it covers only what PHP structurally cannot reach (the iframe
postMessage round trips, native drag-and-drop, Alpine surviving a Livewire
morph, the canvas's localStorage layout). Never re-assert there what
`PageEditorTest` already asserts server-side.

Two tiers sit below it: `tsc`/eslint, and vitest (`npm run test:unit`) for the
pure functions — `protocol.ts`'s shortcut table and origin check,
`page-canvas/view-state.ts`'s storage fallbacks. Anything testable without a
document belongs in vitest, not here. There is deliberately no jsdom: a DOM-less
`environment: 'node'` plus a hand-written stub is what keeps the two tiers from
blurring.

**The one rule that matters when writing these: assert on the PARENT window.**
The canvas mutates its own DOM optimistically — dragover reorders blocks before
any message is sent, and typing into a `contenteditable` changes the text
whether or not the edit is committed. Reading either back out of the iframe
passes even when the bridge is severed. Assert something only the editor could
know: the inspector's field value, the Save button going dirty, a notification.
Both bridge tests were verified by cutting the `post()` call and watching them
go red.

Always include `->assertNoJavaScriptErrors()` in a browser test — a page that
renders but throws is the failure mode these tests exist to catch. The generic
`visit([...])` smoke-test form (`$pages->assertNoJavaScriptErrors()
->assertNoConsoleLogs()`) is available if a batch of public pages ever needs a
cheap sweep, but the current suite is targeted, not a smoke suite.

Harness facts, each of which cost something to find:

- **`pest()->extend(...)->in('Browser')` lives in the root `tests/Pest.php`**,
  next to the Feature/Unit binding, sharing one `$prepareDatabase` closure. A
  nested `tests/Browser/Pest.php` does not bind (same rule as everywhere else
  here).
- **The plugin's HTTP server is in-process** — it builds a Symfony request and
  hands it to this very application instance, so the browser shares the test's
  database, config and container. `actingAs()` therefore works.
- **Four things the Feature/Unit binding does are omitted**: `withoutVite()`
  (these tests need the real `public/build/manifest.json` — `npm run build`
  first), `freezeTime()`/`Sleep::fake()` (they hang polling and SSE), and the
  stray request/process guards (Playwright is a real process on a socket).
  Sessions move to the `database` driver, because the browser holds a cookie.
- **Tenant subdomains work through `*.localhost`**: Chromium resolves it to
  loopback itself, and the server reads the Host header. `VisitsTenantPages`
  builds those URLs — and then pins **two** origins, because the plugin rebuilds
  every request from the server's own `127.0.0.1` address:
  - `URL::forceRootUrl()` for `url()`/`route()`, without which the preview
    iframe lands on a different origin than the editor and fails every check in
    `protocol.ts`.
  - `URL::useAssetOrigin()` for `asset()`, which does **not** follow the root:
    `LaravelHttpServer::bootstrap()` sets an asset origin of its own and
    `asset()` prefers it. Leave it and `@vite` emits the builder's entries
    cross-origin — harmless for Filament's classic `<script>` tags, fatal for
    `type="module"`, which is fetched under CORS and silently dropped. Nothing
    registers `pageCanvas`/`pageEditor`, every `x-data` on the page throws
    "not defined", and the canvas renders every card stacked at one point.
- **A browser suite that HANGS means an element is covered, not slow.** The
  actionability wait behind `hover()`/`drag()` never times out here, so pest
  sits in `stream_select()` at 0% CPU forever and CI never fails, it just stops.
  Two things tell you where you are: `pg_stat_activity` on `ezsite_testing`
  shows a single RLS `GRANT` (one `beforeEach` ran, so it is the first test) and
  nothing but Filament's notifications poll after it. Progress output is no help
  — pest writes it only as each test finishes, so a hung run prints nothing;
  `fwrite(STDERR, ...)` between steps in a scratch test is how you bisect it.
- **`composer test:browser` wraps pest in `ulimit -n 1024`.** amphp's event loop
  uses `stream_select()`, which cannot see a file descriptor numbered above
  1023; macOS's soft limit is in the millions, so PHP hands out high numbers and
  the server dies on its first request. Linux CI's default is already 1024.

## Architecture tests

`tests/Arch/ConventionsTest.php` turns CLAUDE.md conventions into `arch()`
assertions (strict types everywhere, models & actions `final`, actions expose
`handle()`, no `dd`/`dump`/`ray`). Add an expectation here when you introduce a
new structural convention rather than relying on review.

## What a test should assert (four standing conventions)

Derived from full audits of the suite; each one names the shape that keeps a
test from degrading into a change detector.

**1. Enum tests assert structural invariants over `::cases()`, never literal
values.** Iterate every case and assert the shape holds — the full variable set
is emitted, values match the expected format, an ordered scale grows
monotonically, a declared default exists in the referenced vocabulary. A new
case is then covered automatically; a test that copies each constant is a
change detector that a new case silently escapes. See
`Unit/Design/TokenScalesTest` (radius/spacing scales), `ColorPaletteTest`
("every palette emits all 12 variables"), and `StylePresetTest` (every preset's
`blockVariantDefaults()` cross-checked against `BlockRegistry::vocabulary()` —
a typo there would silently fall back to the wrong layout).

**2. A `scoped` container binding is guarded by its consequence, not its
identity.** `expect(resolve(X))->toBe(resolve(X))` passes identically for
`scoped`, `singleton` and any already-resolved instance, so it cannot catch the
regression that matters: dropping to `bind()` gives every caller its own
instance, and the memoization those classes exist for disappears. The visible
damage is an N+1 on every public page render — `BindResolver` re-queries the
business and locations once per bound block, `SiteChrome` once per chrome slot.
Assert the query count through a real render instead:
`Feature/Filament/Fabricator/BindResolutionTest` (one businesses + one locations
query per page) and `SiteChromeRenderTest`'s "single settings query" test. Both
fail when the binding degrades; the identity assertion does not.

(`scoped` rather than `singleton` is about long-lived processes: the container
only forgets scoped instances in the queue worker between jobs
(`QueueServiceProvider`), and under Octane between requests. Nothing in this app
resolves these two classes off the render path today, so the distinction is
currently defensive — it becomes load-bearing the moment a job renders a page or
reads tenant business data, where a `singleton` would carry one tenant's
memoized rows into the next tenant's job.)

**3. A shared primitive gets a test named after itself.** When several classes
delegate to one helper, test the helper directly and parameterize the
consumers in a single dataset — do not restate its behavior once per consumer.
`Unit/Actions/Pages/FindPageBlockIndexTest` is the reference: one dataset
invokes all four key-addressed actions, so each delegation is still exercised
while the contract lives under the primitive's own name.

**4. Never re-compute the production expression; assert literals or invariants.**
`expect(AddBlock::types())->toBe(array_values(array_diff(array_keys(
BlockRegistry::vocabulary()), ['header', 'footer'])))` restates the method body,
so it passes by construction and can never fail. Write what the answer *is*
(`->toContain('hero', 'features')->not->toContain('header')`) or an invariant
that survives a rewrite (`->toHaveCount(count(StylePreset::cases()))`). The
same rule kills `->and($tool->description())->not->toBeEmpty()`: a description
exists to carry ONE load-bearing instruction to the model — pin that clause
(`->toContain('Send only the fields you want to change')`), not its length.
Prompt/instruction copy is otherwise free to evolve.

### Gotcha: scoped instances survive between `$this->get()` calls

Nothing flushes scoped instances at an HTTP request boundary. Under php-fpm none
is needed — each request is a fresh process with a fresh container, so isolation
comes from the process dying. The test harness instead reuses one application
across every request in a test.

So a test that renders, **mutates the data, and renders again** replays the first
render's memoized `BindResolver` / `SiteChrome` and asserts stale values. Prefer
one render per test — that is what production does, and it keeps the test free of
container-lifecycle knowledge. If a test genuinely needs two renders of changed
data, `$this->app->forgetScopedInstances()` between them reproduces the fresh
container a real second request would get.

### Gotcha: one *domained, rendered* tenant per test

A test that renders a tenant site gets ONE tenant. A second
`Tenant::factory()->withDomain('acme')` in the same test throws "The acme domain
is occupied by another tenant" (stancl's `EnsuresDomainIsNotOccupied`), and
giving the second a *different* subdomain gets past that but then **403**s the
request, because the first render's tenancy context is still in play. This bites
whenever you call a `Feature/Filament/Fabricator/*` render helper twice to
compare two outputs (two style presets, linked vs unlinked, before vs after).

Compare via a **dataset** (`->with([...])`) so each case is its own test with its
own tenant. This is a *domain + HTTP render* constraint, not a rule about
`tenancy()->initialize()` — `RlsIsolationTest` legitimately switches between two
domainless tenants inside one test, which is exactly how it proves isolation.

## Model tests (the per-model template)

**Every model in `app/Models` gets a `tests/Unit/Models/{Model}Test.php`.** The
model test file is the canonical, complete home for everything the model does —
coverage that happens to exist incidentally elsewhere (an inline assert in a
feature test) does not replace it. In particular, **relation tests are required
even when the relation is a one-line `belongsTo`/`hasMany`**; never remove them
as "framework behavior" or "redundant coverage".

Cover, in this order:

1. **Relations** — one test per relation, asserting the wiring end-to-end:
   `$model->relation->is($expected)` for to-one, attach + assert membership for
   pivots (`$user->memberWorkspaces->pluck('id')->all()`-style).
2. **Creation side-effects** — generated slugs/codes, defaults, `booted()`
   listeners (e.g. Business's soft-delete cascade, covered branch by branch —
   see the worked example below).
3. **Domain methods & casts** — predicates like `isExpired()`, value-object
   casts (`opening_hours`), custom route keys (`getRouteKeyName`).
4. **DB constraints the model relies on** — unique indexes, NULLS NOT DISTINCT
   (create the conflicting row and assert the `QueryException`).
5. **`to array` last** — the exact key order of `toArray()`, locking the
   serialized shape against `$hidden`/`$appends`/column drift. Column order
   follows the migration.

```php
<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Tenant;

test('tenant relation returns the owning tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $business = $this->runInTenant($tenant, fn (): Business => Business::factory()->create(['tenant_id' => $tenant->id]));

    expect($business->tenant->is($tenant))->toBeTrue();
});

test('slug is auto-generated from name via laravel-sluggable', function (): void {
    // creation side-effect: assert the generated value, not just non-null
});

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
pattern everywhere for consistency (RLS-scoped models also need the re-fetch to
land on the central connection after `runInTenant`).

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
