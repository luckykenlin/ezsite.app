<?php

declare(strict_types=1);

use App\Enums\PopupTrigger;

test('every trigger that takes a value has a unit and a sane default', function (): void {
    // Structural, over cases(): a new trigger is covered by being declared.
    // The editor hides the value field when takesValue() is false, so a
    // valueless trigger must also carry no unit and a zero default — a unit
    // on a hidden field is copy nobody can read, and a non-zero default
    // would suggest a threshold that never fires.
    foreach (PopupTrigger::cases() as $trigger) {
        if ($trigger->takesValue()) {
            expect($trigger->valueLabel())->not->toBeEmpty()
                ->and($trigger->defaultValue())->toBeGreaterThan(0);
        } else {
            expect($trigger->valueLabel())->toBeEmpty()
                ->and($trigger->defaultValue())->toBe(0);
        }
    }
});

test('every trigger carries a label', function (): void {
    $labels = array_map(static fn (PopupTrigger $trigger): string => $trigger->getLabel(), PopupTrigger::cases());

    expect($labels)->not->toContain('')
        ->and(array_unique($labels))->toHaveSameSize($labels);
});
