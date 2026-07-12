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
- `tests/Tenancy/PostgresRlsTest.php` is the reference test for how policies
  are verified (naming convention, table coverage, hard failure when the
  session var is reset).

## Identification
- Default middleware: `InitializeTenancyByDomainOrSubdomain` (supports both
  bare subdomains and fully custom domains through one middleware).
- Unknown domain/subdomain → `TenantCouldNotBeIdentifiedException` →
  redirected to central home (see `bootstrap/app.php` exception handler).

## Models
- `Tenant` (App\Models\Tenant): UUID PK, `HasDatabase` + `HasDomains`, but no
  actual per-tenant DB is provisioned (RLS-only). `domain()` returns the
  oldest domain.
- `Post` is the reference "tenant-scoped" model: no `BelongsToTenant` trait,
  no global scope — isolation is enforced entirely by RLS, not app code.
  Follow this pattern for new tenant-owned models unless there's a reason
  to scope in PHP as well.
