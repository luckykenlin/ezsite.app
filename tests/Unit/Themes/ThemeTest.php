<?php

declare(strict_types=1);

use App\Themes\Theme;
use App\Themes\ThemeBlock;

test('every theme has a definition with a label and a stylesheet that exists on disk', function (): void {
    foreach (Theme::cases() as $theme) {
        $definition = $theme->definition();

        expect($definition->label)->not->toBe('')
            ->and($theme->label())->toBe($definition->label)
            ->and(file_exists(base_path($definition->stylesheet)))->toBeTrue($theme->value.' stylesheet is missing');
    }
});

test('every theme block only names variants it declares', function (): void {
    foreach (Theme::cases() as $theme) {
        foreach ($theme->definition()->blocks as $type => $block) {
            $variants = array_keys($block->variants);

            // A per-variant axis override or an imageless marker for a variant
            // the theme never draws would silently never apply.
            expect(array_keys($block->variantAxes))->each->toBeIn($variants)
                ->and($block->imagelessVariants)->each->toBeIn($variants);
        }
    }
});

test('a theme block defaults to its first variant, and to none when it has a single view', function (): void {
    $single = new ThemeBlock;
    $layered = new ThemeBlock(variants: ['grid' => 'Grid', 'list' => 'List']);

    expect($single->hasVariants())->toBeFalse()
        ->and($single->defaultVariant())->toBeNull()
        ->and($layered->hasVariants())->toBeTrue()
        ->and($layered->defaultVariant())->toBe('grid');
});

test('a theme definition answers which block types it supports', function (): void {
    $definition = Theme::Default->definition();

    expect($definition->types())->toContain('hero', 'offerings', 'header', 'footer')
        ->and($definition->supports('hero'))->toBeTrue()
        ->and($definition->supports('pricing'))->toBeFalse()
        ->and($definition->block('hero'))->toBeInstanceOf(ThemeBlock::class)
        ->and($definition->block('pricing'))->toBeNull();
});
