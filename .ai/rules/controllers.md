---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## One controller, one route, one __invoke
Every controller is a final single-action class with one public `__invoke()` carrying an explicit return type, and no other public methods — split a second route into its own controller rather than adding a method. Keep supporting logic in private methods on the same class, and inject Actions and request-scoped services as `__invoke()` parameters. Return a view, a redirect, or a Response built by a dedicated document class; add a JSON branch only where a real XHR client asks for one, and never introduce an API Resource layer. Central-domain controllers go in `Controllers/Central`, tenant-domain ones at the root of `Controllers`.

## Bind models implicitly; look up by hand only to avoid a 404
Type-hint the model on `__invoke()` and let implicit binding, including `{model:slug}`, resolve and 404 it — RLS already makes another tenant's key invisible, so add no filtering of your own. Only when a missing record must NOT abort the request, take the raw key, resolve it with `->find()`/`->first()`, and decide the outcome explicitly. Do not add `Route::bind` or `resolveRouteBinding()`.
