<?php

declare(strict_types=1);

use App\Actions\Pages\CacheChatTurn;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Sleep;

/**
 * The editor chat's SSE route: it tails what the queue worker writes to the turn
 * cache and forwards only the increment, so the operator sees the answer typed
 * out instead of appearing all at once.
 *
 * The suite fakes Sleep, so `Sleep::whenFakingSleep()` is the seam that stands
 * in for the worker: it runs between the route's reads, which is exactly when a
 * real worker would have appended more text.
 */
beforeEach(function (): void {
    // Through the session, not actingAs(): the route resolves the user itself,
    // and a pre-resolved guard would hide a check that never reads the session.
    $this->actingAsThroughSession(User::factory()->create());
});

function chatStreamResponse(string $token): string
{
    return test()->get(sprintf(
        'http://acme.%s/_editor/chat-stream?token=%s',
        test()->centralDomain(),
        $token,
    ))->streamedContent();
}

it('forwards each new slice as the worker writes it, then closes on the result', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();

    $this->runInTenant($tenant, function (): void {
        $turns = resolve(CacheChatTurn::class);
        $turns->handle('tok', 'Shortening');

        // The worker's remaining writes, one per read the route makes.
        $writes = [
            fn () => $turns->handle('tok', 'Shortening the'),
            fn () => $turns->handle('tok', 'Shortening the headline.'),
            fn () => $turns->handle('tok', 'Shortening the headline.', [
                ['key' => 'k1', 'type' => 'hero', 'data' => ['heading' => 'Fresh']],
            ]),
        ];

        Sleep::whenFakingSleep(function () use (&$writes): void {
            $write = array_shift($writes);

            if ($write !== null) {
                $write();
            }
        });

        $content = chatStreamResponse('tok');

        // Only what is new each time, so the browser's whole job is appending —
        // and the closing sentinel once the result lands.
        expect($content)->toContain('Shortening')
            ->toContain(' the')
            ->toContain(' headline.')
            ->toContain('</stream>')
            // Never the same text twice: a resend would duplicate it on screen.
            ->and(mb_substr_count($content, 'Shortening'))->toBe(1)
            // The frame name is a contract with the browser, not decoration:
            // EventSource delivers a NAMED event only to a listener for that
            // name, so editor.ts listens for 'update' and nothing else. Getting
            // this wrong is silent — the reply simply never types out.
            ->and($content)->toContain('event: update')
            // And the payload is JSON, which is the other half of that contract.
            ->and($content)->toContain('data: {"t":"text","v":"Shortening"}');
    });
});

/*
 * eventStream() writes `data: <message>` with no encoding of its own, so a raw
 * newline used to end the SSE frame early and the browser dropped everything
 * after it — which is most of a markdown reply, the shape this assistant answers
 * in. JSON frames are what make the reply survive the trip.
 */
it('keeps a reply that contains newlines intact', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();

    $this->runInTenant($tenant, function (): void {
        resolve(CacheChatTurn::class)->handle('tok', "Changed two things:\n\n- the headline\n- the button", []);

        $content = chatStreamResponse('tok');

        expect($content)
            // Escaped inside one frame rather than splitting into several.
            ->toContain('\n\n- the headline\n- the button')
            ->and(mb_substr_count($content, 'event: update'))->toBe(2);
    });
});

it('forwards each activity line as the turn announces it', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();

    $this->runInTenant($tenant, function (): void {
        $turns = resolve(CacheChatTurn::class);
        $turns->handle('tok', '', activity: ['Rewriting the Hero block…']);

        $writes = [
            fn () => $turns->handle('tok', '', activity: ['Rewriting the Hero block…', 'Adding a Cta block…']),
            fn () => $turns->handle('tok', 'Done.', [], activity: ['Rewriting the Hero block…', 'Adding a Cta block…']),
        ];

        Sleep::whenFakingSleep(function () use (&$writes): void {
            $write = array_shift($writes);

            if ($write !== null) {
                $write();
            }
        });

        $content = chatStreamResponse('tok');

        // Typed apart from the reply, so the panel can draw them as progress
        // above the answer rather than as part of it.
        expect($content)->toContain('{"t":"activity","v":"Rewriting the Hero block…"}')
            ->toContain('{"t":"activity","v":"Adding a Cta block…"}')
            ->toContain('{"t":"text","v":"Done."}')
            // Each line once: they are appended client-side, so a resend would
            // show the same step twice.
            ->and(mb_substr_count($content, 'Adding a Cta block'))->toBe(1);
    });
});

it('tells the browser to reload the canvas when the turn repaints it', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();

    $this->runInTenant($tenant, function (): void {
        $turns = resolve(CacheChatTurn::class);
        $turns->handle('tok', '');

        $writes = [
            // The worker painted the preview once (its counter moved), then
            // finished. The same counter value must not be forwarded twice —
            // each reload costs the iframe a full fetch.
            fn () => $turns->handle('tok', '', preview: 1),
            fn () => $turns->handle('tok', 'Done.', [], preview: 1),
        ];

        Sleep::whenFakingSleep(function () use (&$writes): void {
            $write = array_shift($writes);

            if ($write !== null) {
                $write();
            }
        });

        $content = chatStreamResponse('tok');

        expect($content)->toContain('{"t":"canvas","v":"1"}')
            ->and(mb_substr_count($content, '"t":"canvas"'))->toBe(1);
    });
});

it('closes immediately when the turn is already finished', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();

    $this->runInTenant($tenant, function (): void {
        resolve(CacheChatTurn::class)->handle('tok', 'Done.', []);

        expect(chatStreamResponse('tok'))
            ->toContain('Done.')
            ->toContain('</stream>');
    });
});

it('stops tailing when the operator abandons the turn mid-flight', function (): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();

    $this->runInTenant($tenant, function (): void {
        $turns = resolve(CacheChatTurn::class);
        $turns->handle('tok', 'Thinking');

        // cancelChatTurn() forgets the entry while this connection is open; with
        // nothing left to tail the stream ends rather than spinning to its cap.
        Sleep::whenFakingSleep(fn () => $turns->forget('tok'));

        expect(chatStreamResponse('tok'))->toContain('Thinking');
    });
});

it('404s a missing or unknown token', function (?string $query): void {
    Tenant::factory()->withDomain('acme')->create();

    $this->get(sprintf('http://acme.%s/_editor/chat-stream%s', $this->centralDomain(), $query ?? ''))
        ->assertNotFound();
})->with([
    'no token' => [null],
    'unknown token' => ['?token=bogus'],
]);

it('does not resolve a token across tenants', function (): void {
    $acme = Tenant::factory()->withDomain('acme')->create();
    Tenant::factory()->withDomain('beta')->create();

    $this->runInTenant($acme, fn () => resolve(CacheChatTurn::class)->handle('tok', 'Done.', []));

    // The turn cache is tenant-prefixed, so another tenant's editor cannot read
    // this turn even holding the token.
    $this->get(sprintf('http://beta.%s/_editor/chat-stream?token=tok', $this->centralDomain()))
        ->assertNotFound();
});

it('streams to nobody who is not signed in', function (): void {
    $this->flushSession();

    Tenant::factory()->withDomain('acme')->create();

    $this->get(sprintf('http://acme.%s/_editor/chat-stream?token=tok', $this->centralDomain()))
        ->assertNotFound();
});
