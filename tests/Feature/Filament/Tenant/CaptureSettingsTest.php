<?php

declare(strict_types=1);

use App\Filament\Tenant\Pages\CaptureSettings;
use App\Models\SiteSetting;
use App\Site\SiteCapture;
use Livewire\Livewire;

test('saving the popup and call bar creates the settings row', function (): void {
    Livewire::test(CaptureSettings::class)
        ->fillForm([
            'popup' => [
                'enabled' => true,
                'heading' => 'Get 10% off',
                'offer' => 'Join the list and we will email your voucher.',
                'fields' => 'email',
                'button_label' => 'Send it',
                'trigger' => 'scroll',
                'trigger_value' => 60,
                'frequency_days' => 14,
            ],
            'call_bar' => ['enabled' => true, 'label' => 'Call the salon'],
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    $settings = SiteSetting::query()->sole();

    expect($settings->tenant_id)->toBe($this->tenant->id)
        ->and($settings->capture['popup']['heading'])->toBe('Get 10% off')
        ->and($settings->capture['popup']['trigger'])->toBe('scroll')
        ->and($settings->capture['call_bar']['label'])->toBe('Call the salon');

    // And it reads back through the value object the site renders from.
    $capture = resolve(SiteCapture::class);

    expect($capture->popupEnabled())->toBeTrue()
        ->and($capture->popupTriggerValue())->toBe(60)
        ->and($capture->callBarLabel())->toBe('Call the salon');
});

test('a popup switched on needs a heading', function (): void {
    // The one required field, because a modal with no message is worse than
    // no modal at all.
    Livewire::test(CaptureSettings::class)
        ->fillForm(['popup' => ['enabled' => true, 'heading' => null]])
        ->call('save')
        ->assertHasFormErrors(['popup.heading' => 'required']);

    expect(SiteSetting::query()->count())->toBe(0);
});

test('nothing is required while the popup is switched off', function (): void {
    Livewire::test(CaptureSettings::class)
        ->fillForm(['popup' => ['enabled' => false]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(resolve(SiteCapture::class)->popupEnabled())->toBeFalse();
});

test('the form loads what was saved before', function (): void {
    SiteSetting::query()->create([
        'tenant_id' => $this->tenant->id,
        'capture' => [
            'popup' => ['enabled' => true, 'heading' => 'Stored heading'],
            'call_bar' => ['enabled' => true, 'label' => 'Stored label'],
        ],
    ]);

    // Per key rather than per section: fill() pads the unset fields with
    // nulls, which SiteCapture reads as "use the default".
    Livewire::test(CaptureSettings::class)
        ->assertSchemaStateSet([
            'popup.enabled' => true,
            'popup.heading' => 'Stored heading',
            'call_bar.enabled' => true,
            'call_bar.label' => 'Stored label',
        ]);
});

test('saving capture settings leaves the chrome columns alone', function (): void {
    // Both editors write the same row, so the second must not blank the first.
    SiteSetting::query()->create([
        'tenant_id' => $this->tenant->id,
        'header' => [['type' => 'header', 'data' => ['variant' => 'centered']]],
    ]);

    Livewire::test(CaptureSettings::class)
        ->fillForm(['popup' => ['enabled' => true, 'heading' => 'Get 10% off']])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = SiteSetting::query()->sole();

    expect($settings->header[0]['data']['variant'])->toBe('centered')
        ->and($settings->capture['popup']['heading'])->toBe('Get 10% off');
});
