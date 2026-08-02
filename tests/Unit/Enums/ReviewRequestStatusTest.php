<?php

declare(strict_types=1);

use App\Enums\ReviewRequestStatus;

test('the states stop at opened, because anything past it would be invented', function (): void {
    // Deliberately no `converted`. Whether a customer actually LEFT a review can only
    // be known by reading the reviews back off Google, which needs the API approval
    // this feature exists to not wait for — and a dashboard caught inferring it from
    // a click stops being believed about everything else.
    expect(ReviewRequestStatus::cases())->toBe([
        ReviewRequestStatus::Queued,
        ReviewRequestStatus::Sent,
        ReviewRequestStatus::Clicked,
    ]);
});

test('every state reads as its own word, in its own colour', function (): void {
    // Calling both for every case proves the matches are exhaustive: a case added
    // without copy throws UnhandledMatchError here rather than rendering blank in a
    // table.
    $labels = array_map(static fn (ReviewRequestStatus $status): string => $status->getLabel(), ReviewRequestStatus::cases());
    $colors = array_map(static fn (ReviewRequestStatus $status): string => $status->getColor(), ReviewRequestStatus::cases());

    expect($labels)->not->toContain('')
        ->and(array_unique($labels))->toHaveCount(count($labels))
        ->and($colors)->not->toContain('')
        // Only the end state is celebrated; a card nobody has scanned is not a
        // failure, it is a card nobody has scanned.
        ->and(ReviewRequestStatus::Clicked->getColor())->toBe('success');
});
