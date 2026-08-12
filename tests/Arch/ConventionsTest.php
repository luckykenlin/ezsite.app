<?php

declare(strict_types=1);

use App\Design\Contrast;
use App\Design\DesignTokens;
use App\Design\StylePreset;
use App\Design\ThemeVariables;
use App\Design\TokenOptions;
use App\Design\TokenSelection;
use App\Filament\Fabricator\BlockRegistry;
use App\Filament\Fabricator\PageBlocks\Block;
use App\Models\Business;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Page;
use App\Models\Post;
use App\Models\SiteSetting;
use App\Templates\PagePreset;
use App\Templates\SiteTemplate;

/**
 * A view's markup with its Blade comments removed.
 *
 * Every guard below that scans view SOURCE needs this, because the views that
 * explain a rule quote the thing the rule forbids — `{!! !!}`, `.site-card` — and
 * a guard that fails on its own documentation is a guard somebody deletes.
 */
function stripBladeComments(string $markup): string
{
    return (string) preg_replace('/\{\{--.*?--\}\}/s', '', $markup);
}

arch('all app code declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debugging statements are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'var_export', 'print_r'])
    ->not->toBeUsed();

arch('assert() is never used')
    // CI's PHP runs php.ini-production (zend.assertions=-1), which compiles
    // `assert()` out while Xdebug still counts its line as executable — the
    // `--exactly=100.0` coverage gate then fails on code that is fully covered
    // locally. setup-php's `ini-values` cannot undo it: once the main php.ini
    // says -1, only that file or `php -d` can raise it. Narrow types with an
    // inline `/** @var X $var */` instead.
    ->expect('assert')
    ->not->toBeUsed();

arch('models are final classes')
    ->expect('App\Models')
    ->toBeClasses()
    ->toBeFinal();

arch('actions are final readonly and expose a single handle entrypoint')
    ->expect('App\Actions')
    ->toBeClasses()
    ->toBeFinal()
    ->toBeReadonly()
    ->toHaveMethod('handle');

arch('templates are final readonly artifacts, never Eloquent-backed')
    // A template is a designed artifact — versioned, diffable, testable — and
    // the moment one of them can read a row, "the copy on the gallery" stops
    // being something a diff can tell you. Readonly is what keeps that true.
    ->expect('App\Templates')
    ->toBeFinal()
    // The two enum indexes — implicitly final, which the checker cannot see.
    ->ignoring([SiteTemplate::class, PagePreset::class]);

arch('template definitions expose exactly one entry point')
    ->expect('App\Templates\Definitions')
    ->toBeReadonly()
    ->toHaveMethod('definition');

arch('page blocks extend the app base block and are final')
    ->expect('App\Filament\Fabricator\PageBlocks')
    ->toExtend(Block::class)
    ->toBeFinal()
    ->ignoring(Block::class);

arch('the design module and block registry classes are final')
    // The enums in App\Design are final by construction; list the classes.
    ->expect([
        BlockRegistry::class,
        Contrast::class,
        DesignTokens::class,
        ThemeVariables::class,
        TokenOptions::class,
        TokenSelection::class,
    ])
    ->toBeFinal();

test('public site views never use unescaped output', function (): void {
    // Block views output AI-influenced content, so Blade's raw `{!! !!}` is
    // forbidden — it would be a stored-XSS hole on the shared, server-rendered
    // tenant sites.
    //
    // `views/site` is scanned for the same reason: /updates renders a body an
    // operator (or the drafting agent) typed, and it is not a Fabricator page, so
    // nothing else here would have covered it. `views/components/site` is NOT in
    // this list — the base layout it wraps legitimately emits the SEO package's
    // pre-rendered tags.
    $dirs = [
        dirname(__DIR__, 2).'/resources/views/components/filament-fabricator/page-blocks',
        dirname(__DIR__, 2).'/resources/views/site',
    ];

    foreach ($dirs as $dir) {
        $views = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($views as $view) {
            if ($view->getExtension() === 'php') {
                // Blade comments first, same as the CSS-prefix guard below: a view
                // that documents WHY raw output is forbidden mentions `{!! !!}` in
                // prose, and a rule that punishes explaining itself gets deleted.
                expect(stripBladeComments((string) file_get_contents($view->getPathname())))
                    ->not->toContain('{!!');
            }
        }
    }
});

test('page block views declare their props exactly as the stored data keys them', function (): void {
    // The block render loop hands a block's `data` array straight to its view
    // (see the note in views/vendor/filament-fabricator/components/page-blocks),
    // so a prop name IS a data key: `kind_filter` is read, `kindFilter` is not.
    // The failure is silent — the prop falls back to its `@props` default and
    // the feature it drives just stops working — which is why it is a guard and
    // not a review note. Stored keys are snake_case (Filament schemas, the AI
    // tools and every template definition write them that way), so the props
    // are too.
    $views = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            dirname(__DIR__, 2).'/resources/views/components/filament-fabricator/page-blocks',
            FilesystemIterator::SKIP_DOTS,
        ),
    );

    foreach ($views as $view) {
        if ($view->getExtension() !== 'php') {
            continue;
        }

        preg_match('/@props\(\[(.*?)\]\)/s', (string) file_get_contents($view->getPathname()), $matches);
        preg_match_all("/'([^']+)'\s*=>/", $matches[1] ?? '', $props);

        expect($props[1])->each->toMatch('/^[a-z0-9_]+$/');
    }
});

test('the editor canvas glue is loaded by the preview document only', function (): void {
    // The canvas script/stylesheet turn a rendered page into an editing
    // surface. They are bundled assets now (so eslint + tsc cover them),
    // which means no request test can see them under `withoutVite()` — this
    // guards the wiring instead: the preview partial pulls them in, and the
    // live-site layout never mentions them.
    $views = dirname(__DIR__, 2).'/resources/views';

    expect(file_get_contents($views.'/filament/tenant/pages/partials/page-editor-canvas.blade.php'))
        ->toContain('resources/js/page-editor/canvas-glue.ts')
        ->toContain('resources/css/page-editor-canvas.css')
        ->and(file_get_contents($views.'/components/filament-fabricator/layouts/main.blade.php'))->not->toContain('page-editor');
});

test('the builder views keep their styles in stylesheets, not inline', function (): void {
    // CLAUDE.md: the editor's browser code lives in resources/, never inline in
    // Blade. These two views held 920 lines of <style> between them, which put
    // them outside `vp fmt --check` and outside every reviewer's diff habits.
    //
    // Panel-wide loading is only safe because every selector in those sheets is
    // `.pe-`/`.pc-` prefixed, so this also guards the prefix: an unprefixed rule
    // would leak into every page of the tenant panel.
    $views = dirname(__DIR__, 2).'/resources/views/filament/tenant/pages';
    $css = dirname(__DIR__, 2).'/resources/css';

    foreach (['page-editor', 'page-canvas'] as $name) {
        expect(file_get_contents($views.'/'.$name.'.blade.php'))
            ->not->toContain('<style')
            ->and(file_get_contents($css.'/'.$name.'.css'))->not->toBeEmpty();
    }

    // Every CLASS the sheets style must carry the prefix. Checking class tokens
    // rather than whole selectors keeps this from trying to parse CSS: `.dark`
    // descendant combinators, media queries and keyframe steps all come out right,
    // and a class is the only way one of these rules can reach another page.
    $prefixes = ['page-editor' => 'pe-', 'page-canvas' => 'pc-'];

    foreach ($prefixes as $name => $prefix) {
        // Comments first: prose mentions filenames, and `.blade.php` reads as
        // three class tokens otherwise.
        $sheet = preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($css.'/'.$name.'.css'));
        $classes = [];
        preg_match_all('/\.([a-zA-Z][\w-]*)/', (string) $sheet, $classes);

        $leaked = array_values(array_unique(array_filter(
            $classes[1],
            // `.dark` is Filament's own theme switch, which these sheets read
            // rather than define.
            static fn (string $class): bool => $class !== 'dark' && ! str_starts_with($class, $prefix),
        )));

        expect($leaked)->toBeEmpty();
    }
});

test('the builder Alpine modules are loaded panel-wide, never scoped to their page', function (): void {
    // Same blind spot as above, different failure. Both modules register their
    // component on `alpine:init`, which fires once — on the first full page
    // load. The panel navigates with wire:navigate, which swaps the body and
    // calls Alpine.initTree() WITHOUT firing that event again, so a module
    // scoped to one page arrives too late to ever register and every x-data on
    // it dies with "… is not defined". No PHP test can catch that — withoutVite()
    // hides the script tag — so this guards the wiring statically. The browser
    // suite (tests/Browser) does exercise the modules for real, but only on a
    // first load, which is exactly the case that works either way.
    $provider = file_get_contents(dirname(__DIR__, 2).'/app/Providers/FilamentServiceProvider.php');

    expect($provider)
        ->toContain('resources/js/page-editor/editor.ts')
        ->toContain('resources/js/page-canvas/canvas.ts')
        ->not->toContain('scopes: PageEditor')
        ->not->toContain('scopes: PageCanvas');
});

test('every page block view renders through the shared section shell', function (): void {
    // A block view that hand-rolls its own <section> is a block whose background
    // and spacing the operator and the assistant cannot touch — the appearance
    // dimension only exists where <x-site.section> does. Site chrome is exempt
    // by design: a header/footer is the frame around pages, not a section in a
    // page's rhythm, so it keeps its own element.
    $dir = dirname(__DIR__, 2).'/resources/views/components/filament-fabricator/page-blocks';
    $chrome = ['header', 'footer'];

    $views = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($views as $view) {
        if ($view->getExtension() !== 'php') {
            continue;
        }

        $slot = basename(dirname((string) $view->getPathname()));

        if (in_array($slot, $chrome, true)) {
            continue;
        }

        expect(file_get_contents($view->getPathname()))
            ->toContain('<x-site.section')
            ->toContain("'appearance' => null")
            // Every view resolves its layout axes through the contract — a view
            // that skips this has hard-coded knobs the assistant cannot turn.
            ->toContain('SectionLayout::for(')
            // The shell owns the outer element, so a stray <section> here means
            // a second, unstyleable one nested inside it.
            ->not->toContain('<section');
    }
});

test('the page-block views type through the shared site-* classes, never utility literals', function (): void {
    // The heading/eyebrow/intro treatments live ONCE in resources/css/site.css
    // (.site-h2 and friends), where the TypeStyle token's --type-* variables
    // can reach them. A pasted utility literal (`text-3xl font-bold
    // tracking-tight`, the pre-refactor state of a dozen views) silently
    // escapes the token system: it renders fine today and simply ignores every
    // typography token forever after. Chrome is exempt like everywhere else —
    // a header's nav is not section typography.
    $dir = dirname(__DIR__, 2).'/resources/views/components/filament-fabricator/page-blocks';
    $chrome = ['header', 'footer'];
    $literals = [
        'text-3xl font-bold tracking-tight',
        'text-4xl font-bold tracking-tight',
        'text-5xl font-bold tracking-tight',
        'uppercase tracking-widest',
        'uppercase tracking-wider',
        // The layout-axis-owned strings: a view that pastes one of these back
        // has re-hard-coded a knob the axes exist to turn. The class literals
        // live only in the App\Site\Blocks\Section* enums.
        'max-w-7xl',
        'max-w-5xl',
        'max-w-3xl',
        // Edge-to-edge is SectionWidth::Full, reached through container() /
        // headerContainer() / mediaFrame(). A view that drops the measure and
        // the padding itself bleeds a section the width axis still believes is
        // contained — and takes the heading out to the viewport edge with it.
        'max-w-none',
        'sm:grid-cols-2',
        'sm:grid-cols-3',
        'lg:grid-cols-3',
        'site-card bg-base-200',
        'site-card bg-base-100',
        'aspect-video',
        'aspect-square',
        'aspect-[4/5]',
        // The header-to-content gap is SectionSpacing's (headerGap()); pasted
        // back into a view it pins an `airy` section to the same internal
        // rhythm as a `tight` one, which is how a roomy preset ended up as a
        // tight block with air only around its outside.
        'mt-12',
        // Card chrome comes from SectionItemStyle; pasting either class into a
        // view puts card chrome on a block the item_style axis thinks is
        // plain. (`site-card-body` and `site-card-actions` are deliberately
        // NOT banned — where the body wrapper goes is the view's own
        // composition, which is why single tokens match on their boundaries.)
        'site-card',
        'site-card-outline',
        // The button silhouette a section stands on is SectionTone's call, for
        // the accent-band reason spelled out on buttonClasses(). A view may
        // still reach for `site-btn site-btn-primary` where the button sits on
        // a CARD rather than on the band (pricing, cta) — what it must not do
        // is decide the on-band case for itself.
        'site-btn-on-accent',
        // Photo scrims go through .site-scrim (the graded overlay); the old
        // flat overlay reads as a grey wash over the whole image.
        'bg-neutral/60',
    ];

    $offenders = [];

    // `views/site` joins the scan because /updates is a public, tenant-themed page
    // that is NOT a Fabricator page: it composes from the same `site-*` classes
    // and resolves its container through the same SectionLayout, and without this
    // it would be a brand-new unenforced typography surface in a product whose
    // stated moat is that the operator never picks a font size.
    foreach ([$dir, dirname(__DIR__, 2).'/resources/views/site'] as $scanRoot) {
        $views = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($views as $view) {
            if ($view->getExtension() !== 'php' || in_array(basename(dirname((string) $view->getPathname())), $chrome, true)) {
                continue;
            }

            // Comments stripped for the same reason as the raw-output guard: a view
            // explaining that `.site-card` belongs to SectionItemStyle rather than
            // to a view is documenting the rule, not breaking it.
            $contents = stripBladeComments((string) file_get_contents($view->getPathname()));

            foreach ($literals as $literal) {
                // A multi-word literal is a phrase and matches as one. A single
                // class token matches on its own boundaries, so banning
                // `site-card` does not also ban `site-card-body`: the card
                // CHROME belongs to SectionItemStyle, but where the body
                // wrapper goes is the view's own composition.
                $found = str_contains($literal, ' ')
                    ? str_contains($contents, $literal)
                    : preg_match('/(?<![\w-])'.preg_quote($literal, '/').'(?![\w-])/', $contents) === 1;

                if ($found) {
                    $offenders[] = basename((string) $view->getPathname()).' hand-rolls "'.$literal.'"';
                }
            }
        }
    }

    expect($offenders)->toBeEmpty();
});

test("the section shell's Tailwind sources are declared", function (): void {
    // A section's background and vertical padding now live ONLY as class strings
    // inside two PHP enums plus one view outside the page-blocks tree. Tailwind
    // compiles the public site from site.css, whose explicit @source list does
    // not reach either — so without these lines every block renders with no
    // background and no padding, and no PHP test can see it (they assert on the
    // class names, which are still emitted).
    expect(file_get_contents(dirname(__DIR__, 2).'/resources/css/site.css'))
        ->toContain("@source '../views/components/site/**/*.blade.php';")
        ->toContain("@source '../../app/Site/Blocks/Section*.php';")
        // The standalone public pages (/updates and its permalinks, the
        // coming-soon notice) are not Fabricator pages, so no glob above reaches
        // them. Without this line they ship unstyled — and no request test can
        // see it, because those assert on class names, which are still emitted.
        ->toContain("@source '../views/site/**/*.blade.php';");
});

test("the central stylesheet's Tailwind sources are declared", function (): void {
    // The central marketing site carries its own stylesheet (central.css) with
    // its own closed @source list — Tailwind compiles one source set per
    // entry, so a central surface missing from this list ships unstyled HTML
    // and no PHP test can see it (they assert on class names, which are still
    // emitted). The livewire and errors lines matter most: neither directory
    // was reached by ANY glob before, so their utilities compiled only by
    // coincidence with classes used elsewhere.
    expect(file_get_contents(dirname(__DIR__, 2).'/resources/css/central.css'))
        ->toContain("@source '../views/central/**/*.blade.php';")
        ->toContain("@source '../views/components/central/**/*.blade.php';")
        ->toContain("@source '../views/livewire/central/**/*.blade.php';")
        ->toContain("@source '../views/errors/404.blade.php';");
});

test('the central controllers never reach for a tenant-scoped model', function (): void {
    // There is no tenant on the central domain, so RLS scopes nothing: a
    // `Page::query()` here would read every page in the installation and put
    // one tenant's content on the marketing site. The templates are PHP, so
    // these controllers need no tenant-owned model at all — which makes the
    // rule cheap to hold and worth stating.
    $dir = dirname(__DIR__, 2).'/app/Http/Controllers/Central';
    $tenantOwned = [Page::class, Business::class, Location::class, Post::class, SiteSetting::class, Lead::class];
    $offenders = [];

    foreach (glob($dir.'/*.php') ?: [] as $controller) {
        $contents = (string) file_get_contents($controller);

        foreach ($tenantOwned as $model) {
            if (str_contains($contents, $model)) {
                $offenders[] = basename($controller).' uses '.$model;
            }
        }
    }

    expect($offenders)->toBeEmpty();
});

test('the database lifecycle the suite depends on is not undermined', function (): void {
    // Both halves of this guard exist because tests/Pest.php migrates ONCE per
    // parallel worker and TRUNCATEs between tests. The schema therefore outlives
    // a test, and two things that used to be harmless are now not:
    //
    //   - a migration that seeds rows: present for the first test in a worker,
    //     truncated away for every one after it. Seed from a factory or a seeder
    //     the test calls itself.
    //   - a test that creates a table: it survives into every later test in that
    //     worker, and `RlsPolicyTest`'s "every table is RLS-protected" guard then
    //     fails somewhere else entirely.
    $offenders = [];

    foreach (glob(dirname(__DIR__, 2).'/database/migrations/*.php') ?: [] as $migration) {
        if (preg_match('/->insert(?:OrIgnore|Using)?\(|DB::table\(/', (string) file_get_contents($migration)) === 1) {
            $offenders[] = 'migration seeds rows: '.basename($migration);
        }
    }

    // Pest.php itself is exempt: creating the per-token database and running that
    // one migration IS the lifecycle this guard protects. (Arch tests get no
    // application, so this walks the tree by hand rather than via the File facade.)
    $tests = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__), FilesystemIterator::SKIP_DOTS));

    foreach ($tests as $test) {
        if ($test->getExtension() !== 'php' || $test->getFilename() === 'Pest.php') {
            continue;
        }

        // The raw-SQL half of the pattern is concatenated so this file does not
        // match itself — same reason stripBladeComments() exists above.
        if (preg_match('/Schema::(?:connection\([^)]*\)->)?(?:create|createDatabase|drop|dropIfExists|dropColumns|rename|table)\(|'.'CREATE'.'\s+TABLE/i', (string) file_get_contents($test->getPathname())) === 1) {
            $offenders[] = 'test mutates the schema: '.$test->getFilename();
        }
    }

    expect($offenders)->toBeEmpty();
});

test('both languages of the marketing site carry exactly the same keys', function (): void {
    // A missing translation does not throw and does not blank — it renders the
    // KEY PATH on the page (`templates.hair-studio.fields.service_one.label`).
    // That failure mode is loud in the browser and invisible in CI, so this is
    // the guard: whatever lang/en gains, lang/zh has to gain too.
    //
    // Driven off lang/en, not off the union: lang/zh/validation.php is
    // deliberately a partial override (Laravel falls back to the framework's
    // English for anything it omits), so requiring an English twin for it would
    // mean committing a copy of a vendor file to satisfy a test.
    $lang = dirname(__DIR__, 2).'/lang';

    $flatten = function (array $values, string $prefix = '') use (&$flatten): array {
        $keys = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $keys = [...$keys, ...(is_array($value) ? $flatten($value, $path) : [$path])];
        }

        sort($keys);

        return $keys;
    };

    $files = array_map(basename(...), glob($lang.'/en/*.php') ?: []);

    // One file per language, not three: a group name that doubles as a UI
    // label (`design`) gets picked up by Filament's global ->translateLabel()
    // and returns an array. See the note at the top of lang/en/marketing.php.
    expect($files)->toEqualCanonicalizing(['marketing.php']);

    foreach ($files as $file) {
        expect($flatten(require $lang.'/zh/'.$file))
            ->toBe($flatten(require $lang.'/en/'.$file), $file.' has drifted between languages');
    }
});

test('every template, preset and wizard question is translated', function (): void {
    // The copy moved out of PHP into lang files, so "did anyone forget one?" is
    // no longer a question the type system answers. These are the three lists
    // that have to stay in step with the enums and the definitions.
    $marketing = require dirname(__DIR__, 2).'/lang/en/marketing.php';
    $templates = $marketing['templates'];
    $presets = $marketing['presets'];

    foreach (StylePreset::cases() as $preset) {
        expect($presets)->toHaveKey($preset->value.'.label')
            ->and($presets)->toHaveKey($preset->value.'.description');
    }

    foreach (SiteTemplate::cases() as $template) {
        expect($templates)->toHaveKey($template->value.'.label')
            ->and($templates)->toHaveKey($template->value.'.description')
            ->and($templates[$template->value]['highlights'])->toHaveCount(3);

        // Keyed per template, because the same field key asks a different
        // question in a different trade — `service_one` is "your most-booked
        // service" in the hair studio and "the service you are known for" in the
        // nail salon.
        foreach ($template->definition()->extraFields as $field) {
            expect($templates)->toHaveKey($template->value.'.fields.'.$field->key.'.label');
        }
    }
});
