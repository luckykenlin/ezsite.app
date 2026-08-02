<?php

declare(strict_types=1);

use App\Mail\SiteMailIdentity;

test('the sender is our verified domain wearing the site name', function (): void {
    // The rule this class exists to hold: mail cannot leave as the tenant's own
    // address without failing their SPF, so the tenant identifies itself in the
    // display name instead.
    $from = new SiteMailIdentity('Golden Dragon', '#b91c1c')->from();

    expect($from->address)->toBe(config()->string('mail.from.address'))
        ->and($from->name)->toBe('Golden Dragon');
});

test('a reply to the site goes to the business address, under the site name', function (): void {
    $replyTo = new SiteMailIdentity('Golden Dragon', '#b91c1c', 'hello@golden.test')->replyTo();

    expect($replyTo)->toHaveCount(1)
        ->and($replyTo[0]->address)->toBe('hello@golden.test')
        ->and($replyTo[0]->name)->toBe('Golden Dragon');
});

test('a site with no contact address gets no reply-to header at all', function (): void {
    // Better than a header pointing at our own sending address, which would
    // silently swallow a customer's reply.
    expect(new SiteMailIdentity('Golden Dragon')->replyTo())->toBeEmpty();
});

test('the accent falls back to the default unless it is a six-digit hex', function (string $stored, string $expected): void {
    // brand_primary is written by operators and by the AI draft pipeline, and
    // lands in an inline style attribute — so the guard belongs here, not in
    // five email views.
    expect(new SiteMailIdentity('Golden Dragon', $stored)->accent())->toBe($expected);
})->with([
    'a brand hex' => ['#b91c1c', '#b91c1c'],
    // ColorPalette::validHex() normalises case, which is where this gate lives.
    'uppercase' => ['#B91C1C', '#b91c1c'],
    'three-digit shorthand' => ['#b11', SiteMailIdentity::DEFAULT_ACCENT],
    'a colour name' => ['red', SiteMailIdentity::DEFAULT_ACCENT],
    'an injection attempt' => ['#fff; background-image: url(x)', SiteMailIdentity::DEFAULT_ACCENT],
    'empty' => ['', SiteMailIdentity::DEFAULT_ACCENT],
]);
