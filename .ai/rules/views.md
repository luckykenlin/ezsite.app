---
paths:
  - 'resources/views/**'
---

# Views

## Server-rendered Blade, no SPA layer
Render every user-facing surface as server-side Blade — public tenant pages through Fabricator blocks, admin through Filament panels. Do not introduce Inertia, a client router, or a JS component framework; reach for Livewire only when a field genuinely needs to react while someone types.

## Anonymous Blade components, no component classes
Build every reusable view as an anonymous component under `resources/views/components/` with an explicit `@props([...])` list; there is no `app/View/Components` and none should be added. Reserve `@include` for splitting a single large panel page into partials that intentionally share the parent's scope.

## Never hand-build a URL in a template
Link by named route (`route('central.…')`, `Resource::getUrl()`), never `action([...])` and never a bare `url()`. The full rule — relative `getUrl()`, `PublicUrl` for absolute tenant addresses, `Domain::getUrl()` cross-tenant, and why a bare `url()` resolves against the central domain — is in `.ai/rules/app.md`, which also matches when you touch the PHP side.
