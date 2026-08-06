---
paths:
  - 'app/Jobs/**'
---

# Jobs

## Jobs extend TenantAware and carry ids, not models
Extend `TenantAware` rather than implementing `ShouldQueue`, pass `$tenantId` plus scalar ids only, and re-query models inside `handleInTenant()` — deserialization runs before the RLS context is established. Configure retries with the `#[Timeout]`, `#[Tries]` and `#[Backoff]` attributes.
