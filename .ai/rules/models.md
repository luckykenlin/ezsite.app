---
paths:
  - 'app/Models/**'
---

# Models

## Tenant-owned models add RequiresTenantContext
There is no custom base model — extend `Model` or the vendor parent directly. Every tenant-owned model must `use RequiresTenantContext`, which hard-fails a write attempted outside a tenant context; route such writes through `RunInTenant`. Omit the trait on a central model and state in its class docblock that the omission is deliberate.

## Models are globally unguarded — declare no $fillable or $guarded
Never add `$fillable` or `$guarded` to a model; mass assignment is disabled project-wide by the Unguard configurable. Control what gets written at the call site by passing an explicit attribute array. Use `protected $attributes` when a model needs PHP-side column defaults.

## Model side-effects live in booted(), not observers
Register model lifecycle side-effects as closures inside `protected static function booted()` on the model itself; do not create observer classes or use `#[ObservedBy]`. When a hook is shared across models, put it in a bootable trait's `bootXxx()` using `registerModelEvent`. A domain event belongs in an Action that dispatches it, not in a model hook.

## Enum columns
Register every enum-valued column in `casts()` against a `: string`-backed enum in `app/Enums`. The column-side half of this rule (why it is `string()` and not a database `enum()`) is in `.ai/rules/migrations.md` — canonical home, don't restate it here.
