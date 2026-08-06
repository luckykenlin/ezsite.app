# Industry Templates + Demo Sites + Central Landing Page Roadmap

> Drafted 2026-08-02, all four phases shipped 2026-08-02. Kept as the record of what was built and why the decisions below were made; the progress table at the bottom is the index.

## Background and goal

Move the product toward self-serve acquisition: build a demo site for each of 8 industries (Chinese restaurant, nail salon, massage/spa, personal resume, designer portfolio, burger joint, pizza shop, bubble tea); turn the central-domain home page into a landing page where visitors learn what the product does, browse the templates, and apply one with a single click — a guided form collects their content, and a site is generated for them (images filled from the photo library).

**Product decisions already made** (do not reopen during implementation):

- Templates are **hand-curated** (PHP definitions, not AI-generated on the fly) — the closed design system is the moat and templates are its extension.
- The guided form **creates the account inline**: submitting creates the user + tenant + site, and a signed URL auto-logs them into the editor.
- Copy is **English-first**, no i18n toggle.
- **Instant provisioning + rate limiting**: no email verification, no manual approval (mail infrastructure comes later).
- The wizard uses the **three-step, industry-fields-with-skip** shape.
- The landing page has **no pricing and no testimonials** (neither exists yet).

## Research findings (cite directly during implementation; do not re-explore)

- Central domain today: `routes/web.php` has a single `Route::view('central.welcome')` placeholder; no public registration; no mail infrastructure (mailer=log, zero Mailables); no central robots/sitemap (the existing `RobotsController`/`SitemapController` are registered in the tenant stack). `resources/views/welcome.blade.php` is dead code — safe to delete.
- The reusable pipeline already exists: `SiteDraftValidator` (hand-written templates go through the same validation; `image_query` only survives when `stock-photos.enabled=true`), `StampPresetDefaults::fill()`, `ApplyStylePreset`, `SaveSiteChrome` (only stamps nav when header is null), `PopulateDraftImages` (**walks draft pages only — must run before publish**), `AdoptLibraryPhoto` (idempotent per library_photo_id), the `library:import` command, `CreateTenant` (creates only tenant + subdomain; Business/pages/users all empty), and `SharePreviewAction`'s `signed:relative` cross-domain signature pattern (copy it for auto-login).
- `GenerateSiteDraft`'s persistence half (`upsertPage()`/`stampNavigation()`) is welded to the agent call — Phase 1 extracts it for shared use.
- The blocks' `$sample` property + `PageEditorPreviewController`'s `?sample=` preview are the existing "sample content → real block" transform and single-block thumbnail mechanism.
- `CreatePageFromName` can never yield the `/` slug — the home page must be created directly via `Page::create(['slug' => '/'])`, exactly as `upsertPage()` does.
- The browser suite's `*.localhost` hostname trick (`tests/Concerns/VisitsTenantPages.php`) works as-is for the screenshot script.

## Phase 1 — Template infrastructure (~15 files, 2–3 days)

1. **Extract `app/Actions/ApplySiteDraft.php`**: move `GenerateSiteDraft`'s transaction body + `upsertPage()` + `stampNavigation()` verbatim; signature `handle(Business $business, array $draft, bool $overwritePublished = false): Page` (`$draft` is the validator **output** shape). `GenerateSiteDraft` keeps `requestDraft()` and the published-home refusal guard, and delegates persistence. **Ship as its own PR** to isolate the AI-draft regression surface.
2. **Template artifacts**:
   - `app/Templates/SiteTemplate.php`: string-backed enum, 8 cases (`chinese-restaurant`, `nail-salon`, `massage-spa`, `personal-resume`, `designer-portfolio`, `burger-joint`, `pizza-shop`, `bubble-tea`); methods `definition()` / `label()` / `demoSubdomain()` (`'demo-'.$value`) / `description()`.
   - `app/Templates/TemplateDefinition.php`: readonly DTO — `preset` + token overrides, three brand hexes, business `category`, chrome (header/footer block shape), `pages` (**SiteDraftValidator input shape**: flat variant/tone/spacing sibling keys, unprefixed `image_query` inside `data`, home `/` must contain a hero and ≥3 blocks, extra pages limited to `/about|/services|/contact`), `photoQueries` (query + PhotoOrientation + PhotoCategory + count), `demoProfile` (the demo site's Business fields + one Location for bind-capable blocks), `extraFields` (list of `TemplateField` DTOs driving wizard step 2).
   - `app/Templates/Definitions/` — 8 classes; ship 2 pilots first to prove the shape: ChineseRestaurant (bind-heavy, local-business type) + DesignerPortfolio (gallery/media-heavy).
3. **`app/Actions/Templates/FillTemplatePlaceholders.php`**: recursively replaces `{business_name}`/`{tagline}`/`{city}`/`{phone}`/`{email}` + extraFields keys in every string inside `data`; unfilled fields fall back to the `demoProfile` value (a literal `{city}` must never reach a page); runs **before** `SiteDraftValidator`.
4. **Tests**: `SiteTemplateTest` (dataset over all 8 cases: passes the validator with zero dropped blocks; hero/block-count/variant/axis/hex/photoQueries/subdomain-uniqueness assertions; run with `stock-photos.enabled=true`); `FillTemplatePlaceholdersTest`; `ApplySiteDraftTest` (port GenerateSiteDraft's existing persistence assertions + the overwritePublished behavior).

## Phase 2 — Demo tenants (~8 files + 6 template definitions, 2–3 days; copywriting is the real cost)

1. Migration: add `template` (nullable string — recorded for demos AND for user sites created via apply, for attribution) + `is_demo` (bool, indexed) to `tenants`. Central anchor table — no RLS involvement. Add casts + a `demo()` scope to `Tenant`.
2. `config/templates.php`: reserved-subdomain list (www/admin/app/api/mail/demo/ezsite/central/status/docs…) + rate-limit config.
3. `app/Actions/Templates/ValidateSubdomain.php`: slugifies, enforces `^[a-z0-9]([a-z0-9-]{1,61}[a-z0-9])?$`, rejects reserved words / the `demo-` prefix / taken Domains (including FQDN prefixes), throws a field-keyed ValidationException. `CreateTenant` gains optional params `?string $subdomain`, `?SiteTemplate $template`, `bool $isDemo = false`.
4. `app/Actions/Templates/ProvisionDemoSite.php` + `php artisan demo:seed {template?*} {--skip-photos}`, idempotent: find-or-create the demo tenant → pre-warm the library per `photoQueries` (`StockPhotoProvider::search` → `FindOrImportLibraryPhoto`; NullProvider degrades silently) → inside `RunInTenant`: `Business::updateOrCreate` (demoProfile + brand hexes + **the full merged preset+overrides design_tokens** — do not call bare ApplyStylePreset, it drops the overrides) → Location → `SaveSiteChrome` → placeholder fill → validator → `ApplySiteDraft(overwritePublished: true)` → `PopulateDraftImages` (synchronous, **before publish**) → `PublishPage` for every page. Re-running overwrites the same tenant with no duplicates. Demo tenants never get users attached (`canAccessPanel` already restricts them to super admins).
5. Append a `demo:seed` call to `DatabaseSeeder` (dev convenience).
6. Author the remaining 6 `Definitions/`. Art direction (every preset used at least once; WarmCraft used twice with different token overrides — "one system, two different restaurants" is itself a selling point):

| Industry | Preset (+overrides) | Hero angle |
|---|---|---|
| Chinese restaurant | WarmCraft (ElegantSerif/WarmSand) | Full-bleed banquet-table photo, inverted: three generations of home-style cooking |
| Pizza shop | WarmCraft + Sunset palette + FriendlyRounded | Wood-fired-oven close-up, neighborhood-pizzeria warmth |
| Burger joint | PlayfulFriendly | Big type over a stacked-burger shot, loud & casual |
| Bubble tea | FreshModern | Pastel product shot + geometric layout, seasonal menu forward |
| Nail salon | NightLounge (its vibes literally include 'salon') | Dark room with gold accents, nail-art gallery right under the hero |
| Massage/spa | CalmCoastal | Airy tall hero, one calm CTA |
| Personal resume | ProfessionalMinimal | Type-only hero, no photo: name / title / one line |
| Designer portfolio | BoldEditorial | Magazine-style plum hero, work grid in the first viewport |

7. Tests: `ProvisionDemoSiteTest` (NullProvider + LibraryPhotoFactory; idempotency = second run creates zero duplicates), `SeedDemoSitesTest`, `ValidateSubdomainTest`, Tenant model test additions for the casts/scope.

## Phase 3 — Central landing + template gallery (2–3 days; depends on Phase 2's demo sites)

1. **Routes**: inside the existing `Route::domain()` loop in `routes/web.php`, add `/`, `/templates`, `/templates/{template}` (enum-bound), `/start/{template}`, `/robots.txt`, `/sitemap.xml`. New namespace `app/Http/Controllers/Central/` (every existing controller is tenant-stack-only — the separation is deliberate); all invokable readonly. **No static `public/robots.txt`** (static files are served before routing and would shadow the tenant RobotsController). Delete the dead `resources/views/welcome.blade.php`; replace `central/welcome.blade.php`.
2. **CSS: reuse site.css (dogfooding is the pitch)** — the central layout loads `@vite(['resources/css/site.css'])`; theme variables come from `ThemeVariables` constructed directly with a fixed preset (e.g. FreshModern) — the tenant-null bail-out lives in the render hook, not the class. Add one line to site.css: `@source '../views/central/**/*.blade.php'`, plus a matching guard in ConventionsTest. All fonts are already bundled.
3. **`<x-central.layout>`** + `central/home.blade.php`, every section built with `<x-site.section>`: Hero ("A beautiful website for your business in minutes", CTA → /templates, fanned composite of three demo screenshots) → How it works (pick a template → guided form → live at yourname.ezsite.app) → 8-template card strip → design-system section (the same block rendered under three presets, static imagery) → closing CTA. No pricing, no testimonials. English copy.
4. **Gallery thumbnails: pre-rendered static screenshots** (8 live iframes = 8 full page loads, unacceptable on mobile; signed preview URLs expire in 7 days, unusable for a permanent gallery). `scripts/capture-template-screenshots.mjs` (Playwright ships with pest-plugin-browser; the `*.localhost` trick is copied from `VisitsTenantPages`), 1440px and 390px viewports, screenshots committed. Template detail page: large screenshot + preset callout + industry feature list + **one** lazy-loaded iframe (desktop, below the fold) + two CTAs ("View live demo" opens the demo subdomain in a new tab / "Use this template" → `/start/{template}`). Shared card component `components/central/template-card.blade.php`.
5. **SEO**: reuse ralphjsmit/laravel-seo; the central sitemap lists `/` + `/templates` + the 8 detail pages; JSON-LD SoftwareApplication.
6. Tests: Home/Gallery/Detail rendering + SEO tags; **collision guard** (central `/` serves the landing while `acme.` subdomains still serve tenant pages); CentralRobots/Sitemap plus an assertion that tenant robots is unaffected; ConventionsTest addition for the Central namespace conventions.

## Phase 4 — One-click apply flow (5–7 days, the largest phase)

1. **Technology: full-page Livewire** (`/livewire/update` works on the central domain; plain Blade can't do live subdomain availability checks; Filament Schemas outside a panel drag panel styling into the marketing surface). New home `app/Livewire/Central/` (the codebase's first Livewire outside Filament — add an arch convention).
2. **Three-step wizard `ApplyTemplate`** (state in Livewire/session; no applications table in v1):
   - Step 1 — business basics: business name, tagline (optional), email, phone (optional) + subdomain picker (`Str::slug` prefill, `wire:model.live.debounce.500ms` availability check via `ValidateSubdomain`, live preview `yourname.ezsite.app` ✓/✗ + one `-2` suggestion).
   - Step 2 — industry content: fields come from `TemplateDefinition::extraFields` (3–6 fields: restaurants fill signature dishes name/price/one-liner; nail/spa fill services + prices + booking phone; resume fills name/title/3 experience entries; portfolio fills studio name/3 projects) + a prominent **"Skip — use example content, edit later"** (the templates are complete on their own; skipping still yields a good site).
   - Step 3 — account: email + password (this wizard IS registration; an existing email → prompt to log in, never allow signup to claim an account). No logo/photo upload in v1 — curated library photos by default, swapped later in the editor.
3. **Submit = synchronous provisioning** (provisioning is just DB writes, sub-second; images are async): `app/Actions/Templates/ProvisionSiteFromTemplate.php` `handle(SiteTemplate, SignupDetails): Tenant` — `ValidateSubdomain` → `CreateTenant(name, email, subdomain, template)` → `User::firstOrCreate` + attach → inside `RunInTenant` (Business record with brand hexes and merged tokens, chrome, placeholder fill → validator → `ApplySiteDraft`; pages stay **Draft** so the user reviews in the editor before publishing) → dispatch `PopulateDraftImagesJob` outside the transaction. `SignupDetails` is a readonly DTO.
4. **Landing in the editor (no mail infrastructure → signed auto-login)**: `app/Actions/Templates/CreateClaimUrl.php` (`URL::temporarySignedRoute('site.claim', 15min, absolute: false)` absolutized against `Domain::getUrl()` — the `signed:relative` cross-domain pattern SharePreviewAction already proves) + `ClaimSiteController` (tenant stack, `GET /_claim/{user}`: verify `belongsToCurrentTenant`, `Auth::login`, session regenerate, redirect to the home page's PageEditor). The success screen offers two buttons: View my site / Open the editor (the claim URL).
5. **Guardrails**: `RateLimiter::for('template-signup')` (IP + email, 3/hour, configurable) + a honeypot field; `ValidateSubdomain` is the single chokepoint, with the unique index on `domains.domain` as the race-condition backstop; no new tenant-owned tables → zero RLS work.
6. Tests: `ProvisionSiteFromTemplateTest` (dataset over all 8 templates: tenant/domain/attribution, new-user and existing-user paths, Business fields, chrome, Draft pages with placeholders swapped, `Queue::fake` assertion on the image job, slug-race rollback), `ClaimSiteControllerTest` (valid/expired/tampered/non-member/wrong tenant), `CreateClaimUrlTest`, rate-limiter registration test, `ApplyTemplateTest` (Livewire: per-step validation, live availability, skip path, honeypot, rate limit), optionally one Browser happy path.

## Explicitly not built (v1)

Payments/plans; email verification and all Mailables; password reset; custom domains at signup (subdomain only); logo/photo upload in the signup form; template re-sync to existing sites; template versioning; AI copy generation at apply time (placeholder fill is deterministic; AI editing already exists in the editor); application/funnel table (v2); CI automation for demo screenshots.

## Deployment checklist

- `bootstrap/app.php` must gain `->trustProxies()` before running behind a proxy (zero middleware is registered today; ignored `X-Forwarded-*` headers break scheme/host detection).
- Production DNS needs wildcard subdomain resolution; `TenantCouldNotBeIdentified` already falls back to a redirect to the central home page.
- Run `demo:seed` once after deploy (the Pexels key is configured; `library:import` can pre-warm the photo library beforehand).

## Dependencies and schedule

Phase 1 ships first as its own PR → Phases 2 and 3 can run in parallel (the gallery needs the demo sites live for screenshots) → Phase 4 needs 1+2. The copywriting (8 industries × 3–4 pages) is the real schedule driver; the two pilot templates de-risk the shape first. Roughly 2–3 weeks total.

## Progress

| Phase | Status |
|---|---|
| Phase 1 Template infrastructure | Shipped |
| Phase 2 Demo tenants | Shipped |
| Phase 3 Central landing | Shipped |
| Phase 4 One-click apply | Shipped |

### Deviations from the plan, and why

- **Phase 1 shipped as one change, not two PRs.** The `ApplySiteDraft` extraction
  landed first and green (`ApplySiteDraftTest` + the untouched
  `GenerateSiteDraftTest`), but the enum needs all eight cases to be
  enumerable, so the definitions were authored together rather than two-then-six.
- **`ApplySiteDraft` does NOT apply the preset tokens.** The plan said "move the
  transaction body verbatim"; the token write stayed in `GenerateSiteDraft`
  (inside its own transaction, so the pair is still atomic) because both
  template provisioners write MERGED preset+override tokens, and a bare
  `ApplyStylePreset` inside the shared action would have clobbered them.
- **The `tenants` migration edits the create table** rather than adding a
  second one — there is no production data (see the project note on that).
- **No `RateLimiter::for('template-signup')`.** A named limiter is only
  reachable through `throttle:` middleware, and the signup is a Livewire method
  call; `ApplyTemplate` calls `RateLimiter::tooManyAttempts()`/`hit()` directly
  against the same `config('templates.signup')` values instead.
- **`FillTemplatePlaceholders` returns chrome split into `header`/`footer`.**
  The flat list the plan implied went into `SaveSiteChrome`'s header argument
  and every demo site rendered its footer twice, above the hero. Found by
  looking at the built sites, fixed at the source, pinned by two tests.
- **Step 1 asks for a town or city.** Not in the plan's field list, but
  `{city}` is all over the templates' copy (hero eyebrows, footer notes, FAQ
  answers), so without it a Portland pizzeria shipped saying Providence.
- **Captures are JPEG at 1x, not PNG at 2x.** The first run of
  `scripts/capture-template-screenshots.mjs` produced 52 MB of lossless PNGs
  for sixteen files that render at a third of their captured width; JPEG at
  quality 82 and one device pixel gets the same gallery for 1.4 MB. The
  extension lives on `TemplateGallery::SCREENSHOT_EXTENSION`, which both halves
  read. All sixteen captures are committed, so the gallery is complete on a
  fresh clone with no demo tenants and no provider key; a template with no
  capture still falls back to a panel drawn from its own brand hexes.
