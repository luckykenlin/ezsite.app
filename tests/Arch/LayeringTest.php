<?php

declare(strict_types=1);

/*
 * Dependency DIRECTION, as opposed to ConventionsTest's per-class shape rules.
 *
 * The inversion these lock out was real and widespread: `app/Ai`, `app/Actions`
 * and even `app/Models` imported `App\Filament\Fabricator`, so the domain
 * depended on the admin panel. Nothing prevented it, and 12 files had drifted
 * that way — the block vocabulary is a domain concept that happened to live under
 * `App\Filament` only because that is where the Fabricator package globs block
 * classes from.
 *
 * ONE RULE PER NAMESPACE, DELIBERATELY. `expect([...])` with an array of
 * namespaces does NOT behave like the conjunction of these rules: written that
 * way the whole thing passes even with a live `App\Filament` import in
 * `App\Actions` — verified by reintroducing one. A single-namespace `expect()`
 * catches it. Each rule below was confirmed to FAIL before its fix landed; if you
 * add one, do the same, because a rule that matches nothing passes vacuously and
 * reads exactly like a rule that is being honoured.
 *
 * `App\Site` and `App\Tenancy` are listed as protected layers, not consumers:
 * App\Site holds the render-side domain concepts (BindResolver, MediaResolver,
 * SiteChrome, UrlScheme) and App\Tenancy the tenant-context machinery.
 */

arch('the AI layer never depends on the admin panel')
    ->expect('App\Ai')
    ->not->toUse('App\Filament');

arch('actions never depend on the admin panel')
    // The last holdout was CaptureLead importing LeadResource to build an inbox
    // URL for a Filament notification; it now fires App\Events\LeadCaptured and
    // App\Listeners\NotifyOperatorsOfLead owns that knowledge.
    ->expect('App\Actions')
    ->not->toUse('App\Filament');

arch('models never depend on the admin panel')
    // Business::logoUrl() used to service-locate the media resolver out of
    // App\Filament\Fabricator from inside an Eloquent model.
    ->expect('App\Models')
    ->not->toUse('App\Filament');

arch('the design module never depends on the admin panel')
    ->expect('App\Design')
    ->not->toUse('App\Filament');

arch('the site render layer never depends on the admin panel')
    ->expect('App\Site')
    ->not->toUse('App\Filament');

arch('the tenancy layer never depends on the admin panel')
    ->expect('App\Tenancy')
    ->not->toUse('App\Filament');

arch('domain events never depend on the admin panel')
    // The escape hatch the LeadCaptured refactor opened: events are domain
    // vocabulary, so they must stay as panel-free as the actions that fire them.
    // The LISTENER is the only side allowed to know about Filament.
    ->expect('App\Events')
    ->not->toUse('App\Filament');
