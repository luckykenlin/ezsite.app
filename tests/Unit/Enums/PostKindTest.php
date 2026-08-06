<?php

declare(strict_types=1);

use App\Enums\PostKind;

test('only an offer and an event need a date range', function (): void {
    // Surprising and load-bearing: Google requires the event{} object — schedule
    // included — for OFFER as much as for EVENT, and omitting it for an offer is
    // the commonest 400 in a first implementation.
    $dated = array_values(array_filter(
        PostKind::cases(),
        static fn (PostKind $kind): bool => $kind->requiresDateRange(),
    ));

    expect($dated)->toBe([PostKind::Offer, PostKind::Event]);
});

test('only an offer carries a coupon', function (): void {
    $offers = array_values(array_filter(
        PostKind::cases(),
        static fn (PostKind $kind): bool => $kind->isOffer(),
    ));

    expect($offers)->toBe([PostKind::Offer]);
});

test('every kind states what it is, in colour, with a reason to pick it', function (): void {
    // Calling all three for every case also proves the matches are exhaustive: a
    // case added without copy throws UnhandledMatchError right here, which is the
    // failure worth catching — a blank option in the composer's first question.
    $labels = array_map(static fn (PostKind $kind): string => $kind->getLabel(), PostKind::cases());
    $hints = array_map(static fn (PostKind $kind): string => $kind->hint(), PostKind::cases());
    $colors = array_map(static fn (PostKind $kind): string => $kind->getColor(), PostKind::cases());

    expect($labels)->not->toContain('')
        ->and(array_unique($labels))->toHaveSameSize($labels)
        ->and($hints)->not->toContain('')
        ->and(array_unique($hints))->toHaveSameSize($hints)
        ->and($colors)->not->toContain('');
});

test('the plain update is the default kind', function (): void {
    // What the composer opens on, and what the column defaults to — so the ten raw
    // Post::create() call sites in the tenancy tests get something sensible.
    expect(PostKind::cases()[0])->toBe(PostKind::Update)
        ->and(PostKind::Update->value)->toBe('update');
});
