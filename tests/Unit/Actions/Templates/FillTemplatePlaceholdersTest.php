<?php

declare(strict_types=1);

use App\Actions\Templates\FillTemplatePlaceholders;
use App\Templates\SiteTemplate;

/**
 * Every string anywhere in a filled page or chrome tree, flattened.
 *
 * @param  array<array-key, mixed>  $node
 * @return list<string>
 */
function flattenStrings(array $node): array
{
    $strings = [];

    foreach ($node as $value) {
        if (is_string($value)) {
            $strings[] = $value;
        } elseif (is_array($value)) {
            $strings = [...$strings, ...flattenStrings($value)];
        }
    }

    return $strings;
}

it('replaces every placeholder in every template, leaving no literal braces behind', function (SiteTemplate $template): void {
    // The fail-closed guard for the whole mechanism: a token nobody defined
    // survives substitution verbatim, so a typo in a template's copy would
    // publish `{ciyt}` onto a live page. There is nowhere else this could be
    // caught — the validator has no opinion about braces.
    $filled = resolve(FillTemplatePlaceholders::class)->handle($template->definition());

    $leftovers = array_values(array_filter(
        [...flattenStrings($filled['pages']), ...flattenStrings($filled['header']), ...flattenStrings($filled['footer'])],
        static fn (string $value): bool => preg_match('/\{[a-z_]+\}/', $value) === 1,
    ));

    expect($leftovers)->toBeEmpty();
})->with(fn (): array => array_map(
    fn (SiteTemplate $template): array => [$template],
    SiteTemplate::cases(),
));

it('falls back to the demo profile and each field example when nothing was answered', function (): void {
    $template = SiteTemplate::ChineseRestaurant;
    $definition = $template->definition();

    $filled = resolve(FillTemplatePlaceholders::class)->handle($definition);
    $strings = [...flattenStrings($filled['pages']), ...flattenStrings($filled['header']), ...flattenStrings($filled['footer'])];

    expect($strings)->toContain($definition->demoProfile->tagline)
        // The skip path is only safe because an unanswered industry field
        // resolves to its own example rather than to nothing.
        ->and(implode(' ', $strings))->toContain($definition->extraFields[0]->example);
});

it('prefers the answers over the fallbacks, and treats a blank answer as unanswered', function (): void {
    $definition = SiteTemplate::ChineseRestaurant->definition();

    $filled = resolve(FillTemplatePlaceholders::class)->handle($definition, [
        'business_name' => '  Jade Pearl  ',
        'city' => 'Portland',
        'tagline' => '   ',
        'dish_one' => 'Twice-Cooked Pork',
    ]);

    $copy = implode(' ', [...flattenStrings($filled['pages']), ...flattenStrings($filled['header']), ...flattenStrings($filled['footer'])]);

    expect($copy)->toContain('Jade Pearl')
        // Trimmed on the way in, so a stray space in a form field cannot land
        // in the middle of a headline.
        ->not->toContain('  Jade Pearl')
        ->and($copy)->toContain('Portland')
        ->and($copy)->toContain('Twice-Cooked Pork')
        ->and($copy)->not->toContain($definition->demoProfile->name)
        // Whitespace is not an answer.
        ->and($copy)->toContain($definition->demoProfile->tagline);
});

it('substitutes in a single pass, so an answer that looks like a placeholder is left alone', function (): void {
    $definition = SiteTemplate::ChineseRestaurant->definition();

    $filled = resolve(FillTemplatePlaceholders::class)->handle($definition, [
        'business_name' => '{city} Kitchen',
        'city' => 'Fresno',
    ]);

    $copy = implode(' ', flattenStrings($filled['pages']));

    // Not "Fresno Kitchen": the name a person typed is content, not a token.
    expect($copy)->toContain('{city} Kitchen');
});

it('hands the chrome back split by slot, one block in each', function (SiteTemplate $template): void {
    // SaveSiteChrome takes a header argument and a footer argument. Handing it
    // the definition's flat list put the FOOTER block in the header slot, and
    // every demo site rendered its footer twice — once above the hero, once
    // where it belonged. Splitting here is what makes that unrepresentable.
    $filled = resolve(FillTemplatePlaceholders::class)->handle($template->definition());

    expect(array_column($filled['header'], 'type'))->toBe(['header'])
        ->and(array_column($filled['footer'], 'type'))->toBe(['footer']);
})->with(fn (): array => array_map(
    fn (SiteTemplate $template): array => [$template],
    SiteTemplate::cases(),
));
