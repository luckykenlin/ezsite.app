<?php

declare(strict_types=1);

use App\Actions\UpdateDesignTokens;
use App\Design\ColorPalette;
use App\Design\FontPair;
use App\Design\RadiusScale;
use App\Design\StylePreset;
use App\Filament\Tenant\Pages\Design;
use App\Models\Business;
use Livewire\Livewire;

test('the page is inaccessible until a business profile exists', function (): void {
    expect(Design::canAccess())->toBeFalse();

    Business::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(Design::canAccess())->toBeTrue();
});

test('choosing a preset only previews it into the fine-tune fields until saved', function (): void {
    Business::factory()->themed(StylePreset::WarmCraft)->create(['tenant_id' => $this->tenant->id]);

    $component = Livewire::test(Design::class)
        ->fillForm(['preset' => 'bold-editorial'])
        ->assertSchemaStateSet([
            'palette' => 'plum',
            'font_pair' => StylePreset::BoldEditorial->tokens()->fontPair->value,
        ]);

    // Nothing persisted yet — a stray click no longer restyles the live site.
    expect(Business::query()->sole()->design_tokens->preset)->toBe(StylePreset::WarmCraft);

    $component->call('save')->assertNotified();

    $tokens = Business::query()->sole()->design_tokens;

    expect($tokens->preset)->toBe(StylePreset::BoldEditorial)
        ->and($tokens->palette)->toBe(ColorPalette::Plum);
});

test('fine-tuning after choosing a preset saves a custom combination, preset detached', function (): void {
    Business::factory()->themed(StylePreset::WarmCraft)->create(['tenant_id' => $this->tenant->id]);

    Livewire::test(Design::class)
        ->fillForm(['preset' => 'bold-editorial'])
        ->fillForm(['palette' => 'ocean'])
        ->call('save')
        ->assertHasNoFormErrors();

    $tokens = Business::query()->sole()->design_tokens;

    expect($tokens->preset)->toBeNull()
        ->and($tokens->palette)->toBe(ColorPalette::Ocean)
        ->and($tokens->fontPair)->toBe(StylePreset::BoldEditorial->tokens()->fontPair);
});

test('clearing the preset selection changes nothing', function (): void {
    Business::factory()->themed(StylePreset::WarmCraft)->create(['tenant_id' => $this->tenant->id]);

    Livewire::test(Design::class)
        ->fillForm(['preset' => null])
        ->assertNotNotified();

    expect(Business::query()->sole()->design_tokens->preset)->toBe(StylePreset::WarmCraft);
});

test('saving fine-tuned tokens persists them and detaches the preset', function (): void {
    Business::factory()->themed(StylePreset::WarmCraft)->create(['tenant_id' => $this->tenant->id]);

    Livewire::test(Design::class)
        ->fillForm([
            'palette' => 'ocean',
            'font_pair' => 'geometric',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $tokens = Business::query()->sole()->design_tokens;

    expect($tokens->palette)->toBe(ColorPalette::Ocean)
        ->and($tokens->fontPair)->toBe(FontPair::Geometric)
        ->and($tokens->radius)->toBe(RadiusScale::Lg) // untouched from WarmCraft
        ->and($tokens->preset)->toBeNull();
});

test('saving brand colors updates the business columns', function (): void {
    $business = Business::factory()->create([
        'tenant_id' => $this->tenant->id,
        'brand_primary' => '#111111',
    ]);
    $this->runInTenant($this->tenant, fn (): Business => resolve(UpdateDesignTokens::class)
        ->handle($business, ['palette' => 'brand']));

    Livewire::test(Design::class)
        ->fillForm(['brand_primary' => '#ff6b35'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Business::query()->sole()->brand_primary)->toBe('#ff6b35');
});

test('the form is pre-filled from the stored tokens', function (): void {
    Business::factory()->themed(StylePreset::CalmCoastal)->create(['tenant_id' => $this->tenant->id]);

    Livewire::test(Design::class)
        ->assertSchemaStateSet([
            'preset' => 'calm-coastal',
            'palette' => 'ocean',
            'radius' => 'lg',
        ]);
});
