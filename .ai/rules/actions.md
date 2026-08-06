---
paths:
  - 'app/Actions/**'
---

# Actions

## Re-runnable writes use updateOrCreate, not find-then-save
Write singleton and provisioning rows with `updateOrCreate`/`firstOrCreate` keyed on their natural unique columns, so the Action is safe to re-run. Do not fetch a model, mutate it, then call `->save()`.
