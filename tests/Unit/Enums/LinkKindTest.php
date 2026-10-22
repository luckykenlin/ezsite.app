<?php

declare(strict_types=1);

use App\Enums\LinkKind;

test('every kind has a distinct label', function (): void {
    $labels = array_map(static fn (LinkKind $kind): string => $kind->getLabel(), LinkKind::cases());

    expect($labels)->not->toContain('')
        ->and(array_unique($labels))->toHaveSameSize($labels);
});

test('every value is a lowercase slug, written the way the brand writes itself', function (): void {
    // Stored values, so they are spelled like the brand ("tiktok",
    // "glossgenius"), not derived from the case name ("tik-tok").
    foreach (LinkKind::cases() as $kind) {
        expect($kind->value)->toMatch('/^[a-z]+(-[a-z]+)*$/');
    }

    expect(LinkKind::TikTok->value)->toBe('tiktok')
        ->and(LinkKind::GlossGenius->value)->toBe('glossgenius')
        ->and(LinkKind::OtherBooking->value)->toBe('other-booking');
});

test('no kind is more than one of booking, social and ordering', function (): void {
    // A link is followed, booked through or ordered from — never two at once,
    // or it would render twice (a footer icon AND the "Book now" button).
    foreach (LinkKind::cases() as $kind) {
        $roles = array_filter([$kind->isBooking(), $kind->isSocial(), $kind->isOrdering()]);

        expect(count($roles))->toBeLessThanOrEqual(1, $kind->name);
    }
});

test('each role has at least one kind', function (): void {
    $cases = LinkKind::cases();

    expect(array_filter($cases, static fn (LinkKind $kind): bool => $kind->isBooking()))->not->toBeEmpty()
        ->and(array_filter($cases, static fn (LinkKind $kind): bool => $kind->isSocial()))->not->toBeEmpty()
        ->and(array_filter($cases, static fn (LinkKind $kind): bool => $kind->isOrdering()))->not->toBeEmpty();
});
