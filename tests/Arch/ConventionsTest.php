<?php

declare(strict_types=1);

use App\Filament\Fabricator\BlockRegistry;
use App\Filament\Fabricator\PageBlocks\Block;

arch('all app code declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debugging statements are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'var_export', 'print_r'])
    ->not->toBeUsed();

arch('models are final classes')
    ->expect('App\Models')
    ->toBeClasses()
    ->toBeFinal();

arch('actions are final and expose a single handle entrypoint')
    ->expect('App\Actions')
    ->toBeClasses()
    ->toBeFinal()
    ->toHaveMethod('handle');

arch('page blocks extend the app base block and are final')
    ->expect('App\Filament\Fabricator\PageBlocks')
    ->toExtend(Block::class)
    ->toBeFinal()
    ->ignoring(Block::class);

arch('the block registry is final')
    ->expect(BlockRegistry::class)
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
