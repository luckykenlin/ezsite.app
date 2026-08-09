<?php

declare(strict_types=1);

use App\Design\FontStylesheet;

/*
 * The design surfaces draw a specimen of every font family, so they need all of
 * them loaded. The SIZE of how they are loaded is the whole point of this class
 * — see its docblock — so that is what the first test pins.
 */
function fontManifest(mixed $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'fonts').'.json';

    file_put_contents($path, is_string($contents) ? $contents : json_encode($contents));

    return $path;
}

it('links the built stylesheet instead of inlining it', function (): void {
    $path = fontManifest(['style' => ['file' => 'assets/fonts-abc123.css']]);

    try {
        $link = FontStylesheet::link($path)->toHtml();
    } finally {
        unlink($path);
    }

    expect($link)->toContain('rel="stylesheet"')
        ->toContain('build/assets/fonts-abc123.css')
        // The failure this class exists to prevent: Vite::fonts() emits the
        // whole @font-face sheet inline plus a preload per weight and subset —
        // ~111KB, re-sent on every Livewire round trip of the page editor.
        ->not->toContain('@font-face')
        ->not->toContain('rel="preload"')
        ->and(mb_strlen($link))->toBeLessThan(200);
});

it('reads the real build, so the manifest shape stays the one this parses', function (): void {
    // The test above proves the tag; this proves we are still reading the key
    // the fonts plugin actually writes. Without it, a plugin upgrade that moved
    // `style.file` would leave every specimen unstyled and every test green.
    $path = public_path('build/fonts-manifest.json');

    if (! is_file($path)) {
        $this->markTestSkipped('no font manifest — run npm run build');
    }

    expect(FontStylesheet::link()->toHtml())->toContain('rel="stylesheet"')
        ->toContain(json_decode((string) file_get_contents($path), true)['style']['file']);
});

it('emits nothing when there is no usable manifest', function (mixed $contents): void {
    // A missing or unreadable manifest must degrade the specimens to their
    // generic stacks, never raise through a rendering panel. The absent case is
    // the one CI itself takes, since it runs the suite without an npm build.
    $path = $contents === null ? '/nonexistent/fonts-manifest.json' : fontManifest($contents);

    try {
        expect(FontStylesheet::link($path)->toHtml())->toBeEmpty();
    } finally {
        if ($contents !== null) {
            unlink($path);
        }
    }
})->with([
    'no build at all' => null,
    'not json' => 'not json at all',
    'no style key' => [['preloads' => []]],
    'style.file is not a string' => [['style' => ['inline' => 'body{}']]],
]);
