<?php

declare(strict_types=1);

use App\Actions\Pages\ChatEditPage;
use App\Actions\SaveSiteChrome;
use App\Ai\Agents\PageEditorAgent;
use App\Design\DesignTokens;
use App\Design\StylePreset;
use App\Enums\ChatRole;
use App\Enums\PageStatus;
use App\Models\Business;
use App\Models\Page;
use App\Models\PageChatMessage;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Prompts\AgentPrompt;
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
function chatTurn(
    array $blocks,
    string $message = 'Shorten the headline',
    ?User $user = null,
    ?Closure $onDelta = null,
    ?Closure $onActivity = null,
): array {
    return test()->runInTenant(
        test()->tenant,
        fn (): array => resolve(ChatEditPage::class)->handle(test()->page, $blocks, $message, $user, $onDelta, $onActivity),
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

it('records the tool-call summary on the reply, and none for a tool-less answer', function (): void {
    // What feeds the agent's memory of its own edits — the prose routinely
    // under-describes them ("Done." after a three-block rewrite).
    PageEditorAgent::fake([
        toolCall('UpdateBlockContent', ['key' => 'k1', 'content' => ['heading' => 'Fresh bread daily']]),
        'Done.',
    ]);

    chatTurn(chatBlocks());

    expect(PageChatMessage::query()->where('role', ChatRole::Assistant)->sole()->activity)
        ->toBe(['Rewriting the Hero block…']);

    PageEditorAgent::fake(['The hero is the banner at the top.']);

    chatTurn(chatBlocks(), 'What is the hero?');

    expect(PageChatMessage::query()->where('role', ChatRole::Assistant)->orderByDesc('id')->first()?->activity)
        ->toBeNull();
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

    $apology = PageChatMessage::query()->where('role', ChatRole::Assistant)->sole();

    expect($result['blocks'])->toBe(chatBlocks())
        ->and($result['failed'])->toBeTrue()
        ->and($result['reply'])->toContain("couldn't reach the assistant")
        ->and($apology->changed_blocks)->toBeNull()
        // Persisted, not just returned: the retry button renders from the
        // transcript, which is the only half that survives a reload.
        ->and($apology->failed)->toBeTrue();

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
    ])->and(array_column($transcript, 'role'))->toBe(['user', 'assistant', 'user', 'assistant'])
        // Ordinary turns carry failed: false — the panel keys the retry
        // affordance off this, so it must be present on every entry.
        ->and(array_column($transcript, 'failed'))->toBe([false, false, false, false]);
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

/*
 * The other half of "alive rather than hung", and the half that matters on a long
 * turn: this agent edits through tools and writes its prose LAST, so a rewrite of
 * several blocks streams no text at all until every edit is already made. Without
 * these lines the panel shows nothing for most of a minute.
 */
it('announces each tool call as it is made', function (): void {
    PageEditorAgent::fake([
        toolCall('UpdateBlockContent', ['key' => 'k1', 'content' => ['heading' => 'Fresh bread daily']]),
        toolCall('AddBlock', ['type' => 'gallery']),
        'Rewrote the hero and added a gallery.',
    ]);

    $activity = [];

    chatTurn(chatBlocks(), 'Rewrite the hero and add a gallery', null, null, function (string $line) use (&$activity): void {
        $activity[] = $line;
    });

    // In the order the model worked, naming the block each step is about.
    expect($activity)->toBe([
        'Rewriting the Hero block…',
        'Adding a Gallery block…',
    ]);
});

it('runs fine without an activity listener', function (): void {
    PageEditorAgent::fake([
        toolCall('UpdateBlockContent', ['key' => 'k1', 'content' => ['heading' => 'Fresh']]),
        'Done.',
    ]);

    expect(chatTurn(chatBlocks())['blocks'][0]['data']['heading'])->toBe('Fresh');
});

it('shows only as much transcript as the assistant remembers, oldest first', function (): void {
    // The panel re-reads this on every mount AND every poll tick, markdown
    // rendering each assistant line, so it is bounded — by the agent's own
    // memory window, so the operator is never shown history the model cannot see.
    $limit = PageEditorAgent::HISTORY_LIMIT;

    $this->runInTenant($this->tenant, function () use ($limit): void {
        foreach (range(1, $limit + 5) as $i) {
            PageChatMessage::query()->create([
                'tenant_id' => $this->tenant->id,
                'page_id' => $this->page->id,
                'role' => $i % 2 === 0 ? ChatRole::Assistant : ChatRole::User,
                'content' => 'message '.$i,
            ]);
        }

        $transcript = resolve(ChatEditPage::class)->transcript($this->page);

        expect($transcript)->toHaveCount($limit)
            // The NEWEST N, still rendered oldest-first.
            ->and($transcript[0]['content'])->toBe('message 6')
            ->and($transcript[$limit - 1]['content'])->toBe('message '.($limit + 5))
            // The list is re-indexed, not a preserved-key slice.
            ->and(array_keys($transcript))->toBe(range(0, $limit - 1));
    });
});

/*
 * The safety property of the whole design feature: a turn that restyles the site
 * must NOT write it. Tokens are site-scoped and ThemeVariables::style() reads the
 * saved ones on every public render, so a write here would repaint a live website
 * while its owner was still reading the reply. The turn hands back a staged style
 * and the operator applies it themselves.
 */
it('stages a site style without writing it to the business', function (): void {
    $this->createTenantBusiness($this->tenant, ['design_tokens' => DesignTokens::default()], 0);

    PageEditorAgent::fake([
        toolCall('SetSiteStyle', ['preset' => 'warm-craft']),
        'Warmed the whole site up.',
    ])->preventStrayPrompts();

    $result = chatTurn(chatBlocks(), 'make it warmer');

    expect($result['design'])->toBe(StylePreset::WarmCraft->tokens()->toArray())
        // Untouched on disk — the "Apply to site" click is what writes it.
        ->and(Business::query()->sole()->design_tokens->preset)->toBeNull();
});

it('reports no staged style when the turn only edited content', function (): void {
    $this->createTenantBusiness($this->tenant, [], 0);

    PageEditorAgent::fake([
        toolCall('UpdateBlockContent', ['key' => 'k1', 'content' => ['heading' => 'Fresh']]),
        'Rewrote the headline.',
    ])->preventStrayPrompts();

    expect(chatTurn(chatBlocks())['design'])->toBeNull();
});

/*
 * Without a Business row there is nowhere for tokens to live, so the tool is not
 * offered at all — mirroring DesignAction::visible(hasBusinessProfile()). A call
 * the model cannot diagnose is worse than a verb it never sees.
 */
it('withholds the site-style tool when the tenant has no business profile', function (): void {
    PageEditorAgent::fake(['I can only help with this page.'])->preventStrayPrompts();

    expect(chatTurn(chatBlocks())['design'])->toBeNull();
});

/*
 * A turn that chose a palette and then died must not leave the operator
 * previewing half a decision they never saw described — the same reasoning that
 * returns the blocks unchanged.
 */
it('discards a half-chosen style when the turn fails', function (): void {
    $this->createTenantBusiness($this->tenant, ['design_tokens' => DesignTokens::default()], 0);

    Log::spy();

    PageEditorAgent::fake(function (): never {
        throw new RuntimeException('provider exploded mid-restyle');
    })->preventStrayPrompts();

    $result = chatTurn(chatBlocks(), 'make it premium');

    expect($result['failed'])->toBeTrue()
        ->and($result['design'])->toBeNull()
        ->and($result['blocks'])->toBe(chatBlocks())
        ->and(Business::query()->sole()->design_tokens->preset)->toBeNull();
});

/*
 * A restyle that aligns layouts changes BLOCKS as well as tokens, in one call —
 * which is what keeps a broad restyle inside MaxSteps(12) and the turn budget.
 */
it('re-lays the page in the same call that stages the style', function (): void {
    $this->createTenantBusiness($this->tenant, [], 0);

    PageEditorAgent::fake([
        toolCall('SetSiteStyle', ['preset' => 'bold-editorial', 'align_layouts' => true]),
        'Went bold, and re-laid the sections to match.',
    ])->preventStrayPrompts();

    $result = chatTurn(chatBlocks(), 'make it dramatic');

    expect($result['blocks'][0]['data']['variant'])
        ->toBe(StylePreset::BoldEditorial->blockVariantDefaults()['hero'])
        ->and($result['design']['preset'])->toBe('bold-editorial');
});

it('switches one section layout without touching the site style', function (): void {
    $this->createTenantBusiness($this->tenant, [], 0);

    PageEditorAgent::fake([
        toolCall('SetBlockVariant', ['key' => 'k1', 'variant' => 'full-bleed-overlay']),
        'Made the hero full-bleed.',
    ])->preventStrayPrompts();

    $result = chatTurn(chatBlocks(), 'put the photo behind the hero text');

    expect($result['blocks'][0]['data']['variant'])->toBe('full-bleed-overlay')
        ->and($result['blocks'][0]['data']['heading'])->toBe('Old headline')
        ->and($result['design'])->toBeNull();
});

/*
 * Chrome is site-scoped like design tokens, so it gets the same treatment: the
 * turn STAGES it and the operator's Save is still the only write. A tool calling
 * SaveSiteChrome from the worker would rewrite the navigation of a live website
 * while its owner was reading the reply.
 */
it('stages a site chrome edit without writing it to site settings', function (): void {
    $this->createTenantBusiness($this->tenant, [], 0);

    PageEditorAgent::fake([
        toolCall('UpdateChrome', ['slot' => 'header', 'content' => [
            'nav_links' => [['label' => 'Services', 'url' => '/services']],
        ]]),
        'Added Services to the menu — it shows on every page.',
    ])->preventStrayPrompts();

    $result = chatTurn(chatBlocks(), 'add Services to the menu');

    expect($result['chrome']['header']['data']['nav_links'])
        ->toBe([['label' => 'Services', 'url' => '/services']])
        // Nothing on disk: site_settings is written by Save, not by the turn.
        ->and($this->runInTenant($this->tenant, fn (): int => SiteSetting::query()->count()))->toBe(0);
});

it('hands back only the chrome slot the turn touched', function (): void {
    $this->createTenantBusiness($this->tenant, [], 0);

    PageEditorAgent::fake([
        toolCall('UpdateChrome', ['slot' => 'footer', 'content' => ['note' => 'Closed Sundays.']]),
        'Updated the footer.',
    ])->preventStrayPrompts();

    // The editor merges per slot, so an untouched header must not travel back and
    // overwrite one the operator was editing by hand while the turn ran.
    expect(array_keys(chatTurn(chatBlocks())['chrome']))->toBe(['footer']);
});

it('reports no staged chrome when the turn left it alone', function (): void {
    $this->createTenantBusiness($this->tenant, [], 0);

    PageEditorAgent::fake([
        toolCall('UpdateBlockContent', ['key' => 'k1', 'content' => ['heading' => 'Fresh']]),
        'Rewrote the headline.',
    ])->preventStrayPrompts();

    expect(chatTurn(chatBlocks())['chrome'])->toBeNull();
});

/*
 * Withheld on the same condition as the design tools, for a reason of its own:
 * SiteChrome renders nothing at all without a Business, so a staged header would
 * be invisible everywhere.
 */
it('withholds the chrome verb from a tenant with no business profile', function (): void {
    PageEditorAgent::fake(['I can only help with this website.'])->preventStrayPrompts();

    expect(chatTurn(chatBlocks())['chrome'])->toBeNull();

    PageEditorAgent::assertPrompted(fn (AgentPrompt $prompt): bool => ! $prompt->contains('## Site header and footer'));
});

it('gives the model the links already in the navigation, not just the field names', function (): void {
    $this->createTenantBusiness($this->tenant, [], 0);

    $this->runInTenant($this->tenant, fn (): SiteSetting => resolve(SaveSiteChrome::class)->handle(
        [['type' => 'header', 'data' => ['variant' => 'simple', 'nav_links' => [['label' => 'About', 'url' => '/about']]]]],
        null,
    ));

    PageEditorAgent::fake(['Done.'])->preventStrayPrompts();

    chatTurn(chatBlocks());

    // nav_links is replaced as a whole list, so a model that cannot see the
    // existing links deletes them when it adds one.
    PageEditorAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('## Site header and footer')
        && $prompt->contains('About')
        && $prompt->contains('replaced as a whole list'));
});

/*
 * A failed turn must not leave a half-finished navigation staged, for the same
 * reason it must not leave a half-chosen palette.
 */
it('discards a staged chrome edit when the turn fails', function (): void {
    $this->createTenantBusiness($this->tenant, [], 0);

    PageEditorAgent::fake([new RuntimeException('provider down')])->preventStrayPrompts();

    $result = chatTurn(chatBlocks());

    expect($result['failed'])->toBeTrue()
        ->and($result['chrome'])->toBeNull()
        ->and($result['design'])->toBeNull();
});

/*
 * A malformed stored slot must not stop the turn. `site_settings.header` is JSON
 * a seeder, an import or an older release could have written, and SiteChrome
 * hands back whatever is in it — so the slot is skipped here and
 * SiteChromeDraft::current() falls back to an empty entry, exactly as the render
 * path degrades rather than fatalling.
 */
it('survives a chrome slot whose stored entry is not a block', function (): void {
    $this->createTenantBusiness($this->tenant, [], 0);

    $this->runInTenant($this->tenant, fn (): SiteSetting => resolve(SaveSiteChrome::class)->handle(
        [null],
        [['type' => 'footer', 'data' => ['note' => 'Fine.']]],
    ));

    PageEditorAgent::fake([
        toolCall('UpdateChrome', ['slot' => 'header', 'content' => ['cta_label' => 'Call us']]),
        'Added a button to the menu.',
    ])->preventStrayPrompts();

    $result = chatTurn(chatBlocks());

    // The header had nothing usable to start from, so the edit lands on an empty
    // entry rather than failing the turn.
    expect($result['failed'])->toBeFalse()
        ->and($result['chrome']['header']['data'])->toBe(['cta_label' => 'Call us']);
});

/*
 * The page-level verbs end to end. They are the only ones that WRITE, so what
 * matters is what they write: a hidden draft, leaving the open page's blocks and
 * the live site alone.
 */
it('adds a page without touching the open one', function (): void {
    PageEditorAgent::fake([
        toolCall('CreatePage', ['title' => 'Services', 'sections' => ['hero', 'cta']]),
        'Added a Services page as a draft — you will find it on the site canvas.',
    ])->preventStrayPrompts();

    $blocks = chatBlocks();
    $result = chatTurn($blocks, 'add a services page');

    $created = $this->runInTenant($this->tenant, fn (): Page => Page::query()->where('slug', 'services')->sole());

    expect($created->status)->toBe(PageStatus::Draft)
        ->and(array_column($created->blocks, 'type'))->toBe(['hero', 'cta'])
        // The open page is untouched: creating a page is not an edit to this one,
        // so nothing here should ask the operator to review and save anything.
        ->and($result['blocks'])->toBe($blocks)
        ->and($result['chrome'])->toBeNull()
        ->and($result['design'])->toBeNull();
});

it('copies the open page as a draft, from the working blocks', function (): void {
    PageEditorAgent::fake([
        toolCall('DuplicatePage', []),
        'Copied this page.',
    ])->preventStrayPrompts();

    // The draft carries an edit the stored page does not have.
    $blocks = [
        ['key' => 'k1', 'type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Only in the draft']],
    ];

    chatTurn($blocks, 'make another page like this');

    $copy = $this->runInTenant($this->tenant, fn (): Page => Page::query()
        ->where('slug', 'like-my-page-copy')
        ->orWhere('slug', 'like', '%-copy')
        ->firstOrFail());

    expect($copy->status)->toBe(PageStatus::Draft)
        ->and($copy->blocks[0]['data']['heading'])->toBe('Only in the draft')
        ->and($copy->blocks[0])->not->toHaveKey('key');
});

/*
 * THE ROUTING INVARIANT (see ChatEditPage::ask() and PageEditorAgent::messages()):
 * a turn runs on the vision chain exactly when the agent's conversation window
 * contains attachments. The default provider's gateway throws on document
 * attachments, so "the window has one but the turn routed to the default chain"
 * is the regression these three pin against.
 */
it('routes a turn with attachments to the vision chain and announces them', function (): void {
    config()->set('ai.failover', ['deepseek']);
    config()->set('ai.vision', ['gemini']);

    PageEditorAgent::fake(['Placed the photo.']);

    $this->runInTenant($this->tenant, fn (): array => resolve(ChatEditPage::class)->handle(
        $this->page,
        chatBlocks(),
        'Use this as the hero image',
        attachments: [[
            'kind' => 'image',
            'name' => 'kitchen.jpg',
            'file' => ['type' => 'stored-image', 'path' => 'chat/a.jpg', 'disk' => 'public'],
            'media_id' => 42,
            'width' => 1600,
            'height' => 900,
        ]],
    ));

    PageEditorAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->provider->name() === 'gemini'
        && $prompt->contains('## Attachments on this message')
        && $prompt->contains('media id 42'));

    // The question row keeps its attachments, so the NEXT turn still routes here.
    $stored = $this->runInTenant($this->tenant, fn () => PageChatMessage::query()->orderBy('id')->first());

    expect($stored->hasAttachments())->toBeTrue();
});

it('keeps a text-only follow-up on the vision chain while attachments are in the window', function (): void {
    config()->set('ai.failover', ['deepseek']);
    config()->set('ai.vision', ['gemini']);

    $this->runInTenant($this->tenant, fn () => PageChatMessage::factory()->withAttachments()->create([
        'tenant_id' => $this->tenant->id,
        'page_id' => $this->page->id,
        'content' => 'Here is our menu',
    ]));

    PageEditorAgent::fake(['Added the desserts.']);

    chatTurn(chatBlocks(), 'now add the desserts from that menu too');

    PageEditorAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->provider->name() === 'gemini'
        // A text-only turn announces nothing new — the file rides as history.
        && ! $prompt->contains('## Attachments on this message'));
});

it('keeps a clean thread on the default chain', function (): void {
    config()->set('ai.failover', ['deepseek']);
    config()->set('ai.vision', ['gemini']);

    PageEditorAgent::fake(['Shortened it.']);

    chatTurn(chatBlocks());

    PageEditorAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->provider->name() === 'deepseek');
});

it('shows the transcript attachments as chips, with thumbs only for library images', function (): void {
    $media = $this->runInTenant($this->tenant, fn (): App\Models\Media => App\Models\Media::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]));

    $this->runInTenant($this->tenant, fn () => PageChatMessage::factory()->create([
        'tenant_id' => $this->tenant->id,
        'page_id' => $this->page->id,
        'content' => 'Use these',
        'attachments' => [
            ['kind' => 'image', 'name' => 'kitchen.jpg', 'file' => [], 'media_id' => (int) $media->id, 'width' => null, 'height' => null],
            ['kind' => 'document', 'name' => 'menu.pdf', 'file' => [], 'media_id' => null, 'width' => null, 'height' => null],
            'not an attachment shape',
        ],
    ]));

    $transcript = $this->runInTenant(
        $this->tenant,
        fn (): array => resolve(ChatEditPage::class)->transcript($this->page),
    );

    $chips = $transcript[0]['attachments'];

    expect($chips)->toHaveCount(2)
        ->and($chips[0]['name'])->toBe('kitchen.jpg')
        ->and($chips[0]['thumb'])->toBeString()
        ->and($chips[1])->toBe(['kind' => 'document', 'name' => 'menu.pdf', 'thumb' => null])
        // Rows without attachments still carry the key, as an empty list.
        ->and(array_column($transcript, 'attachments'))->toHaveCount(1);
});
