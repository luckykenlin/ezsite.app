<?php

declare(strict_types=1);

use App\Design\StyleGroup;
use App\Design\TokenKey;

it('groups every design token exactly once', function (): void {
    // Fail-closed, and the reason this enum is worth testing at all: TokenKey
    // and StyleGroup are two independent lists, so a token added to the first
    // and forgotten in the second raises nothing — it just silently disappears
    // from every design surface.
    $grouped = [];

    foreach (StyleGroup::cases() as $group) {
        foreach ($group->keys() as $key) {
            $grouped[] = $key->value;
        }
    }

    expect($grouped)->toEqualCanonicalizing(TokenKey::values())
        ->and(array_unique($grouped))->toHaveSameSize($grouped);
});

it('leaves the theme group owning no single token, because the preset sets them all', function (): void {
    expect(StyleGroup::Theme->keys())->toBeEmpty();
});

it('gives every group a distinct label and a hint', function (): void {
    // Distinctness rather than exact copy: two groups sharing a label is a real
    // bug (the operator cannot tell the cards apart), while the wording is free
    // to evolve.
    $labels = array_map(static fn (StyleGroup $group): string => $group->label(), StyleGroup::cases());

    expect(array_unique($labels))->toHaveSameSize(StyleGroup::cases());

    foreach (StyleGroup::cases() as $group) {
        expect($group->hint())->not->toBeEmpty();
    }
});
