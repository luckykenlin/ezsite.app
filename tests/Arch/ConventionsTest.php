<?php

declare(strict_types=1);

use App\Design\Contrast;
use App\Design\DesignTokens;
use App\Design\ThemeVariables;
use App\Design\TokenOptions;
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
        ->toContain('resources/js/page-editor/canvas.ts')
        ->toContain('resources/css/page-editor-canvas.css')
        ->and(file_get_contents($views.'/components/filament-fabricator/layouts/main.blade.php'))->not->toContain('page-editor');
});

test('dragging a library block onto the canvas is gone from both ends', function (): void {
    // Another one nothing at runtime can see. Half-removing this leaves the
    // canvas listening for a message the editor never sends — or worse, a live
    // drop path with nothing to arm it — and neither shows up in a request
    // test under withoutVite() or in PHP coverage.
    $resources = dirname(__DIR__, 2).'/resources';

    foreach (['protocol.ts', 'editor.ts', 'canvas.ts'] as $file) {
        expect(file_get_contents($resources.'/js/page-editor/'.$file))
            ->not->toContain('library-drag')
            ->not->toContain('library-drop');
    }

    expect(file_get_contents($resources.'/css/page-editor-canvas.css'))
        ->not->toContain('data-editor-insert-mode');
});

test('the builder Alpine modules are loaded panel-wide, never scoped to their page', function (): void {
    // Same blind spot as above, different failure. Both modules register their
    // component on `alpine:init`, which fires once — on the first full page
    // load. The panel navigates with wire:navigate, which swaps the body and
    // calls Alpine.initTree() WITHOUT firing that event again, so a module
    // scoped to one page arrives too late to ever register and every x-data on
    // it dies with "… is not defined". Nothing at runtime can catch that here:
    // withoutVite() hides the script tag, and there is no JS test runner.
    $provider = file_get_contents(dirname(__DIR__, 2).'/app/Providers/FilamentServiceProvider.php');

    expect($provider)
        ->toContain('resources/js/page-editor/editor.ts')
        ->toContain('resources/js/page-canvas/canvas.ts')
        ->not->toContain('scopes: PageEditor')
        ->not->toContain('scopes: PageCanvas');
});
