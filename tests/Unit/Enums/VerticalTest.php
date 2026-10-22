<?php

declare(strict_types=1);

use App\Enums\LinkKind;
use App\Enums\Vertical;
use App\Themes\Theme;

test('every vertical offers at least one theme, each at most once', function (): void {
    foreach (Vertical::cases() as $vertical) {
        $themes = $vertical->themes();

        expect($themes)->not->toBeEmpty()
            ->and(array_unique(array_map(static fn (Theme $theme): string => $theme->value, $themes)))->toHaveSameSize($themes);
    }
});

test('every vertical books through booking links only', function (): void {
    // A non-booking kind here would put "Book now" on an Instagram profile.
    foreach (Vertical::cases() as $vertical) {
        $kinds = $vertical->bookingLinkKinds();

        expect($kinds)->not->toBeEmpty()
            ->and(array_filter($kinds, static fn (LinkKind $kind): bool => ! $kind->isBooking()))->toBeEmpty()
            ->and(array_unique($kinds, SORT_REGULAR))->toHaveSameSize($kinds);
    }
});

test('every vertical names its catalogue, team and gallery', function (): void {
    // Calling each for every case also proves the matches are exhaustive: a case
    // added without its vocabulary throws UnhandledMatchError right here.
    foreach (Vertical::cases() as $vertical) {
        expect([
            $vertical->getLabel(),
            $vertical->catalogLabel(),
            $vertical->catalogItemLabel(),
            $vertical->teamLabel(),
            $vertical->teamMemberLabel(),
            $vertical->galleryLabel(),
        ])->not->toContain('')
            ->and($vertical->catalogLabel())->not->toBe($vertical->catalogItemLabel())
            ->and($vertical->teamLabel())->not->toBe($vertical->teamMemberLabel());
    }
});

test('every vertical declares a schema.org type name', function (): void {
    foreach (Vertical::cases() as $vertical) {
        expect($vertical->schemaOrgType())->toMatch('/^[A-Z][A-Za-z]+$/');
    }
});

test('a nail salon books by the appointment slot', function (): void {
    // The defining property: its services have durations, unlike a menu.
    expect(Vertical::NailSalon->usesDuration())->toBeTrue()
        ->and(Vertical::NailSalon->schemaOrgType())->toBe('NailSalon');
});
