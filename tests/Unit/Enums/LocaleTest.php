<?php

declare(strict_types=1);

use App\Enums\Locale;

it('publishes Chinese by default', function (): void {
    // The product decision, asserted rather than assumed: a visitor whose
    // browser says nothing useful gets Chinese, and the whole negotiation chain
    // ends here.
    expect(Locale::default())->toBe(Locale::Chinese);
});

it('constrains the route parameter to exactly the languages it publishes', function (): void {
    // The constraint is what makes `/templates` reach the legacy redirect
    // instead of matching `/{locale}` with locale="templates".
    expect(Locale::pattern())->toBe('zh|en')
        ->and(['zh', 'en'])->each->toMatch('/^('.Locale::pattern().')$/');

    foreach (['fr', 'templates', 'robots.txt', 'zh-Hans', ''] as $unpublished) {
        expect(preg_match('/^('.Locale::pattern().')$/', $unpublished))->toBe(0);
    }
});

it('tags each language once, for the browser and the crawler alike', function (): void {
    $tags = array_map(fn (Locale $locale): string => $locale->htmlLang(), Locale::cases());
    $openGraph = array_map(fn (Locale $locale): string => $locale->openGraphLocale(), Locale::cases());
    $labels = array_map(fn (Locale $locale): string => $locale->nativeLabel(), Locale::cases());

    // Distinctness is the whole point: two languages sharing an hreflang tag
    // tells a crawler they are the same page, and two sharing a switcher label
    // leaves a visitor with no way to tell them apart.
    expect($tags)->toBe(array_unique($tags))
        ->and($openGraph)->toBe(array_unique($openGraph))
        ->and($labels)->toBe(array_unique($labels));

    foreach (Locale::cases() as $locale) {
        expect($locale->htmlLang())->toMatch('/^[a-z]{2}(-[A-Z][a-z]{3})?$/')
            // og:locale wants the underscored territory form, not a BCP-47 tag.
            ->and($locale->openGraphLocale())->toMatch('/^[a-z]{2}_[A-Z]{2}$/')
            ->and($locale->nativeLabel())->not->toBeEmpty();
    }

    // Simplified is stated, not left to a browser's guess between scripts.
    expect(Locale::Chinese->htmlLang())->toBe('zh-Hans');
});

it('names one cookie for the whole site', function (): void {
    expect(Locale::COOKIE)->toBe('locale');
});
