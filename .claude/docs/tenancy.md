# Tenancy Internals

## Bootstrappers (config/tenancy.php)
CacheTenancyBootstrapper, FilesystemTenancyBootstrapper, QueueTenancyBootstrapper,
DatabaseSessionBootstrapper, PostgresRLSBootstrapper.

## RLS mechanics
- `PostgresRLSBootstrapper` sets the Postgres session variable
  `my.current_tenant` (config: `tenancy.rls.session_variable_name`) on
  tenancy init; RLS policies filter rows by `tenant_id = current_setting(...)`.
- One shared DB role (`TENANCY_RLS_USERNAME`) runs all tenant-scoped queries;
  there is no per-tenant DB user.
- Reference RLS tests: `tests/Feature/Tenancy/RlsPolicyTest.php` (policy naming +
  table coverage) and `RlsIsolationTest.php` (row isolation, hard failure when
  the session var is reset). For test layout/conventions/backend, the
  `pest-testing` skill is the single source of truth — don't restate them here.

## Identification
- Default middleware: `InitializeTenancyByDomainOrSubdomain` (supports both
  bare subdomains and fully custom domains through one middleware).
- Unknown domain/subdomain → `TenantCouldNotBeIdentifiedException` →
  redirected to central home (see `bootstrap/app.php` exception handler).

## Models
- `Tenant`/`Domain` (App\Models): UUID PK on `Tenant`, `HasDatabase` +
  `HasDomains`, but no actual per-tenant DB is provisioned (RLS-only).
  `domain()` returns the oldest domain. Kept flat under `App\Models` (no
  `Central` sub-namespace) — keep new tenancy models flat too.
- `Post` is the reference "tenant-scoped" model: no `BelongsToTenant` trait,
  no global scope — isolation is enforced entirely by RLS, not app code.
  Follow this pattern for new tenant-owned models unless there's a reason
  to scope in PHP as well.

## Authorization
Panel access is a separate concern from RLS — RLS only scopes rows, not
who can open a panel at all.
- `User::canAccessPanel()`: `central` panel requires `is_super_admin`;
  `tenant` panel requires `is_super_admin` OR membership in the *current*
  tenant via the `users<->tenants` `tenant_user` pivot (`User::tenants()`,
  `Tenant::users()`).
- `tenant_user.tenant_id` has `->comment('no-rls')`, same reasoning as
  `domains.tenant_id`: it must be queryable from the central panel and from
  `canAccessPanel()` regardless of RLS session state.
- Membership is managed via `TenantResource`'s `ManageUsers` page
  (`ManageRelatedRecords` on the `users` relation) rather than a nested
  RelationManager, since `TenantResource` has no Edit/View page.
- A plain `User::factory()->create()` has zero panel access — use a
  super-admin factory state or `$user->tenants()->attach($tenant)` in
  tests/seeders.

## Known gotchas
- **Route collisions**: routes in `routes/web.php` and `routes/tenant.php`
  with the same method+URI silently overwrite each other in Laravel's
  `RouteCollection` if neither has a `->domain()` constraint —
  `tenancy.default_route_mode` does not prevent this, it only classifies
  intent. Wrap central routes that share a path with a tenant route (e.g.
  `/`) in `Route::domain(config('tenancy.identification.central_domains')[0])->group(...)`.
- **`toArray()` relation leak**: `Domain` (and anything using
  `InvalidatesTenantsResolverCache`) lazy-loads and caches its `tenant`
  relation inside a `saved`/`deleting` listener, so a freshly saved instance
  leaks a `tenant` key into `toArray()`/`toJson()`. `->refresh()` does not
  clear it (reloads already-loaded relations) — re-fetch via
  `Model::query()->findOrFail($id)` in shape tests instead.
- **Parallel test leftovers**: the suite creates one Postgres DB per
  parallel-runner token (`ezsite_testing_test_*`), and
  `FilesystemTenancyBootstrapper` creates `storage/tenant{uuid}/...` dirs per
  test tenant. Storage dirs are auto-cleaned in `tests/Pest.php`'s
  `afterEach`; the Postgres DBs are left behind by design (Laravel's own
  parallel-testing default) — drop them with
  `php artisan test --parallel --recreate-databases` if needed.
