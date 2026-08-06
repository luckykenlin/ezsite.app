<?php

declare(strict_types=1);

use App\Models\Business;
use App\Models\Media;
use App\Models\Tenant;
use App\Site\Favicon;

it('uses the logo the owner uploaded', function (): void {
    $tenant = Tenant::factory()->create();

    $links = $this->runInTenant($tenant, function () use ($tenant): string {
        $media = Media::factory()->create(['tenant_id' => $tenant->id]);
        $business = Business::factory()->create(['tenant_id' => $tenant->id, 'logo_media_id' => $media->id]);

        return Favicon::links($business)->toHtml();
    });

    expect($links)->toContain('rel="icon"')
        ->and($links)->toContain('/media/')
        ->and($links)->not->toContain('data:image/svg+xml');
});

it('draws a letter mark in the brand colour when there is no logo', function (): void {
    // The state most sites are in the day they are provisioned. Generating one
    // is what gives every site a distinct tab icon with nothing to upload.
    $business = Business::factory()->make([
        'name' => 'Jade Nails',
        'brand_primary' => '#b91c1c',
        'logo_path' => null,
        'logo_media_id' => null,
    ]);

    $links = Favicon::links($business)->toHtml();

    expect($links)->toContain('type="image/svg+xml"')
        ->and(rawurldecode($links))->toContain('fill="#b91c1c"')
        ->and(rawurldecode($links))->toContain('>J<');
});

it('falls back to the product colour when the brand hex is unusable', function (?string $stored): void {
    // Same gate every other brand-colour path goes through
    // (ColorPalette::validHex), because this value lands in an SVG attribute.
    $business = Business::factory()->make([
        'name' => 'Jade Nails',
        'brand_primary' => $stored,
        'logo_path' => null,
        'logo_media_id' => null,
    ]);

    expect(rawurldecode(Favicon::links($business)->toHtml()))->toContain('fill="#059669"');
})->with([
    'nothing stored' => [null],
    'a colour name' => ['red'],
    'shorthand' => ['#b11'],
    'an injection attempt' => ['#fff" onload="alert(1)'],
]);

it('keeps a business name out of the document as markup', function (): void {
    // The first character reaches the SVG as text content, and a business name
    // is operator-supplied.
    $business = Business::factory()->make([
        'name' => '<script>alert(1)</script>',
        'logo_path' => null,
        'logo_media_id' => null,
    ]);

    expect(rawurldecode(Favicon::links($business)->toHtml()))
        ->toContain('>&lt;<')
        ->not->toContain('<script');
});

it('uses the first character as-is when it is not a latin letter', function (): void {
    // A CJK restaurant name renders as its own glyph, which is a better mark
    // than any transliteration of it.
    $business = Business::factory()->make([
        'name' => '李記麵館',
        'logo_path' => null,
        'logo_media_id' => null,
    ]);

    expect(rawurldecode(Favicon::links($business)->toHtml()))->toContain('>李<');
});
