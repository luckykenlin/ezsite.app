<?php

declare(strict_types=1);

use App\Design\DesignTokens;
use App\Design\TokenKey;
use App\Design\TokenOptions;

/*
 * Structural invariants over ::cases() rather than a copy of the four keys —
 * a fifth token then has to satisfy all of this on the day it is added, which
 * is the entire reason this enum exists.
 */
it('resolves every key to a usable token vocabulary', function (TokenKey $key): void {
    $options = TokenOptions::for($key);

    expect($key->label())->not->toBeEmpty()
        ->and($options)->not->toBeEmpty()
        // Every option is a real case of this key's own enum, so a form can
        // never offer a value the strict writer would then reject.
        ->and(array_keys($options))->each->toBeIn(array_map(
            static fn (BackedEnum $case): string => (string) $case->value,
            $key->tokenClass()::cases(),
        ));
})->with(TokenKey::cases());

it('reads its own value off a token set', function (TokenKey $key): void {
    $tokens = DesignTokens::default();

    expect($key->valueOn($tokens))->toBeIn(array_keys(TokenOptions::for($key)));
})->with(TokenKey::cases());

it('accepts a real value for its key and refuses anything else', function (TokenKey $key): void {
    $legal = array_key_first(TokenOptions::for($key));

    expect($key->tryValue($legal))->not->toBeNull()
        ->and($key->tryValue('not-a-token'))->toBeNull()
        // Non-strings arrive from JSON payloads and tool arguments alike.
        ->and($key->tryValue(null))->toBeNull()
        ->and($key->tryValue(42))->toBeNull();
})->with(TokenKey::cases());

/*
 * The link that keeps this enum honest.
 *
 * DesignTokens keeps an explicitly typed constructor and toArray() — generating
 * them from here would trade `ColorPalette` for `BackedEnum` at every read site
 * — so the two lists are maintained separately and could drift. They must not:
 * a key present in one and absent from the other means either an unwritable
 * token (no form field, no tool argument) or an unpreviewable one (dropped by
 * previewDesign()). This is the test that turns that into a build failure.
 */
it('enumerates exactly the keys a token set serialises', function (): void {
    $serialised = array_keys(DesignTokens::default()->toArray());

    expect(TokenKey::values())->toBe(array_values(array_diff($serialised, ['preset'])));
});
