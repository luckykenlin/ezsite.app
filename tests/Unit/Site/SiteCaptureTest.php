<?php

declare(strict_types=1);

use App\Enums\LeadFieldSet;
use App\Enums\PopupTrigger;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Site\SiteCapture;

/**
 * The capture settings as SiteCapture reads them back, inside tenancy.
 */
function captureFor(array $capture): SiteCapture
{
    $tenant = Tenant::factory()->create();

    test()->runInTenant($tenant, fn (): SiteSetting => SiteSetting::query()->create([
        'tenant_id' => $tenant->id,
        'capture' => $capture,
    ]));

    tenancy()->initialize($tenant);

    return resolve(SiteCapture::class);
}

it('reads a fully configured popup', function (): void {
    $capture = captureFor(['popup' => [
        'enabled' => true,
        'heading' => 'Get 10% off',
        'offer' => 'Join the list.',
        'fields' => 'name_email',
        'button_label' => 'Claim it',
        'success_message' => 'Check your inbox.',
        'fine_print' => 'No spam.',
        'trigger' => 'scroll',
        'trigger_value' => 40,
        'frequency_days' => 30,
    ]]);

    expect($capture->popupEnabled())->toBeTrue()
        ->and($capture->popupHeading())->toBe('Get 10% off')
        ->and($capture->popupOffer())->toBe('Join the list.')
        ->and($capture->popupFields())->toBe(LeadFieldSet::NameEmail)
        ->and($capture->popupButtonLabel())->toBe('Claim it')
        ->and($capture->popupSuccessMessage())->toBe('Check your inbox.')
        ->and($capture->popupFinePrint())->toBe('No spam.')
        ->and($capture->popupTrigger())->toBe(PopupTrigger::Scroll)
        ->and($capture->popupTriggerValue())->toBe(40)
        ->and($capture->popupFrequencyDays())->toBe(30);
});

it('treats an unset capture column as everything switched off', function (): void {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);

    $capture = resolve(SiteCapture::class);

    expect($capture->popupEnabled())->toBeFalse()
        ->and($capture->callBarEnabled())->toBeFalse()
        ->and($capture->popupHeading())->toBeEmpty();
});

it('degrades every malformed value to a safe default rather than rendering it', function (): void {
    // Tenant-authored JSON reaching a class attribute, a data- attribute and a
    // tel: href. Same fail-safe posture the layout axes take.
    $capture = captureFor(['popup' => [
        'enabled' => 'yes',            // not a real bool
        'heading' => ['nested'],       // not a string
        'fields' => 'not-a-field-set',
        'trigger' => 'telepathy',
        'trigger_value' => 'soon',
        'frequency_days' => null,
    ], 'call_bar' => 'not-an-array']);

    expect($capture->popupEnabled())->toBeFalse()
        ->and($capture->popupHeading())->toBeEmpty()
        ->and($capture->popupFields())->toBe(LeadFieldSet::Email)
        ->and($capture->popupTrigger())->toBe(PopupTrigger::Delay)
        ->and($capture->popupTriggerValue())->toBe(PopupTrigger::Delay->defaultValue())
        ->and($capture->popupFrequencyDays())->toBe(7)
        ->and($capture->callBarEnabled())->toBeFalse()
        ->and($capture->callBarLabel())->toBe('Call now');
});

it('needs a heading before it will call the popup enabled', function (): void {
    // Switched on but never written — an empty modal is worse than none.
    $capture = captureFor(['popup' => ['enabled' => true, 'heading' => '   ']]);

    expect($capture->popupEnabled())->toBeFalse();
});

it('clamps a trigger threshold that would never fire, or fire too soon', function (array $popup, int $expected): void {
    expect(captureFor(['popup' => $popup])->popupTriggerValue())->toBe($expected);
})->with([
    'a zero delay would fire before the page painted' => [['trigger' => 'delay', 'trigger_value' => 0], 1],
    'a two-hour delay is past any real visit' => [['trigger' => 'delay', 'trigger_value' => 7200], 120],
    'scrolling 400% never happens' => [['trigger' => 'scroll', 'trigger_value' => 400], 100],
    'a 1% scroll is not a signal of interest' => [['trigger' => 'scroll', 'trigger_value' => 1], 5],
    'exit intent reads no threshold at all' => [['trigger' => 'exit_intent', 'trigger_value' => 99], 0],
]);

it('accepts a numeric string, which is how Filament stores a number input', function (): void {
    $capture = captureFor(['popup' => ['trigger' => 'delay', 'trigger_value' => '12', 'frequency_days' => '3']]);

    expect($capture->popupTriggerValue())->toBe(12)
        ->and($capture->popupFrequencyDays())->toBe(3);
});

it('bounds the cooling-off period', function (int $stored, int $expected): void {
    expect(captureFor(['popup' => ['frequency_days' => $stored]])->popupFrequencyDays())->toBe($expected);
})->with([
    'a four-thousand-day cap is a typo, not a policy' => [4000, 365],
    'zero legitimately means every visit' => [0, 0],
]);

it('reads the call bar, defaulting its label', function (): void {
    $capture = captureFor(['call_bar' => ['enabled' => true]]);

    expect($capture->callBarEnabled())->toBeTrue()
        ->and($capture->callBarLabel())->toBe('Call now')
        // No popup configured, so there is nothing for the second button to open.
        ->and($capture->callBarOffersPopup())->toBeFalse();
});

it('offers the popup from the call bar only when the popup itself is live', function (): void {
    $capture = captureFor([
        'popup' => ['enabled' => true, 'heading' => 'Get 10% off'],
        'call_bar' => ['enabled' => true, 'show_popup_button' => true],
    ]);

    expect($capture->callBarOffersPopup())->toBeTrue();
});

it('reads nothing at all outside tenancy', function (): void {
    // The settings query would be unscoped by RLS there.
    expect(resolve(SiteCapture::class)->popupEnabled())->toBeFalse();
});
