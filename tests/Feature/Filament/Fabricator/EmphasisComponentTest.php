<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

/*
 * The nail-luxe heading convention: `*phrase*` becomes an italic accent. The
 * text is AI- and operator-authored and reaches a public page, so every
 * segment must stay escaped — the component's own <em> is the only markup it
 * may ever emit.
 */
function emphasise(string $text): string
{
    return Blade::render('<x-site.emphasis :text="$text" />', ['text' => $text]);
}

it('keeps markup in a heading escaped, inside and outside the emphasis', function (): void {
    expect(emphasise('Hi <script>alert(1)</script> *<b>bold</b>*'))
        ->not->toContain('<script>')
        ->not->toContain('<b>')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->toContain('<em class="site-accent"><span hidden>*</span>&lt;b&gt;bold&lt;/b&gt;<span hidden>*</span></em>');
});

it('alternates plain and emphasised segments between asterisks', function (): void {
    expect(emphasise('*a*b*c*'))
        ->toBe('<em class="site-accent"><span hidden>*</span>a<span hidden>*</span></em>b<em class="site-accent"><span hidden>*</span>c<span hidden>*</span></em>');
});

it('prints an unmatched asterisk and plain text as they are', function (string $text, string $expected): void {
    expect(emphasise($text))->toBe($expected);
})->with([
    'no asterisks' => ['Our services', 'Our services'],
    'an unmatched trailing asterisk' => ['Five *stars', 'Five *stars'],
    'a pair and a stray' => ['A *b* c*d', 'A <em class="site-accent"><span hidden>*</span>b<span hidden>*</span></em> c*d'],
]);
