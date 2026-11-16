<?php

declare(strict_types=1);

use App\Ai\SiteDraftValidator;
use App\Site\Blocks\BlockShape;
use App\Site\Blocks\LayoutAxis;
use App\Templates\PlaceholderSubstitution;
use App\Themes\CurrentTheme;
use App\Themes\Theme;
use App\Themes\ThemePage;
use Filament\Support\Icons\Heroicon;

/**
 * Every page of every theme, paired with the theme that offers it — the
 * dataset the structural assertions run over, so a new page (or a new theme's
 * whole page set) is covered the day it is declared.
 *
 * @return array<string, array{Theme, ThemePage}>
 */
dataset('theme_pages', function (): array {
    $pages = [];

    foreach (Theme::cases() as $theme) {
        foreach ($theme->definition()->pages as $key => $page) {
            $pages[$theme->value.'/'.$key] = [$theme, $page];
        }
    }

    return $pages;
});

/**
 * The validator reads the vocabulary and variants through the current theme,
 * so a page is validated against the theme that offers it.
 *
 * @return list<array{type: string, data: array<string, mixed>}>
 */
function validateThemePage(Theme $theme, ThemePage $page): array
{
    app()->instance(CurrentTheme::class, new CurrentTheme($theme->definition()));

    return resolve(SiteDraftValidator::class)->blocks(fillThemePage($page));
}

/**
 * A page's blocks with every token answered — the fully-personalized input
 * the validator sees in production.
 *
 * @return list<array<string, mixed>>
 */
function fillThemePage(ThemePage $page): array
{
    return PlaceholderSubstitution::fill($page->blocks, [
        '{business_name}' => 'The Lacquer Room',
        '{tagline}' => 'Hand-painted nails, one chair at a time',
        '{city}' => 'Savannah',
        '{phone}' => '(912) 555-0184',
        '{email}' => 'hello@lacquerroom.example',
    ]);
}

it('survives the site draft validator with no block dropped', function (Theme $theme, ThemePage $page): void {
    config(['stock-photos.enabled' => true]);

    expect(validateThemePage($theme, $page))->toHaveSameSize($page->blocks);
})->with('theme_pages');

it('keeps every authored field, layout variant and appearance axis', function (Theme $theme, ThemePage $page): void {
    // The sharp end of "no block dropped": a block survives with the right
    // TYPE even when the sanitizer threw away half its data, so the count
    // above cannot see a misspelt field name. Same walk as SiteTemplateTest.
    config(['stock-photos.enabled' => true]);

    $authored = $page->blocks;
    $validated = validateThemePage($theme, $page);
    $axes = array_column(LayoutAxis::cases(), 'value');
    $lost = [];

    foreach ($authored as $index => $block) {
        $landed = $validated[$index]['data'];
        $where = $page->key.' block '.$index.' ('.$block['type'].')';

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
})->with('theme_pages');

it('leaves no literal placeholder brace once every token is answered', function (Theme $theme, ThemePage $page): void {
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

    $walk(fillThemePage($page), $walk);

    expect($braces)->toBeEmpty();

    // The meta description personalizes through the same map, so its tokens
    // must be drawn from the same closed set the block copy uses.
    preg_match_all('/\{([a-z_]+)}/', (string) $page->metaDescription, $matches);

    expect($matches[1])->each->toBeIn(['business_name', 'tagline', 'city', 'phone', 'email']);
})->with('theme_pages');

it('composes a page worth landing on: enough blocks, at most one hero', function (Theme $theme, ThemePage $page): void {
    $types = array_column($page->blocks, 'type');

    expect(count($types))->toBeGreaterThanOrEqual(3)
        ->and(count(array_keys($types, 'hero', true)))->toBeLessThanOrEqual(1);
})->with('theme_pages');

it('gives every page a label, a description, an icon and a title', function (Theme $theme, ThemePage $page): void {
    expect($page->label)->not->toBeEmpty()
        ->and($page->description)->not->toBeEmpty()
        ->and($page->icon)->toBeInstanceOf(Heroicon::class)
        ->and($page->title)->not->toBeEmpty();
})->with('theme_pages');
