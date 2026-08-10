<?php

declare(strict_types=1);

use App\Ai\SiteDraftValidator;
use App\Site\Blocks\BlockShape;
use App\Site\Blocks\LayoutAxis;
use App\Templates\PagePreset;
use App\Templates\PlaceholderSubstitution;
use Filament\Support\Icons\Heroicon;

/**
 * Every preset that carries blocks — the dataset the structural assertions
 * run over, so a tenth preset is covered the day it is declared.
 *
 * @return array<string, array{PagePreset}>
 */
dataset('page_presets', fn (): array => array_reduce(
    array_filter(PagePreset::cases(), fn (PagePreset $preset): bool => $preset !== PagePreset::Blank),
    fn (array $carry, PagePreset $preset): array => $carry + [$preset->value => [$preset]],
    [],
));

/**
 * A preset's blocks with every token answered — the fully-personalized input
 * the validator sees in production.
 *
 * @return list<array<string, mixed>>
 */
function fillPagePreset(PagePreset $preset): array
{
    return PlaceholderSubstitution::fill($preset->definition()->blocks, [
        '{business_name}' => 'The Lacquer Room',
        '{tagline}' => 'Hand-painted nails, one chair at a time',
        '{city}' => 'Savannah',
        '{phone}' => '(912) 555-0184',
        '{email}' => 'hello@lacquerroom.example',
    ]);
}

it('survives the site draft validator with no block dropped', function (PagePreset $preset): void {
    config(['stock-photos.enabled' => true]);

    $authored = $preset->definition()->blocks;
    $validated = resolve(SiteDraftValidator::class)->blocks(fillPagePreset($preset));

    expect($validated)->toHaveSameSize($authored);
})->with('page_presets');

it('keeps every authored field, layout variant and appearance axis', function (PagePreset $preset): void {
    // The sharp end of "no block dropped": a block survives with the right
    // TYPE even when the sanitizer threw away half its data, so the count
    // above cannot see a misspelt field name. Same walk as SiteTemplateTest.
    config(['stock-photos.enabled' => true]);

    $authored = $preset->definition()->blocks;
    $validated = resolve(SiteDraftValidator::class)->blocks(fillPagePreset($preset));
    $axes = array_column(LayoutAxis::cases(), 'value');
    $lost = [];

    foreach ($authored as $index => $block) {
        $landed = $validated[$index]['data'];
        $where = $preset->value.' block '.$index.' ('.$block['type'].')';

        foreach (array_keys($block['data']) as $field) {
            $expected = $field === 'image_query' ? BlockShape::IMAGE_QUERY_KEY : $field;

            if (! array_key_exists($expected, $landed)) {
                $lost[] = $where.' dropped the field "'.$field.'"';
            }
        }

        if (isset($block[BlockShape::VARIANT_KEY]) && ($landed[BlockShape::VARIANT_KEY] ?? null) !== $block[BlockShape::VARIANT_KEY]) {
            $lost[] = $where.' dropped the variant "'.$block[BlockShape::VARIANT_KEY].'"';
        }

        foreach ($axes as $axis) {
            if (isset($block[$axis]) && ($landed[BlockShape::APPEARANCE_KEY][$axis] ?? null) !== $block[$axis]) {
                $lost[] = $where.' dropped '.$axis.' "'.$block[$axis].'"';
            }
        }
    }

    expect($lost)->toBeEmpty();
})->with('page_presets');

it('leaves no literal placeholder brace once every token is answered', function (PagePreset $preset): void {
    $braces = [];

    $walk = function (array $node, callable $walk) use (&$braces): void {
        foreach ($node as $value) {
            if (is_string($value) && preg_match('/[{}]/', $value) === 1) {
                $braces[] = $value;
            } elseif (is_array($value)) {
                $walk($value, $walk);
            }
        }
    };

    $walk(fillPagePreset($preset), $walk);

    expect($braces)->toBeEmpty();

    // The meta description personalizes through the same map, so its tokens
    // must be drawn from the same closed set the block copy uses.
    preg_match_all('/\{([a-z_]+)}/', (string) $preset->definition()->metaDescription, $matches);

    expect($matches[1])->each->toBeIn(['business_name', 'tagline', 'city', 'phone', 'email']);
})->with('page_presets');

it('composes a page worth landing on: enough blocks, at most one hero', function (PagePreset $preset): void {
    $types = array_column($preset->definition()->blocks, 'type');

    expect(count($types))->toBeGreaterThanOrEqual(3)
        ->and(count(array_keys($types, 'hero', true)))->toBeLessThanOrEqual(1);
})->with('page_presets');

it('gives every case a label, a description, an icon and a title', function (): void {
    foreach (PagePreset::cases() as $preset) {
        expect($preset->label())->not->toBeEmpty()
            ->and($preset->description())->not->toBeEmpty()
            ->and($preset->icon())->toBeInstanceOf(Heroicon::class)
            ->and($preset->definition()->title)->not->toBeEmpty();
    }
});

it('renders a thumbnail for everything except Blank, which starts empty', function (): void {
    foreach (PagePreset::cases() as $preset) {
        expect($preset->hasThumbnail())->toBe($preset !== PagePreset::Blank)
            ->and($preset->definition()->blocks === [])->toBe($preset === PagePreset::Blank);
    }

    expect(PagePreset::Blank->definition()->title)->toBe('Untitled page')
        ->and(PagePreset::Blank->definition()->metaDescription)->toBeNull();
});
