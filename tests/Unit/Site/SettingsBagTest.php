<?php

declare(strict_types=1);

use App\Site\SettingsBag;

it('reads typed values and treats every malformed shape as absent', function (): void {
    $bag = new SettingsBag([
        'popup' => [
            'heading' => '  Spring offer  ',
            'enabled' => true,
            'delay' => 15,
            'scroll' => '40',
            'broken' => ['an', 'array'],
        ],
        'not_a_group' => 'scalar',
    ]);

    expect($bag->string('popup', 'heading'))->toBe('Spring offer')
        ->and($bag->bool('popup', 'enabled'))->toBeTrue()
        // Only a real true counts — "1" from a form is not a switched-on flag.
        ->and($bag->bool('popup', 'delay'))->toBeFalse()
        ->and($bag->int('popup', 'delay'))->toBe(15)
        // Filament stores a TextInput's numeric value as a string.
        ->and($bag->int('popup', 'scroll'))->toBe(40)
        ->and($bag->int('popup', 'heading'))->toBeNull()
        ->and($bag->string('popup', 'broken'))->toBeEmpty()
        ->and($bag->value('not_a_group', 'anything'))->toBeNull()
        ->and($bag->value('missing', 'anything'))->toBeNull();
});
