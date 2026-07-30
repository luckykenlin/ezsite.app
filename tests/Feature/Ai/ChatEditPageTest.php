<?php

declare(strict_types=1);

use App\Actions\Pages\ChatEditPage;
use App\Ai\Agents\PageEditorAgent;
use App\Enums\ChatRole;
use App\Models\Page;
use App\Models\PageChatMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\Data\ToolCall;

/*
 * One turn of the editor chat, end to end. The SDK's fake gateway executes real
 * tool calls, so these exercise the whole AI → tool → blocks path rather than
 * stubbing it: fake a ToolCall, and the agent's actual tool runs against the
 * actual draft.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->page = $this->createTenantPage($this->tenant, []);
});

function chatBlocks(): array
{
    return [
        ['key' => 'k1', 'type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Old headline']],
        ['key' => 'k2', 'type' => 'cta', 'data' => ['heading' => 'Come by']],
    ];
}

function toolCall(string $name, array $arguments): ToolCall
{
    return new ToolCall('call-1', $name, $arguments);
}

/**
 * Run a turn in the page's tenant context, the way the editor does.
 */
function chatTurn(array $blocks, string $message = 'Shorten the headline', ?User $user = null, ?Closure $onDelta = null): array
{
    return test()->runInTenant(
        test()->tenant,
        fn (): array => resolve(ChatEditPage::class)->handle(test()->page, $blocks, $message, $user, $onDelta),
    );
}

it('applies the edit its tool made and returns the new blocks', function (): void {
    PageEditorAgent::fake([
        toolCall('UpdateBlockContent', ['key' => 'k1', 'content' => ['heading' => 'Fresh bread daily']]),
        'Shortened the hero headline.',
    ])->preventStrayPrompts();

    $result = chatTurn(chatBlocks());

    expect($result['blocks'][0]['data']['heading'])->toBe('Fresh bread daily')
        ->and($result['reply'])->toBe('Shortened the hero headline.')
        ->and($result['failed'])->toBeFalse()
        // Untouched blocks come back byte-identical, so the caller can compare.
        ->and($result['blocks'][1])->toBe(chatBlocks()[1]);
});

it('never writes the page itself — the editor decides when to save', function (): void {
    PageEditorAgent::fake([
        toolCall('UpdateBlockContent', ['key' => 'k1', 'content' => ['heading' => 'Fresh bread daily']]),
        'Done.',
    ]);

    chatTurn(chatBlocks());

    // The operator reviews it on the canvas and hits Save; nothing persisted here.
    expect(Page::query()->findOrFail($this->page->getKey())->blocks)->toBeEmpty();
});

it('records both sides of the turn, attributed and counted', function (): void {
    PageEditorAgent::fake([
        toolCall('UpdateBlockContent', ['key' => 'k1', 'content' => ['heading' => 'Fresh bread daily']]),
        'Shortened the hero headline.',
    ]);

    $user = User::factory()->create();

    chatTurn(chatBlocks(), 'Shorten the headline', $user);

    $transcript = PageChatMessage::query()->orderBy('id')->get();

    expect($transcript)->toHaveCount(2)
        ->and($transcript[0]->role)->toBe(ChatRole::User)
        ->and($transcript[0]->content)->toBe('Shorten the headline')
        ->and($transcript[0]->user_id)->toBe($user->id)
        ->and($transcript[0]->changed_blocks)->toBeNull()
        ->and($transcript[1]->role)->toBe(ChatRole::Assistant)
        ->and($transcript[1]->changedThePage())->toBeTrue()
        ->and($transcript[1]->tenant_id)->toBe($this->tenant->id);
});

it('counts an answer that changed nothing as no edit', function (): void {
    PageEditorAgent::fake(['The hero block is the big banner at the top of your page.']);

    $result = chatTurn(chatBlocks(), 'What does the hero block do?');

    expect($result['blocks'])->toBe(chatBlocks())
        ->and(PageChatMessage::query()->where('role', ChatRole::Assistant)->sole()->changedThePage())->toBeFalse();
});

it('counts added and removed blocks', function (): void {
    PageEditorAgent::fake([
        toolCall('AddBlock', ['type' => 'features']),
        toolCall('RemoveBlock', ['key' => 'k2']),
        'Added a features section and removed the call to action.',
    ]);

    $result = chatTurn(chatBlocks(), 'Swap the CTA for a features section');

    expect(array_column($result['blocks'], 'type'))->toBe(['hero', 'features'])
        ->and(PageChatMessage::query()->where('role', ChatRole::Assistant)->sole()->changed_blocks)->toBe(2);
});

/*
 * A pure reorder leaves every block's data untouched, so a naive per-block diff
 * would report "nothing changed" for a real edit — and the editor would then skip
 * applyBlocks() and silently discard it.
 */
it('counts a pure reorder as a change even though no block data differs', function (): void {
    PageEditorAgent::fake([
        toolCall('ReorderBlocks', ['keys' => ['k2', 'k1']]),
        'Moved the call to action to the top.',
    ]);

    $result = chatTurn(chatBlocks(), 'Move the CTA up');

    expect(array_column($result['blocks'], 'key'))->toBe(['k2', 'k1'])
        ->and(PageChatMessage::query()->where('role', ChatRole::Assistant)->sole()->changed_blocks)->toBe(1);
});

it('falls back to a stand-in reply when the model returns no prose', function (): void {
    PageEditorAgent::fake([
        toolCall('UpdateBlockContent', ['key' => 'k1', 'content' => ['heading' => 'Fresh bread daily']]),
        '   ',
    ]);

    expect(chatTurn(chatBlocks())['reply'])->toBe('Done.');
});

/*
 * A provider outage must not cost the operator their uncommitted work, and a
 * half-applied edit would be worse than none — so the blocks come back
 * untouched and the failure is visible in the transcript.
 */
it('keeps the page unchanged and apologizes when the provider fails', function (): void {
    PageEditorAgent::fake(fn () => throw new RuntimeException('provider exploded'));

    Log::spy();

    $result = chatTurn(chatBlocks());

    expect($result['blocks'])->toBe(chatBlocks())
        ->and($result['failed'])->toBeTrue()
        ->and($result['reply'])->toContain("couldn't reach the assistant")
        ->and(PageChatMessage::query()->where('role', ChatRole::Assistant)->sole()->changed_blocks)->toBeNull();

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message, array $context): bool => $message === 'page_chat.failed'
            && $context['exception'] === 'provider exploded')
        ->once();
});

/*
 * Regression: a slow turn used to die on PHP's max_execution_time (30s by
 * default, shorter than one provider round trip). A fatal is not a Throwable,
 * so it skipped the apology above — and since wire:stream had already written
 * to the response body, the 500 could not set its headers and the editor
 * rendered a BLANK modal over the canvas. The budget below is ours, so it
 * throws and lands in the same catch as any provider failure.
 */
it('gives up with an apology when a turn outruns its budget', function (): void {
    Log::spy();

    // A provider slower than the budget. The suite freezes the clock, so moving
    // it is what "slow" means here — and the SDK calls this closure before it
    // yields the first event, which is exactly where a real turn spends its time
    // waiting. `test()->` because travel() is a TestCase method, not a helper.
    PageEditorAgent::fake(function (): string {
        test()->travel(100)->seconds();

        return 'Still going...';
    });

    $result = chatTurn(chatBlocks(), 'Rewrite every block');

    expect($result['failed'])->toBeTrue()
        ->and($result['blocks'])->toBe(chatBlocks())
        ->and($result['reply'])->toContain("couldn't reach the assistant");

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message, array $context): bool => $message === 'page_chat.failed'
            && str_contains($context['exception'], 'second budget'))
        ->once();
});

it('reads a page transcript back oldest first', function (): void {
    PageEditorAgent::fake(['First answer.', 'Second answer.']);

    chatTurn(chatBlocks(), 'First question');
    chatTurn(chatBlocks(), 'Second question');

    $transcript = $this->runInTenant(
        $this->tenant,
        fn (): array => resolve(ChatEditPage::class)->transcript($this->page),
    );

    expect(array_column($transcript, 'content'))->toBe([
        'First question', 'First answer.', 'Second question', 'Second answer.',
    ])->and(array_column($transcript, 'role'))->toBe(['user', 'assistant', 'user', 'assistant']);
});

/*
 * The model answers in light markdown — a table of what is on the page reads far
 * better than a wall of pipes — so assistant turns are rendered to HTML for the
 * panel. That output is untrusted text going into the operator's browser, which
 * is the interesting half of these.
 */
it('renders an assistant answer from markdown to html', function (): void {
    PageEditorAgent::fake(["Rewrote **two** sections:\n\n- Hero\n- Contact"]);

    chatTurn(chatBlocks(), 'Rewrite the copy');

    $assistant = $this->runInTenant(
        $this->tenant,
        fn (): array => resolve(ChatEditPage::class)->transcript($this->page),
    )[1];

    expect($assistant['html'])->toContain('<strong>two</strong>')
        ->toContain('<li>Hero</li>')
        // The raw markdown stays available for anything that wants the text.
        ->and($assistant['content'])->toContain('**two**');
});

it('shows the operator their own words verbatim, never as markup', function (): void {
    PageEditorAgent::fake(['Done.']);

    chatTurn(chatBlocks(), 'Make **this** shorter');

    $transcript = $this->runInTenant(
        $this->tenant,
        fn (): array => resolve(ChatEditPage::class)->transcript($this->page),
    );

    // Their message is theirs: the panel escapes it, so it must not arrive as
    // pre-rendered HTML that would interpret whatever they happened to type.
    expect($transcript[0]['html'])->toBeNull()
        ->and($transcript[0]['content'])->toBe('Make **this** shorter');
});

it('refuses markup and unsafe links inside an assistant answer', function (): void {
    PageEditorAgent::fake([
        "<img src=x onerror=alert(1)> and <b>bold</b>\n\n[click](javascript:alert(1))",
    ]);

    chatTurn(chatBlocks(), 'Do something');

    $html = $this->runInTenant(
        $this->tenant,
        fn (): array => resolve(ChatEditPage::class)->transcript($this->page),
    )[1]['html'];

    // A prompt injection reaching the transcript must not become live markup.
    expect($html)->not->toContain('<img')
        ->and($html)->not->toContain('onerror')
        ->and($html)->not->toContain('<b>')
        ->and($html)->not->toContain('javascript:');
});

it('scopes a transcript to its own tenant', function (): void {
    PageEditorAgent::fake(['Answer.']);

    chatTurn(chatBlocks(), 'My question');

    $otherTenant = Tenant::factory()->create();

    $visibleToOther = $this->runInTenant($otherTenant, fn (): int => PageChatMessage::query()->count());

    expect($visibleToOther)->toBe(0);
});

/*
 * Streaming is what makes a multi-block turn feel alive rather than hung: the
 * reply is handed over in chunks as the provider produces it, and the editor
 * types them into the panel. The deltas must reassemble into exactly the reply
 * that gets persisted — otherwise the operator watches one answer appear and
 * then sees a different one after the re-render.
 */
it('streams the reply in chunks that add up to the persisted answer', function (): void {
    PageEditorAgent::fake([
        toolCall('UpdateBlockContent', ['key' => 'k1', 'content' => ['heading' => 'Fresh bread daily']]),
        'Shortened the hero headline.',
    ]);

    $deltas = [];

    $result = chatTurn(chatBlocks(), 'Shorten the headline', null, function (string $delta) use (&$deltas): void {
        $deltas[] = $delta;
    });

    expect($deltas)->not->toBeEmpty()
        ->and(implode('', $deltas))->toBe('Shortened the hero headline.')
        ->and($result['reply'])->toBe('Shortened the hero headline.')
        // The tool ran during the stream, so the edit is in the returned blocks.
        ->and($result['blocks'][0]['data']['heading'])->toBe('Fresh bread daily');
});

it('runs fine without a delta listener', function (): void {
    PageEditorAgent::fake(['Done.']);

    expect(chatTurn(chatBlocks())['reply'])->toBe('Done.');
});
