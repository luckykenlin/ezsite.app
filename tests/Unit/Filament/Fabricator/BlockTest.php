<?php

declare(strict_types=1);

use App\Filament\Fabricator\PageBlocks\Heading;
use App\Filament\Fabricator\PageBlocks\Hero;
use Filament\Forms\Components\Builder\Block as BuilderBlock;

it('declares no bind type for content-only blocks', function (): void {
    expect(Hero::bindType())->toBeNull()
        ->and(Heading::bindType())->toBeNull();
});

it('builds a Filament builder block for a variant block', function (): void {
    expect(Hero::getBlockSchema())
        ->toBeInstanceOf(BuilderBlock::class)
        ->and(Hero::getBlockSchema()->getName())->toBe('hero');
});

it('builds a Filament builder block for a no-variant block', function (): void {
    expect(Heading::getBlockSchema())
        ->toBeInstanceOf(BuilderBlock::class)
        ->and(Heading::getBlockSchema()->getName())->toBe('heading');
});
