<?php

declare(strict_types=1);

use App\Design\Contrast;
use App\Design\DesignTokens;
use App\Design\ThemeVariables;
use App\Design\TokenOptions;
use App\Design\TokenSelection;
use App\Filament\Fabricator\BlockRegistry;
use App\Filament\Fabricator\PageBlocks\Block;

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

test('page block views never use unescaped output', function (): void {
    // Block views output AI-influenced content, so Blade's raw `{!! !!}` is
    // forbidden — it would be a stored-XSS hole on the shared, server-rendered
    // tenant sites.
    $dir = dirname(__DIR__, 2).'/resources/views/components/filament-fabricator/page-blocks';

    $views = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($views as $view) {
        if ($view->getExtension() === 'php') {
            expect(file_get_contents($view->getPathname()))
                ->not->toContain('{!!');
        }
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

        $slot = basename(dirname($view->getPathname()));

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
        'sm:grid-cols-2',
        'sm:grid-cols-3',
        'lg:grid-cols-3',
        'card bg-base-200',
        'card bg-base-100',
        'aspect-video',
        'aspect-square',
        'aspect-[4/5]',
        // Card chrome comes from SectionItemStyle::Card; pasting the class
        // into a view puts hover-lift cards on a block the item_style axis
        // thinks is plain.
        'site-card',
        // Photo scrims go through .site-scrim (the graded overlay); the old
        // flat overlay reads as a grey wash over the whole image.
        'bg-neutral/60',
    ];

    $views = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
    );

    $offenders = [];

    foreach ($views as $view) {
        if ($view->getExtension() !== 'php' || in_array(basename(dirname($view->getPathname())), $chrome, true)) {
            continue;
        }

        $contents = (string) file_get_contents($view->getPathname());

        foreach ($literals as $literal) {
            if (str_contains($contents, $literal)) {
                $offenders[] = basename($view->getPathname()).' hand-rolls "'.$literal.'"';
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
        ->toContain("@source '../../app/Site/Blocks/Section*.php';");
});
