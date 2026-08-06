<?php

declare(strict_types=1);

use App\Actions\Pages\CacheChatTurn;
use App\Actions\Pages\CachePageEditorPreview;
use App\Actions\Pages\RecordPageChatMessage;
use App\Ai\Agents\PageEditorAgent;
use App\Design\StylePreset;
use App\Enums\ChatRole;
use App\Jobs\ChatEditPageJob;
use App\Models\Business;
use App\Models\Page;
use App\Models\PageChatMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolCall;

/*
 * The queued half of a chat turn. The turn's own behaviour lives in
 * ChatEditPageTest — these cover the job wrapper: that it edits the DRAFT it was
 * handed, publishes progress for the SSE route to tail, and that a failure of the
 * job ITSELF (not of the provider, which ChatEditPage contains) still reaches the
 * editor instead of leaving it polling a turn nobody will finish.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->page = $this->createTenantPage($this->tenant, []);
});

function chatJob(array $blocks, string $token = 'tok', ?int $userId = null, ?string $previewToken = null): ChatEditPageJob
{
    return new ChatEditPageJob(
        (string) test()->tenant->id,
        (int) test()->page->id,
        $userId,
        'Shorten the headline',
        $token,
        $blocks,
        null,
        $previewToken,
    );
}

function jobBlocks(): array
{
    return [['key' => 'k1', 'type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Old headline']]];
}

it('edits the draft it was handed and publishes the result for the editor', function (): void {
    PageEditorAgent::fake([
        new ToolCall('c1', 'UpdateBlockContent', ['key' => 'k1', 'content' => ['heading' => 'Fresh bread daily']]),
        'Shortened the headline.',
    ]);

    chatJob(jobBlocks())->handle();

    $turn = $this->runInTenant($this->tenant, fn (): ?array => resolve(CacheChatTurn::class)->read('tok'));

    expect($turn['status'])->toBe('done')
        ->and($turn['failed'])->toBeFalse()
        ->and($turn['reply'])->toBe('Shortened the headline.')
        ->and($turn['blocks'][0]['data']['heading'])->toBe('Fresh bread daily')
        // Still nothing written to the page: the operator reviews and saves.
        ->and(Page::query()->findOrFail($this->page->id)->blocks)->toBeEmpty();
});

it('publishes the reply as it streams so the stream route has something to tail', function (): void {
    PageEditorAgent::fake(['Shortened the hero headline for you.']);

    chatJob(jobBlocks())->handle();

    // The final write carries the whole reply; the intermediate ones are what the
    // SSE route forwards (PageEditorChatStreamTest drives those).
    $turn = $this->runInTenant($this->tenant, fn (): ?array => resolve(CacheChatTurn::class)->read('tok'));

    expect($turn['reply'])->toBe('Shortened the hero headline for you.');
});

it('publishes what the turn is doing, not only what it has said', function (): void {
    PageEditorAgent::fake([
        new ToolCall('c1', 'UpdateBlockContent', ['key' => 'k1', 'content' => ['heading' => 'Fresh bread daily']]),
        new ToolCall('c2', 'AddBlock', ['type' => 'cta']),
        'Rewrote the hero and added a call to action.',
    ]);

    chatJob(jobBlocks())->handle();

    $turn = $this->runInTenant($this->tenant, fn (): ?array => resolve(CacheChatTurn::class)->read('tok'));

    // The tool calls ARE the turn on this agent — the prose is written last — so
    // these lines are the only thing the panel can show for most of a long one.
    // They survive to the final write so a browser connecting late still sees the
    // steps it missed.
    expect($turn['activity'])->toBe([
        'Rewriting the Hero block…',
        'Adding a Cta block…',
    ]);
});

/*
 * The "watch it edit" half of the chat: after every tool the worker swaps the
 * canvas preview's blocks for the draft as it now stands and bumps the turn's
 * paint counter, which the SSE route turns into a reload-the-canvas frame. The
 * editor's own state is untouched — the result still lands through applyTurn().
 */
it('repaints the canvas preview after each tool call', function (): void {
    PageEditorAgent::fake([
        new ToolCall('c1', 'UpdateBlockContent', ['key' => 'k1', 'content' => ['heading' => 'Fresh bread daily']]),
        'Shortened the headline.',
    ]);

    $this->runInTenant($this->tenant, function (): void {
        // The entry the editor published when it last pushed a preview — what
        // the worker's paints splice into.
        resolve(CachePageEditorPreview::class)->handle($this->page, jobBlocks(), 'ptok');
    });

    chatJob(jobBlocks(), previewToken: 'ptok')->handle();

    [$entry, $turn] = $this->runInTenant($this->tenant, fn (): array => [
        Cache::get(CachePageEditorPreview::key('ptok')),
        resolve(CacheChatTurn::class)->read('tok'),
    ]);

    expect($entry['blocks'][0]['data']['heading'])->toBe('Fresh bread daily')
        // Keys move with the blocks — the canvas addresses blocks by key.
        ->and($entry['keys'])->toBe(['k1'])
        ->and($turn['preview'])->toBe(1);
});

/*
 * The style and chrome halves of "watch it edit": a recolour or a menu edit
 * used to paint nothing for the whole turn — up to ninety seconds of an
 * unchanged canvas while the reply claimed the site was being restyled.
 */
it('paints a staged style and chrome into the preview mid-turn', function (): void {
    $this->createTenantBusiness($this->tenant, [], 0);

    PageEditorAgent::fake([
        new ToolCall('c1', 'SetSiteStyle', ['preset' => 'warm-craft']),
        new ToolCall('c2', 'UpdateChrome', ['slot' => 'header', 'add_links' => [['label' => 'Pricing', 'url' => '/pricing']]]),
        'Restyled the site and added Pricing to the menu.',
    ])->preventStrayPrompts();

    $this->runInTenant($this->tenant, function (): void {
        resolve(CachePageEditorPreview::class)->handle(
            $this->page,
            jobBlocks(),
            'ptok',
            ['header' => ['type' => 'header', 'data' => []], 'footer' => ['type' => 'footer', 'data' => []]],
        );
    });

    chatJob(jobBlocks(), previewToken: 'ptok')->handle();

    $entry = $this->runInTenant($this->tenant, fn (): ?array => Cache::get(CachePageEditorPreview::key('ptok')));

    expect($entry['design_tokens']['preset'])->toBe('warm-craft')
        ->and($entry['chrome']['header'][0]['data']['nav_links'])->toBe([['label' => 'Pricing', 'url' => '/pricing']])
        // The untouched slot keeps what the editor pushed — a header edit must
        // not clobber a footer the operator was editing by hand.
        ->and($entry['chrome']['footer'])->toBe([['type' => 'footer', 'data' => []]]);
});

it('paints nothing when the editor never published a preview to paint into', function (): void {
    PageEditorAgent::fake([
        new ToolCall('c1', 'UpdateBlockContent', ['key' => 'k1', 'content' => ['heading' => 'Fresh bread daily']]),
        'Done.',
    ]);

    chatJob(jobBlocks(), previewToken: 'expired')->handle();

    $turn = $this->runInTenant($this->tenant, fn (): ?array => resolve(CacheChatTurn::class)->read('tok'));

    // No entry to splice into means nothing is on screen to go stale — the
    // counter never moves, so the browser is never told to reload.
    expect($turn['preview'])->toBe(0)
        ->and($turn['blocks'][0]['data']['heading'])->toBe('Fresh bread daily');
});

it('attributes the turn to the user who asked', function (): void {
    PageEditorAgent::fake(['Done.']);

    $user = User::factory()->create();

    chatJob(jobBlocks(), userId: $user->id)->handle();

    expect($this->runInTenant(
        $this->tenant,
        fn () => PageChatMessage::query()->where('role', 'user')->sole()->user_id,
    ))->toBe($user->id);
});

/*
 * ChatEditPage catches anything the provider throws, so the job only fails when
 * the JOB dies — a worker killed mid-turn by a deploy or `queue:restart`, a
 * timeout, a payload it cannot deserialise. The editor is polling a token by
 * then, so it has to be told, or it waits out its whole give-up window.
 */
it('reports a dead job to the editor with the page unchanged', function (): void {
    Log::spy();

    chatJob(jobBlocks())->failed(new RuntimeException('worker killed mid-turn'));

    $turn = $this->runInTenant($this->tenant, fn (): ?array => resolve(CacheChatTurn::class)->read('tok'));

    expect($turn['status'])->toBe('done')
        ->and($turn['failed'])->toBeTrue()
        ->and($turn['reply'])->toContain("couldn't finish that")
        // The blocks it was handed, untouched — never a half-applied edit.
        ->and($turn['blocks'])->toBe(jobBlocks());

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message, array $context): bool => $message === 'page_chat.job_failed'
            && $context['exception'] === 'worker killed mid-turn')
        ->once();
});

it('leaves the apology in the transcript, so a reload still sees an answer', function (): void {
    // The cache entry only reaches an editor still polling this token. A reload
    // drops it, and the operator used to be left with their own question and no
    // answer at all — permanently.
    Log::spy();

    chatJob(jobBlocks())->failed(new RuntimeException('worker killed mid-turn'));

    $transcript = PageChatMessage::query()->orderBy('id')->get();

    expect($transcript)->toHaveCount(2)
        ->and($transcript[0]->role)->toBe(ChatRole::User)
        ->and($transcript[0]->content)->toBe('Shorten the headline')
        ->and($transcript[1]->role)->toBe(ChatRole::Assistant)
        ->and($transcript[1]->content)->toContain("couldn't finish that")
        // Zero, not null: the apology must not draw the "edited the page" badge.
        ->and($transcript[1]->changed_blocks)->toBe(0)
        ->and($transcript[1]->changedThePage())->toBeFalse();
});

it('does not record the question twice when the turn already recorded it', function (): void {
    // The ordinary failure path (a provider blowing up inside ChatEditPage) has
    // already written the question; only an undeserialisable payload has not.
    Log::spy();

    $this->runInTenant($this->tenant, function (): void {
        resolve(RecordPageChatMessage::class)
            ->handle($this->page, null, ChatRole::User, 'Shorten the headline');
    });

    chatJob(jobBlocks())->failed(new RuntimeException('died later'));

    expect(PageChatMessage::query()->where('role', ChatRole::User)->count())->toBe(1)
        ->and(PageChatMessage::query()->count())->toBe(2);
});

it('records nothing when the page was deleted while the turn was in flight', function (): void {
    Log::spy();

    $pageId = (int) $this->page->id;
    $this->runInTenant($this->tenant, fn () => Page::query()->whereKey($pageId)->delete());

    chatJob(jobBlocks())->failed(new RuntimeException('worker killed mid-turn'));

    expect(PageChatMessage::query()->count())->toBe(0)
        // The cache write still happens; it is harmless with no editor to read it.
        ->and($this->runInTenant($this->tenant, fn (): ?array => resolve(CacheChatTurn::class)->read('tok'))['failed'])
        ->toBeTrue();
});

it('keeps an answer that landed just before the job died', function (): void {
    PageEditorAgent::fake([
        new ToolCall('c1', 'UpdateBlockContent', ['key' => 'k1', 'content' => ['heading' => 'Fresh bread daily']]),
        'Shortened the headline.',
    ]);

    $job = chatJob(jobBlocks());
    $job->handle();

    // Failing on the way out — after the result was published but before the job
    // was released. That answer is real, so it must not be overwritten with an
    // apology the operator would see instead of their edit.
    $job->failed(new RuntimeException('died releasing the job'));

    $turn = $this->runInTenant($this->tenant, fn (): ?array => resolve(CacheChatTurn::class)->read('tok'));

    expect($turn['failed'])->toBeFalse()
        ->and($turn['blocks'][0]['data']['heading'])->toBe('Fresh bread daily');
});

it('survives a failure whose exception is gone', function (): void {
    // Laravel passes null when the failure has no throwable behind it.
    chatJob(jobBlocks())->failed(null);

    expect($this->runInTenant(
        $this->tenant,
        fn (): ?array => resolve(CacheChatTurn::class)->read('tok'),
    )['failed'])->toBeTrue();
});

/*
 * The staged style has to survive the worker → cache → editor hop, which is a
 * serialisation boundary the blocks already cross. It travels beside them rather
 * than inside them because tokens are site-scoped — they belong to no page — and
 * the editor needs both halves to land under one undo snapshot.
 */
it('publishes a staged site style for the editor to pick up', function (): void {
    $this->createTenantBusiness($this->tenant, [], 0);

    PageEditorAgent::fake([
        new ToolCall('call-1', 'SetSiteStyle', ['preset' => 'warm-craft']),
        'Warmed the site up.',
    ])->preventStrayPrompts();

    chatJob(jobBlocks())->handle();

    $turn = $this->runInTenant($this->tenant, fn (): ?array => resolve(CacheChatTurn::class)->read('tok'));

    expect($turn['design']['preset'])->toBe('warm-craft')
        ->and($turn['design']['palette'])->toBe(StylePreset::WarmCraft->tokens()->palette->value)
        // And still nothing written — the operator applies it from the rail.
        ->and(Business::query()->sole()->design_tokens->preset)->toBeNull();
});

it('publishes no style for a turn that only touched content', function (): void {
    $this->createTenantBusiness($this->tenant, [], 0);

    PageEditorAgent::fake([
        new ToolCall('call-1', 'UpdateBlockContent', ['key' => 'k1', 'content' => ['heading' => 'Fresh']]),
        'Rewrote the headline.',
    ])->preventStrayPrompts();

    chatJob(jobBlocks())->handle();

    $turn = $this->runInTenant($this->tenant, fn (): ?array => resolve(CacheChatTurn::class)->read('tok'));

    expect($turn['design'])->toBeNull();
});

/*
 * A job that DIED (a deploy, a worker OOM) never returned a result, so anything
 * it had begun choosing is discarded along with the block edits.
 */
it('leaves no staged style behind when the job itself fails', function (): void {
    $this->createTenantBusiness($this->tenant, [], 0);

    Log::spy();

    chatJob(jobBlocks())->failed(new RuntimeException('worker killed'));

    $turn = $this->runInTenant($this->tenant, fn (): ?array => resolve(CacheChatTurn::class)->read('tok'));

    expect($turn['design'])->toBeNull()
        ->and($turn['blocks'])->toBe(jobBlocks());
});

it('forwards its attachment payload into the turn', function (): void {
    config()->set('ai.vision', ['gemini']);

    PageEditorAgent::fake(['Placed the photo.']);

    $shapes = [[
        'kind' => 'image',
        'name' => 'kitchen.jpg',
        'file' => ['type' => 'stored-image', 'path' => 'chat/a.jpg', 'disk' => 'public'],
        'media_id' => 42,
        'width' => 1600,
        'height' => 900,
    ]];

    new ChatEditPageJob(
        (string) $this->tenant->id,
        (int) $this->page->id,
        null,
        'Use this as the hero image',
        'tok',
        jobBlocks(),
        attachments: $shapes,
    )->handle();

    // The attachments reached the prompt (the announcement section) — proof the
    // payload crossed the queue boundary rather than dying in the constructor.
    PageEditorAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('media id 42'));

    $question = $this->runInTenant($this->tenant, fn () => PageChatMessage::query()->orderBy('id')->first());

    expect($question->attachments)->toBe($shapes);
});
