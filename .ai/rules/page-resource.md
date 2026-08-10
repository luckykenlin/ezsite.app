---
paths:
  - 'app/Filament/Tenant/Resources/PageResource/**'
---

# Page Resource

## The editBlock drawer is chrome around the page-bound blockForm — never an action schema
EditBlockAction is a click-through slideOver (`->modalClickThrough()`) whose `->modalContent()` renders the PAGE-bound `blockForm`; state stays at `data.block.*`. Moving the fields into the action's own `->schema()` would relocate state to `mountedActions.*.data` and silently break four paths that read `data.block`: `updated()`'s per-keystroke canvas patch, `BuildEditorPreviewDraft`'s uncommitted-draft merge, the inline canvas editor's `$wire.set('data.block.*')`, and `RestoresEditorDraft`. Commits stay lazy via `commitSelectedBlock()` — the drawer has no submit on purpose.
