<?php

declare(strict_types=1);

use App\Filament\Fabricator\BlockRegistry;
use App\Site\Blocks\BlockVocabularies;
use App\Site\Blocks\BlockVocabulary;
use App\Themes\CurrentTheme;
use App\Themes\Definitions\DefaultTheme;
use App\Themes\Theme;
use App\Themes\ThemeBlock;
use App\Themes\ThemeDefinition;

/**
 * A theme that draws two block types only — different enough from the default
 * theme that a vocabulary built for one cannot pass for the other's.
 */
function twoBlockTheme(): ThemeDefinition
{
    return new ThemeDefinition(
        label: 'Two-block',
        blocks: ['hero' => new ThemeBlock(variants: ['poster' => 'Poster']), 'heading' => new ThemeBlock],
        stylesheet: Theme::Default->definition()->stylesheet,
        schemes: [DefaultTheme::WARM_CRAFT => defaultScheme(DefaultTheme::WARM_CRAFT)],
    );
}

/*
 * The leak this exists to stop. Nothing flushes scoped instances when
 * RunInTenant switches tenants in one process, so a vocabulary that WAS the
 * scoped instance followed a queue worker from one tenant's theme into the
 * next. The theme switch below happens with the container's scoped instances
 * deliberately left alone.
 */
it('gives a second theme in the same container its own vocabulary', function (): void {
    $default = resolve(BlockVocabulary::class);

    app()->instance(CurrentTheme::class, new CurrentTheme(twoBlockTheme()));

    $twoBlock = resolve(BlockVocabulary::class);

    expect(array_keys($twoBlock->all()))->toBe(['heading', 'hero'])
        ->and($twoBlock->get('hero')?->variants)->toBe(['poster'])
        ->and($default->has('features'))->toBeTrue()
        ->and($twoBlock->has('features'))->toBeFalse();
});

/*
 * And the memo it still is: walking every registered block class happens once
 * per theme, however many seams ask. Counted at the builder, because identity
 * alone would pass for any binding that happened to return one object.
 */
it('builds each theme s vocabulary once, however often it is asked for', function (): void {
    $builds = 0;

    app()->instance(BlockVocabularies::class, new BlockVocabularies(function () use (&$builds): array {
        $builds++;

        return BlockRegistry::contracts();
    }));

    resolve(BlockVocabulary::class);
    resolve(BlockVocabulary::class);

    expect($builds)->toBe(1);

    app()->instance(CurrentTheme::class, new CurrentTheme(twoBlockTheme()));

    resolve(BlockVocabulary::class);
    resolve(BlockVocabulary::class);

    expect($builds)->toBe(2);
});
