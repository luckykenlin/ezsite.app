---
paths:
  - 'routes/**'
---

# Routes

## Every route points at a class, even the one-liners
Register routes against an invokable controller or a Livewire component class — `robots.txt` and `sitemap.xml` included. A closure is acceptable only for a pure host-level redirect. Keep central-domain routes in `routes/web.php` inside the `->domain()` group and tenant routes in `routes/tenant.php`.

## Assign middleware on the route, never globally
Attach middleware with `Route::middleware()` on the group or the individual route, referencing it by `::class` rather than registering an alias; leave the `withMiddleware()` closure in `bootstrap/app.php` empty. Do not implement `HasMiddleware` or use the `#[Middleware]` attribute. Widening a middleware to the `web` group hits tenant requests too, so scope it to the group that needs it.

## Routes carry their own gate; controllers assume authorization
Put the gate in the route definition — `signed:relative` for a shareable link, `throttle:<max>,<minutes>` for a visitor-facing POST or scan endpoint, with a comment justifying the number in visitor terms — and let the controller assume it is already authorized. Do not define a named `RateLimiter::for()` limiter; outside the route layer, call the RateLimiter facade directly with your own key and read the limits from config.
