<?php

declare(strict_types=1);

use App\Actions\Templates\FillTemplatePlaceholders;
use App\Ai\SiteDraftValidator;
use App\Design\DesignTokens;
use App\Site\Blocks\BlockShape;
use App\Site\Blocks\BlockVocabulary;
use App\Site\Blocks\LayoutAxis;
use App\Templates\PhotoQuery;
use App\Templates\SiteTemplate;
use App\Templates\TemplateDefinition;
use App\Templates\TemplateField;
use App\Themes\Definitions\DefaultTheme;
use App\Themes\Scheme;
use App\Themes\Theme;

/**
 * Every template, by name — the dataset every structural assertion below runs
 * over, so a ninth template is covered the day it is declared.
 *
 * @return array<string, array{SiteTemplate}>
 */
dataset('templates', fn (): array => array_reduce(
    SiteTemplate::cases(),
    fn (array $carry, SiteTemplate $template): array => $carry + [$template->value => [$template]],
    [],
));

/**
 * The filled template run through the same door the AI path uses.
 *
 * @return array{scheme: Scheme, pages: non-empty-list<array{slug: string, title: string, metaDescription: string|null, blocks: list<array{type: string, data: array<string, mixed>}>}>}
 */
function validateTemplate(SiteTemplate $template): array
{
    config(['stock-photos.enabled' => true]);

    $definition = $template->definition();
    $filled = resolve(FillTemplatePlaceholders::class)->handle($definition);

    return resolve(SiteDraftValidator::class)->handle(
        ['scheme' => $definition->scheme, 'pages' => $filled['pages']],
        $definition->demoProfile->name,
    );
}

it('survives the site draft validator with no page and no block dropped', function (SiteTemplate $template): void {
    $definition = $template->definition();
    $validated = validateTemplate($template);

    expect($validated['scheme']->slug)->toBe($definition->scheme)
        ->and($validated['pages'])->toHaveSameSize($definition->pages);

    foreach ($definition->pages as $index => $authored) {
        expect($validated['pages'][$index]['slug'])->toBe($authored['slug'])
            ->and($validated['pages'][$index]['blocks'])->toHaveSameSize($authored['blocks']);
    }
})->with('templates');

it('keeps every authored field, layout variant and appearance axis', function (SiteTemplate $template): void {
    // The sharp end of "no block dropped": a block survives with the right
    // TYPE even when the sanitizer threw away half its data, so the counts
    // above cannot see a misspelt field name. This walks every key the
    // template author wrote and demands it landed.
    $definition = $template->definition();
    $validated = validateTemplate($template);
    $axes = array_column(LayoutAxis::cases(), 'value');
    $lost = [];

    foreach ($definition->pages as $pageIndex => $authored) {
        foreach ($authored['blocks'] as $blockIndex => $block) {
            $landed = $validated['pages'][$pageIndex]['blocks'][$blockIndex]['data'];
            $where = $authored['slug'].' block '.$blockIndex.' ('.$block['type'].')';

            foreach (array_keys($block['data']) as $field) {
                // The one renamed key: the unprefixed query the author writes
                // is re-attached under the reserved transit key.
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
    }

    expect($lost)->toBeEmpty();
})->with('templates');

it('opens on a home page that leads with a hero', function (SiteTemplate $template): void {
    $pages = $template->definition()->pages;
    $home = $pages[0];

    expect($home['slug'])->toBe('/')
        ->and(array_column($home['blocks'], 'type'))->toContain('hero')
        // Enough page to be worth publishing — the validator's own bar for a
        // home page, asserted here so a thin template fails by name rather
        // than as an opaque SiteDraftUnusable.
        ->and(count($home['blocks']))->toBeGreaterThanOrEqual(3);

    foreach ($pages as $page) {
        expect($page['slug'])->toBeIn(['/', ...SiteDraftValidator::EXTRA_SLUGS])
            ->and(count($page['blocks']))->toBeGreaterThanOrEqual(3);
    }
})->with('templates');

it('declares three brand hexes and a category the photo searches can use', function (SiteTemplate $template): void {
    $definition = $template->definition();

    expect([$definition->brandPrimary, $definition->brandSecondary, $definition->brandAccent])
        ->each->toMatch('/^#[0-9a-fA-F]{6}$/')
        ->and($definition->category)->not->toBeEmpty()
        // The tokens the business row is stamped with keep the scheme marker,
        // so the design panel and StampSchemeDefaults still know which
        // scheme's per-block opinions apply after an override.
        ->and($definition->tokens()->scheme)->toBe($definition->scheme);
})->with('templates');

it('pre-warms the library with photo searches, and asks a short answerable set of questions', function (SiteTemplate $template): void {
    $definition = $template->definition();

    expect($definition->photoQueries)->not->toBeEmpty()
        ->and($definition->photoQueries)->toContainOnlyInstancesOf(PhotoQuery::class)
        ->and($definition->extraFields)->toContainOnlyInstancesOf(TemplateField::class)
        // Three to six: fewer is not worth a wizard step, more and people skip.
        ->and(count($definition->extraFields))->toBeGreaterThanOrEqual(3)
        ->and(count($definition->extraFields))->toBeLessThanOrEqual(6);

    foreach ($definition->photoQueries as $query) {
        expect($query->query)->not->toBeEmpty()
            ->and($query->count)->toBeGreaterThan(0);
    }

    $keys = array_column($definition->extraFields, 'key');

    expect($keys)->toBe(array_unique($keys));

    foreach ($definition->extraFields as $field) {
        // The example IS the skip-path content, so an empty one would leave a
        // literal placeholder on a published page.
        //
        // The question itself is translated, so it is asked of the template
        // rather than the field: a field only knows its key, and `service_one`
        // asks something different in the hair studio than in the nail salon. A
        // missing key would render as the key path, not as nothing.
        $help = $template->fieldHelp($field);

        expect($field->example)->not->toBeEmpty()
            ->and($template->fieldLabel($field))->not->toBeEmpty()
            ->and($template->fieldLabel($field))->not->toStartWith('marketing.');

        // Most questions carry no hint, and the ones that do must not be
        // resolving to their own key path either.
        if ($help !== null) {
            expect($help)->not->toBeEmpty()->and($help)->not->toStartWith('marketing.');
        }
    }
})->with('templates');

it('names header and footer chrome that renders', function (SiteTemplate $template): void {
    $vocabulary = resolve(BlockVocabulary::class);
    $chrome = $template->definition()->chrome;

    expect(array_column($chrome, 'type'))->toBe(['header', 'footer']);

    foreach ($chrome as $block) {
        $contract = $vocabulary->get($block['type']);
        $variant = $block['data'][BlockShape::VARIANT_KEY] ?? null;

        expect($contract)->not->toBeNull()
            ->and($variant)->toBeIn($contract->variants)
            ->and(array_keys($block['data']))->each->toBeIn([BlockShape::VARIANT_KEY, ...$contract->fields]);
    }
})->with('templates');

it('gives every template its own demo subdomain, label and gallery copy', function (): void {
    $subdomains = array_map(fn (SiteTemplate $t): string => $t->demoSubdomain(), SiteTemplate::cases());

    expect($subdomains)->toBe(array_unique($subdomains))
        ->and($subdomains)->each->toStartWith('demo-');

    foreach (SiteTemplate::cases() as $template) {
        expect($template->label())->not->toBeEmpty()
            ->and($template->description())->not->toBeEmpty()
            ->and($template->highlights())->toHaveCount(3);
    }
});

/*
 * A template names its scheme by slug, and `TemplateDefinition::tokens()`
 * throws on one its theme does not offer — so a mistyped slug is caught the
 * first time anything builds the template, which this makes every run.
 */
it('names a scheme its theme offers', function (SiteTemplate $template): void {
    $definition = $template->definition();

    expect($definition->theme->definition()->scheme($definition->scheme))->toBeInstanceOf(Scheme::class)
        ->and($definition->tokens()->scheme)->toBe($definition->scheme);
})->with('templates');

it('refuses to build tokens for a scheme its theme does not offer', function (): void {
    $definition = SiteTemplate::cases()[0]->definition();
    $broken = new TemplateDefinition(
        scheme: 'no-such-scheme',
        brandPrimary: $definition->brandPrimary,
        brandSecondary: $definition->brandSecondary,
        brandAccent: $definition->brandAccent,
        category: $definition->category,
        chrome: $definition->chrome,
        pages: $definition->pages,
        photoQueries: $definition->photoQueries,
        demoProfile: $definition->demoProfile,
    );

    expect(fn (): DesignTokens => $broken->tokens())
        ->toThrow(InvalidArgumentException::class, 'The default theme has no "no-such-scheme" scheme.');
});

it('leaves only the two schemes of the default theme without a template unshowcased', function (): void {
    // The gallery is the design system's shop window: a scheme no template
    // reaches for is a scheme no visitor ever sees. Two are unshowcased on
    // purpose — their only templates (the designer portfolio on BoldEditorial,
    // the personal resume on ProfessionalMinimal) were retired — and listing
    // them here keeps the gap deliberate: a third orphan, or a template that
    // picks one of these up, both show here.
    $used = array_map(fn (SiteTemplate $t): string => $t->definition()->scheme, SiteTemplate::cases());
    $unused = array_values(array_filter(
        array_keys(Theme::Default->definition()->schemes),
        fn (string $slug): bool => ! in_array($slug, $used, true),
    ));

    expect($unused)->toBe([DefaultTheme::PROFESSIONAL_MINIMAL, DefaultTheme::BOLD_EDITORIAL]);
});
