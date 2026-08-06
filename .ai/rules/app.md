---
paths:
  - 'app/**'
---

# App

## Actions are the only behaviour layer — never instantiate one
Put imperative business logic in an `app/Actions` class: `final readonly`, exactly one `handle()`, in a feature subdirectory (or the root when it is site-wide). Never add a Service, Repository, Query, Manager, Policy or Observer layer — those directories do not exist and should not be created. Obtain an Action from the container by type-hint, never with `new`.

## Type-hint dependencies; resolve() only where injection is impossible
Get dependencies by type-hinting them on a constructor, a controller `__invoke()`, or a Filament action closure. Only when the class cannot receive them — a Filament page, a Livewire component, a queued job — call `resolve(Thing::class)`. Never use `app()`.

## Value objects are hand-rolled readonly classes in the owning namespace
Model a structured value as a `final readonly class` with promoted public constructor properties, placed in the feature namespace that owns the concept — not a DTO package, not an `app/Data` folder, not a loose array. Give it static named constructors. Reserve a non-readonly `final class` for objects that memoize or accumulate state within a single request or AI turn.

## Feature roots hold vocabulary; Actions hold behaviour; Models stay flat
Give a new domain concept its own top-level `App\<Domain>` namespace holding its enums, readonly value objects and pure logic — no `Service`/`Manager`/`Helper` suffixes, and no nested `Actions/` or `Models/` inside it. Eloquent models stay flat in `App\Models` and imperative logic stays in `App\Actions`.

## Wire things up by direct call, not events
Call the next Action or dispatch the Job directly. Introduce an event only to invert a layering dependency — so a domain Action need not import the Filament panel, for instance — and state that reason in the event's docblock.

## Open every class with a why-docblock
Give each class a docblock stating what it is for, the decision it encodes, and the plausible alternative that was rejected and why. Do not restate what the code obviously does.

## Every enum is string-backed and carries its own behaviour
Declare enums as `enum Name: string` with TitleCase cases — never pure, never int-backed. Put the label, colour and derived-value logic on the enum itself, including Filament's `HasLabel`/`HasColor`, rather than in a match statement at the call site.

## Plain arrays and foreach over collection pipelines
Transform plain arrays with `foreach`, `array_map` and `array_filter`, returning arrays rather than Collections. Reach for `collect()` only for a short in-place chain over something like `Enum::cases()`, not as the default iteration style.

## Static Str:: helpers, not the fluent Stringable
Call string helpers statically, as in `Str::slug($value)`; do not start `Str::of()` or `str()` chains. A cast-and-transform in one expression is the only place fluent is acceptable, and even there prefer the static call when one step suffices.

## Named routes, relative model URLs, PublicUrl for absolute
Link to central and panel surfaces by named route (`route('central.…')`, `Resource::getUrl()`); never use `action([...])`. A model's `getUrl()` must return a relative path — reach an absolute tenant address through `PublicUrl` and a cross-tenant one through the tenant's `Domain::getUrl()`, because a bare `url()` outside a request resolves against the central domain.
