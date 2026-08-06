<?php

declare(strict_types=1);

use App\Enums\PostCtaAction;

test('the buttons are exactly Google Business Profile six action types', function (): void {
    // The set is closed for a reason: a seventh of our own would be
    // untranslatable the day the connector ships, and Google rejects an unknown
    // actionType rather than ignoring it. Asserted as literals, not derived — the
    // whole point is that these are somebody else's names.
    expect(array_map(
        static fn (PostCtaAction $action): string => $action->toGoogleActionType(),
        PostCtaAction::cases(),
    ))->toBe(['BOOK', 'ORDER', 'SHOP', 'LEARN_MORE', 'SIGN_UP', 'CALL']);
});

test('only the call button goes without a url', function (): void {
    // Google uses the profile's own phone number for CALL and rejects an action
    // that carries a url at all, so this asymmetry is the platform's.
    $withoutUrl = array_values(array_filter(
        PostCtaAction::cases(),
        static fn (PostCtaAction $action): bool => ! $action->requiresUrl(),
    ));

    expect($withoutUrl)->toBe([PostCtaAction::Call]);
});

test('every button reads as its own instruction', function (): void {
    $labels = array_map(static fn (PostCtaAction $action): string => $action->getLabel(), PostCtaAction::cases());

    expect($labels)->not->toContain('')
        ->and(array_unique($labels))->toHaveSameSize($labels);
});
