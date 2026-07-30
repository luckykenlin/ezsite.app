<?php

declare(strict_types=1);

use App\Site\UrlScheme;

it('denies the schemes a browser executes', function (string $url): void {
    expect(UrlScheme::isExecutable($url))->toBeTrue();
})->with([
    'javascript' => ['javascript:alert(1)'],
    'vbscript' => ['vbscript:msgbox(1)'],
    'data html' => ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='],

    // data:image is denied too: an SVG cannot script in <img src>, but the same
    // value in an href navigates to a document where it can.
    'data svg' => ['data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg"/>'],

    // Browsers strip control characters and whitespace before reading the
    // scheme, so a plain prefix check would miss all of these.
    'uppercased' => ['JaVaScRiPt:alert(1)'],
    'leading whitespace' => ['   javascript:alert(1)'],
    'leading newline' => ["\njavascript:alert(1)"],
    'embedded tab' => ["java\tscript:alert(1)"],
    'embedded newline' => ["java\nscript:alert(1)"],
    'null byte' => ["java\0script:alert(1)"],
]);

it('allows every URL shape the blocks legitimately store', function (string $url): void {
    expect(UrlScheme::isExecutable($url))->toBeFalse();
})->with([
    'relative path' => ['/contact'],
    'nested relative path' => ['/services/plumbing'],
    'anchor' => ['#contact'],
    'query only' => ['?utm_source=qr'],
    'https' => ['https://example.com/page'],
    'http' => ['http://example.com/page'],
    'protocol relative' => ['//example.com/page'],
    'mailto' => ['mailto:hi@example.com'],
    'tel' => ['tel:+15551234567'],
    'the gallery placeholder asset' => ['/images/placeholder.svg'],
    'empty' => [''],

    // "javascript" as ordinary path text is not a scheme — the colon is what
    // makes it one, so a page about JavaScript must stay linkable.
    'path mentioning javascript' => ['/blog/javascript-tips'],
    'anchor mentioning data' => ['#data'],
]);
