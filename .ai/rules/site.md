---
paths:
  - 'resources/js/site/**'
---

# Site

## Public-site behaviour is TS modules on data-* hooks
Add public-site behaviour as a module under `resources/js/site/` wired up from `boot.ts`, and attach it to markup with a `data-*` hook rather than Alpine or an inline script. Every feature must degrade to working HTML, and the module must be importable without touching `document` at module scope so it stays unit-testable alongside a colocated `.test.ts`.
