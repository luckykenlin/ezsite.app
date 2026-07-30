<?php

declare(strict_types=1);

use App\Actions\Pages\CacheChatTurn;
use App\Models\Tenant;
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
            ->and($content)->toContain('data: Shortening');
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
