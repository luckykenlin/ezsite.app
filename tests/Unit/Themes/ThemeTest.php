<?php

declare(strict_types=1);

use App\Design\DesignTokens;
use App\Filament\Fabricator\BlockRegistry;
use App\Site\Blocks\BlockVocabulary;
use App\Site\Blocks\LayoutAxis;
use App\Themes\CurrentTheme;
use App\Themes\Definitions\DefaultTheme;
use App\Themes\Theme;
use App\Themes\ThemeBlock;
use App\Themes\ThemeDefinition;
use App\Themes\ThemePage;

/**
 * The block contracts as THIS theme publishes them — bound as the current
 * theme so the registry filters by it, which is how every theme-level
 * invariant below reads the same vocabulary the product would.
 */
function vocabularyOf(ThemeDefinition $definition): BlockVocabulary
{
    app()->instance(CurrentTheme::class, new CurrentTheme($definition));

    return new BlockVocabulary(BlockRegistry::contracts());
}

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

test('a theme definition answers its schemes by slug, and the first is the default', function (): void {
    $definition = Theme::Default->definition();

    expect($definition->scheme(DefaultTheme::NIGHT_LOUNGE)?->label)->toBe('Night lounge')
        ->and($definition->scheme('no-such-scheme'))->toBeNull()
        ->and($definition->defaultScheme()->slug)->toBe(DefaultTheme::WARM_CRAFT);
});

/*
 * The scheme map is keyed data, so three things the type system cannot say
 * have to be said here: the key and the scheme agree, the tokens point back at
 * the scheme they came from (that slug is what `businesses.design_tokens`
 * stores, and `ThemeDefinition::scheme()` is how it is read back), and there
 * is at least one — every design surface lists them, and the validator's
 * fallback is the first.
 */
test('every theme carries at least one complete, self-referencing scheme under its own slug', function (): void {
    foreach (Theme::cases() as $theme) {
        $schemes = $theme->definition()->schemes;

        expect($schemes)->not->toBeEmpty();

        foreach ($schemes as $slug => $scheme) {
            expect($scheme->slug)->toBe($slug)
                ->and($scheme->tokens->scheme)->toBe($slug)
                ->and($scheme->label)->not->toBeEmpty()
                ->and($scheme->description)->not->toBeEmpty()
                ->and($scheme->vibes)->not->toBeEmpty();
        }
    }
});

/*
 * synonyms are the whole grounding for "make it more premium": vibes holds
 * INDUSTRY nouns (bakery, law, saas), so without these adjectives the model has
 * nothing to match a feeling against and free-associates among the labels.
 *
 * Disjointness is the property that makes it work. An adjective naming two
 * schemes grounds neither, and the failure is invisible — the model picks one,
 * plausibly, and the operator just gets a look they did not ask for.
 */
test('every theme grounds each scheme in adjectives that name no other scheme of that theme', function (): void {
    foreach (Theme::cases() as $theme) {
        $seen = [];

        foreach ($theme->definition()->schemes as $slug => $scheme) {
            expect($scheme->synonyms)->not->toBeEmpty();

            foreach ($scheme->synonyms as $word) {
                expect($seen)->not->toHaveKey($word, message: sprintf('"%s" names both %s and %s', $word, $seen[$word] ?? '', $slug));

                $seen[$word] = $slug;
            }
        }
    }
});

/*
 * Both directions of the variant half. The entries that exist must name a
 * layout the theme actually draws for that type — a typo would otherwise fall
 * back to the first variant and nobody would see why. And every
 * variant-bearing type must HAVE an entry in every scheme: the fallback in
 * `StampSchemeDefaults::variantFor()` is silent, so adding a block type with
 * variants and forgetting the scheme defaults would lay the new section out
 * identically under every look, and "make it more premium" would leave it
 * untouched. Named rather than counted, so the failure says which to fix.
 */
test('every scheme gives every variant-bearing block of its theme a default that the theme draws', function (): void {
    foreach (Theme::cases() as $theme) {
        $definition = $theme->definition();

        foreach ($definition->schemes as $slug => $scheme) {
            foreach ($scheme->blockVariantDefaults as $type => $variant) {
                expect($definition->block($type)?->variants ?? [])->toHaveKey($variant, message: sprintf('%s/%s names %s.%s, which the theme does not draw', $theme->value, $slug, $type, $variant));
            }

            $missing = [];

            foreach ($definition->blocks as $type => $block) {
                if ($block->hasVariants() && ! array_key_exists($type, $scheme->blockVariantDefaults)) {
                    $missing[] = $type;
                }
            }

            expect($missing)->toBeEmpty(sprintf('%s/%s has no variant default for: %s', $theme->value, $slug, implode(', ', $missing)));
        }
    }
});

/*
 * The per-SECTION half of a scheme. Its validity rules are deliberately the
 * INVERSE of the variant half's: a missing variant silently makes every scheme
 * lay a block out identically, so all of them are required — but a missing
 * appearance falls back to the value the block's own view declares, which is
 * a considered default rather than a degradation. Totality here would force
 * eight opinions about every future block type, most of which should have
 * none. What IS required: every entry names a page type the theme draws, only
 * axes that type's contract declares, with values the axis enum resolves —
 * a typo here would reach a class attribute.
 *
 * Two exemptions are pinned, because both are decisions rather than
 * omissions and a future scheme author would otherwise "fix" them:
 *
 *  - hero: its VARIANT already decides its weight (the scheme that wants a
 *    dramatic opening picks full-bleed-overlay, which is dark by construction),
 *    so a tone here fights the variant it was chosen with.
 *  - heading: it is a divider inside the page's flow, not a band of its own.
 *    Give it a background and a section title becomes a section.
 */
test('every scheme only shapes page block types of its theme, never chrome, a hero or a bare heading, on axes the type declares', function (): void {
    foreach (Theme::cases() as $theme) {
        $definition = $theme->definition();
        $vocabulary = vocabularyOf($definition);

        foreach ($definition->schemes as $slug => $scheme) {
            expect($scheme->blockAppearanceDefaults)
                ->not->toHaveKey('hero')
                ->not->toHaveKey('heading');

            foreach ($scheme->blockAppearanceDefaults as $type => $appearance) {
                // Chrome renders outside the section shell, so an entry for it
                // would be a setting with nowhere to land.
                expect($vocabulary->isAddableToPage($type))->toBeTrue(sprintf('%s/%s shapes %s, which this theme does not draw as a page section', $theme->value, $slug, $type))
                    ->and(array_keys($appearance))->each->toBeIn(array_column(LayoutAxis::cases(), 'value'));

                $contract = $vocabulary->get($type);

                foreach ($appearance as $key => $value) {
                    $axis = LayoutAxis::from($key);

                    expect($axis->resolve($value))->not->toBeNull(sprintf('%s/%s %s.%s = %s is not a value of that axis', $theme->value, $slug, $type, $key, $value))
                        ->and($contract?->supportsAxis($axis))->toBeTrue(sprintf('%s/%s sets %s on %s, which does not declare that axis', $theme->value, $slug, $key, $type));
                }
            }
        }
    }
});

/*
 * A staged selection carries a slug and brand hexes, never axes, so every
 * reader that draws it resolves the slug here. The fallback is the SAVED
 * tokens, not the default scheme: a brand-only selection means "the look you
 * have, with these colours", and a retired slug keeps showing what is on disk.
 */
test('a theme definition resolves a staged selection to tokens, falling back to the saved look', function (): void {
    $definition = Theme::Default->definition();
    $saved = DesignTokens::default();

    expect($definition->tokensFor(['scheme' => DefaultTheme::BOLD_EDITORIAL], $saved))
        ->toBe($definition->scheme(DefaultTheme::BOLD_EDITORIAL)?->tokens)
        ->and($definition->tokensFor(['scheme' => null, 'brand_primary' => '#1a2b3c'], $saved))->toBe($saved)
        ->and($definition->tokensFor(['scheme' => 'retired-look'], $saved))->toBe($saved)
        ->and($definition->tokensFor([], $saved))->toBe($saved);
});

/*
 * The page map is keyed data, like the scheme map: the key and the page must
 * agree (the picker submits the key and resolves it back through `page()`),
 * and no page may be keyed `blank` — that is the picker's fixed first card,
 * and a theme page under the same value would be unreachable.
 *
 * The draft slugs are the AI site draft's closed menu: each one is a path
 * (leading slash), none is the home page (the draft always writes `/` itself),
 * and none repeats — the validator drops a second page on the same slug, so a
 * duplicate would offer the model a page it can never land.
 */
test('every theme keys its pages by their own key and offers a well-formed draft slug menu', function (): void {
    foreach (Theme::cases() as $theme) {
        $definition = $theme->definition();

        foreach ($definition->pages as $key => $page) {
            expect($page->key)->toBe($key)
                ->and($definition->page($key))->toBe($page);
        }

        $slugs = $definition->draftSlugs();

        expect($definition->pages)->not->toHaveKey('blank')
            ->and($slugs)->toBe(array_values(array_unique($slugs)))
            ->and($slugs)->not->toContain('/')
            ->and($slugs)->each->toStartWith('/');
    }
});

test('a theme definition answers its pages by key, and lists the draft slugs in page order', function (): void {
    $definition = Theme::Default->definition();

    expect(array_keys($definition->pages))->toBe(['home', 'about', 'services', 'contact', 'team', 'faqs', 'gallery', 'portfolio'])
        ->and($definition->page('about')?->title)->toBe('About')
        ->and($definition->page('blank'))->toBeNull()
        ->and($definition->draftSlugs())->toBe(['/about', '/services', '/contact'])
        // The pages behind that menu, in the same order — the one filter the
        // slug enum and the draft prompt's described menu both read.
        ->and(array_map(fn (ThemePage $page): string => $page->key, $definition->draftPages()))->toBe(['about', 'services', 'contact']);
});

/*
 * Built once per case: the definition is read on every block render, and the
 * vocabulary memo (BlockVocabularies) keys by the definition object, so a
 * rebuilt one per call would also defeat that memo.
 */
test('a theme hands back the same definition object on every call', function (): void {
    foreach (Theme::cases() as $theme) {
        expect($theme->definition())->toBe($theme->definition());
    }
});
