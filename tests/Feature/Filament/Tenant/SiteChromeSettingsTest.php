<?php

declare(strict_types=1);

use App\Filament\Tenant\Pages\SiteChromeSettings;
use App\Models\SiteSetting;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->tenant = $this->actingAsTenantPanelMember();
});

test('saving a header and footer creates the settings row in block-entry shape', function (): void {
    Livewire::test(SiteChromeSettings::class)
        ->fillForm([
            'header' => [[
                'type' => 'header',
                'data' => [
                    'variant' => 'centered',
                    'nav_links' => [['label' => 'About', 'url' => '/about']],
                ],
            ]],
            'footer' => [[
                'type' => 'footer',
                'data' => ['variant' => 'minimal', 'note' => 'All rights reserved.'],
            ]],
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $settings = SiteSetting::query()->sole();

    expect($settings->tenant_id)->toBe($this->tenant->id)
        ->and($settings->header[0]['type'])->toBe('header')
        ->and($settings->header[0]['data']['variant'])->toBe('centered')
        ->and($settings->footer[0]['type'])->toBe('footer')
        ->and($settings->footer[0]['data']['note'])->toBe('All rights reserved.');
});

test('empty slots are stored as null so the default chrome applies', function (): void {
    Livewire::test(SiteChromeSettings::class)
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = SiteSetting::query()->sole();

    expect($settings->header)->toBeNull()
        ->and($settings->footer)->toBeNull();
});

test('saving again updates the same row', function (): void {
    SiteSetting::factory()->withHeader()->create(['tenant_id' => $this->tenant->id]);

    Livewire::test(SiteChromeSettings::class)
        ->fillForm(['header' => []])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(SiteSetting::query()->count())->toBe(1)
        ->and(SiteSetting::query()->sole()->header)->toBeNull();
});

test('the form is pre-filled from the saved configuration', function (): void {
    SiteSetting::factory()->withHeader()->create(['tenant_id' => $this->tenant->id]);

    Livewire::test(SiteChromeSettings::class)
        ->assertSchemaStateSet(function (array $state): void {
            $entry = array_first($state['header']);

            expect($entry['type'])->toBe('header')
                ->and($entry['data']['variant'])->toBe('centered');
        });
});
