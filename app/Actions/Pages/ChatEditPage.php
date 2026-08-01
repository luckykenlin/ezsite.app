<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Ai\Agents\PageEditorAgent;
use App\Ai\ChangedBlocks;
use App\Ai\ChatActivity;
use App\Ai\ChatAttachment;
use App\Ai\PageDraft;
use App\Ai\Prompts\PageEditPrompt;
use App\Ai\SiteChromeDraft;
use App\Ai\SiteStyleDraft;
use App\Enums\ChatMode;
use App\Enums\ChatRole;
use App\Enums\ChromeSlot;
use App\Models\Business;
use App\Models\Page;
use App\Models\PageChatMessage;
use App\Models\User;
use App\Site\BindResolver;
use App\Site\Blocks\BlockData;
use App\Site\Blocks\BlockVocabulary;
use App\Site\MediaResolver;
use App\Site\SiteChrome;
use App\Site\SiteContext;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use RuntimeException;
use Throwable;

/**
 * One turn of the page editor's chat: persist what the operator said, let the
 * agent edit a working copy of the blocks through its tools, persist the reply,
 * and hand the edited blocks back.
 *
 * Nothing is written to the page itself. The caller
 * ({@see \App\Filament\Tenant\Resources\PageResource\Pages\PageEditor::sendChatMessage()})
 * pushes the returned blocks through `applyBlocks()`, so an AI edit lands on the
 * undo stack and still needs an explicit Save — the operator reviews it on the
 * canvas first, exactly like a hand edit.
 *
 * A provider failure is contained here: the transcript keeps the question and an
 * apology, and the caller gets the blocks back UNCHANGED. A half-applied edit
 * would be worse than none, and a 500 in the editor would lose the operator's
 * uncommitted work.
 */
final readonly class ChatEditPage
{
    /**
     * Wall-clock budget for one turn, enforced between stream events.
     *
     * A turn has no natural upper bound — `MaxSteps` × the per-request
     * `Timeout` is minutes — but every layer above it does (php-fpm's
     * `max_execution_time`, nginx's `fastcgi_read_timeout`). Whichever of those
     * fires first kills the process, and a PHP fatal is NOT a Throwable: it
     * bypasses the catch in handle(), so the operator gets no apology. Worse,
     * `wire:stream` has already written to the response body by then, so the
     * 500 that follows cannot set its own headers ("Cannot modify header
     * information") and the editor renders a BLANK modal.
     *
     * So the deadline is ours, and it throws — which the catch below turns into
     * "the page is unchanged, try again". Keep this comfortably under the
     * deployment's fpm/nginx read timeouts; a single hung provider request is
     * bounded separately by PageEditorAgent's own `#[Timeout]` (Guzzle throws,
     * which is catchable too).
     */
    private const int TURN_BUDGET_SECONDS = 90;

    public function __construct(
        private BlockVocabulary $vocabulary,
        // The request-scoped resolver rather than a fresh `Business::query()`:
        // one turn asks for the business through the prompt and, on the render
        // that follows, through every bound block. Memoizing it is the whole
        // reason BindResolver is `scoped`.
        private BindResolver $bindResolver,
        private RecordPageChatMessage $transcript,
        private ChatActivity $activity,
        private ChangedBlocks $changed,
        private SiteContext $site,
        // Request-scoped like the bind resolver, and read for the same reason:
        // one turn needs the effective chrome for the draft, and the render that
        // follows needs it again.
        private SiteChrome $chrome,
        // Scoped like the two above: the transcript's attachment thumbs go
        // through the same memoized id→URL map the canvas render uses.
        private MediaResolver $media,
    ) {
        //
    }

    /**
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>  $blocks  the editor's current draft
     * @param  (Closure(string): void)|null  $onDelta  called with each chunk of the reply as it arrives
     * @param  (Closure(string): void)|null  $onActivity  called with one line per tool call, as it is announced
     * @param  string|null  $selectedBlockKey  the block selected on the canvas when the turn
     *                                         was dispatched — the referent for requests that
     *                                         name no section (see PageEditPrompt)
     * @param  (Closure(list<array{key: string, type: string, data: array<string, mixed>}>): void)|null  $onEdit  called with the draft's current blocks after
     *                                                                                                            each tool finishes — what lets the canvas
     *                                                                                                            repaint edit by edit instead of once at the end
     * @param  ChatMode  $mode  Ask runs the turn with no tools at all, so it can
     *                          answer questions with a structural guarantee of
     *                          changing nothing
     * @param  list<array<string, mixed>>  $attachments  the message's files as
     *                                                   {@see ChatAttachment} array shapes —
     *                                                   arrays rather than objects because
     *                                                   they ride a queued job payload
     * @return array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, reply: string, failed: bool, design: array<string, string|null>|null, chrome: array<string, array{type: string, data: array<string, mixed>}>|null}
     */
    public function handle(
        Page $page,
        array $blocks,
        string $message,
        ?User $user = null,
        ?Closure $onDelta = null,
        ?Closure $onActivity = null,
        ?string $selectedBlockKey = null,
        ?Closure $onEdit = null,
        ChatMode $mode = ChatMode::Edit,
        array $attachments = [],
    ): array {
        // Hand the time budget to TURN_BUDGET_SECONDS instead: PHP's own limit
        // can only fail as an uncatchable fatal, and the default 30s is shorter
        // than a single provider round trip, let alone a multi-tool turn.
        set_time_limit(0);

        $hydrated = array_values(array_filter(array_map(ChatAttachment::fromArray(...), $attachments)));

        // Idempotent: the editor already records the question when it dispatches
        // the turn, so the panel has a single bubble to render rather than a
        // persisted message plus the local echo of it. This is for callers that
        // reach the action directly.
        $this->transcript->question($page, $user, $message, $attachments === [] ? null : $attachments);

        $draft = new PageDraft($blocks);

        // Null when the tenant has no Business profile: tokens live on that row,
        // so there is nowhere for a style to be applied — and the agent withholds
        // the design tool for the same reason.
        $business = $this->bindResolver->business();
        $style = $business instanceof Business ? new SiteStyleDraft($business->design_tokens) : null;

        // Same condition as the style draft, different reason: SiteChrome renders
        // nothing at all without a Business, so there would be no header for an
        // edit to appear in.
        $chrome = $business instanceof Business ? new SiteChromeDraft($this->savedChrome()) : null;

        // Captured here as well as forwarded: the lines land on the reply row,
        // so the agent's conversation memory carries WHAT it changed — its
        // prose routinely under-describes its own edits.
        /** @var list<string> $activity */
        $activity = [];

        $captureActivity = function (string $line) use (&$activity, $onActivity): void {
            $activity[] = $line;

            if ($onActivity instanceof Closure) {
                $onActivity($line);
            }
        };

        try {
            $reply = $this->ask($page, $draft, $style, $chrome, $message, $onDelta, $captureActivity, $selectedBlockKey, $onEdit, $mode, $hydrated);
        } catch (Throwable $throwable) {
            Log::error('page_chat.failed', [
                'page_id' => $page->id,
                'exception' => $throwable->getMessage(),
            ]);

            $reply = __("Sorry — I couldn't reach the assistant just then. Your page is unchanged; please try again.");

            $this->record($page, $user, ChatRole::Assistant, $reply, failed: true);

            // `design` and `chrome` are explicitly null, not merely absent: a turn
            // that chose a palette or rewrote the navigation and then died must not
            // leave the operator previewing a half-finished change they never saw
            // described.
            return ['blocks' => $blocks, 'reply' => $reply, 'failed' => true, 'design' => null, 'chrome' => null];
        }

        $edited = $draft->blocks();
        $changed = $this->changed->count($blocks, $edited);

        // The apology path above deliberately records NO activity: a failed
        // turn's edits were discarded, and "edits you made" lines describing
        // them would feed the model a memory of changes that never landed.
        $this->record($page, $user, ChatRole::Assistant, $reply, $changed, activity: $activity);

        return [
            'blocks' => $edited,
            'reply' => $reply,
            'failed' => false,
            'design' => $style?->toArray(),
            'chrome' => $chrome?->toArray(),
        ];
    }

    /**
     * This page's recent transcript, oldest first — what the chat panel renders.
     * Capped at {@see PageEditorAgent::HISTORY_LIMIT}, the same window the
     * assistant remembers.
     *
     * Assistant turns also come back as HTML: the model answers in light
     * markdown (lists, tables, bold) and rendering it is the difference between
     * a readable summary of six sections and a wall of pipes and asterisks. The
     * operator's own turns are NOT rendered — their text is theirs, shown
     * verbatim and escaped.
     *
     * @return list<array{id: int, role: string, content: string, html: string|null, changed: bool, failed: bool, revertible: bool, attachments: list<array{kind: string, name: string, thumb: string|null}>}>
     */
    public function transcript(Page $page): array
    {
        $transcript = [];

        // Bounded by the agent's own memory window, and for the same reason it
        // has one: this runs on every editor mount AND every poll tick, and it
        // markdown-renders each assistant line. Unbounded, a long-lived page paid
        // to re-render its entire history every few seconds. Newest N, then
        // reversed, so the panel still reads oldest-first.
        $entries = PageChatMessage::query()
            ->where('page_id', $page->id)
            ->orderByDesc('id')
            ->limit(PageEditorAgent::HISTORY_LIMIT)
            ->get()
            ->reverse();

        $this->preloadAttachmentThumbs($entries);

        foreach ($entries as $entry) {
            $transcript[] = [
                // The id is what the revert button addresses; harmless on
                // every other row.
                'id' => (int) $entry->id,
                'role' => $entry->role->value,
                'content' => $entry->content,
                'html' => $entry->role === ChatRole::Assistant ? $this->markdown($entry->content) : null,
                'changed' => $entry->changedThePage(),
                'failed' => $entry->failed,
                'revertible' => $entry->blocks_before !== null,
                'attachments' => $this->attachmentChips($entry),
            ];
        }

        return $transcript;
    }

    /**
     * One batched media lookup for every image attachment in the window —
     * this renders on every poll tick, so per-chip queries are the N+1 the
     * resolver's preload exists to prevent.
     *
     * @param  iterable<int, PageChatMessage>  $entries
     */
    private function preloadAttachmentThumbs(iterable $entries): void
    {
        $ids = [];

        foreach ($entries as $entry) {
            foreach ($entry->attachments ?? [] as $stored) {
                $mediaId = ChatAttachment::fromArray($stored)?->mediaId;

                if ($mediaId !== null) {
                    $ids[] = $mediaId;
                }
            }
        }

        if ($ids !== []) {
            $this->media->preload($ids);
        }
    }

    /**
     * The attachment chips one transcript bubble renders: a thumbnail URL for
     * images (through the same resolver the canvas uses), a bare filename chip
     * for documents.
     *
     * @return list<array{kind: string, name: string, thumb: string|null}>
     */
    private function attachmentChips(PageChatMessage $entry): array
    {
        $chips = [];

        foreach ($entry->attachments ?? [] as $stored) {
            $attachment = ChatAttachment::fromArray($stored);

            if ($attachment === null) {
                continue;
            }

            $chips[] = [
                'kind' => $attachment->kind,
                'name' => $attachment->name,
                'thumb' => $attachment->mediaId !== null ? $this->media->url($attachment->mediaId) : null,
            ];
        }

        return $chips;
    }

    /**
     * The EFFECTIVE header/footer entry per slot — stored settings, or the default
     * chrome a tenant with a Business gets.
     *
     * Read through {@see SiteChrome} rather than from `site_settings` directly, so
     * the assistant sees exactly what a visitor sees. The editor's own chrome
     * draft deliberately hydrates only STORED slots (a null slot must stay null so
     * merely opening the editor never materialises a default); here the opposite is
     * right, because the model is being asked to change what is on screen.
     *
     * @return array<string, array{type: string, data: array<string, mixed>}>
     */
    private function savedChrome(): array
    {
        $entries = [];

        foreach ([ChromeSlot::Header, ChromeSlot::Footer] as $slot) {
            $blocks = $slot === ChromeSlot::Header
                ? $this->chrome->headerBlocks()
                : $this->chrome->footerBlocks();

            $entry = is_array($blocks[0] ?? null) ? $blocks[0] : null;

            if ($entry === null) {
                continue;
            }

            $data = $entry['data'] ?? null;

            $entries[$slot->value] = [
                'type' => is_string($entry['type'] ?? null) ? $entry['type'] : $slot->value,
                'data' => is_array($data) ? BlockData::stringKeyed($data) : [],
            ];
        }

        return $entries;
    }

    /**
     * Run the turn. The agent mutates `$draft` through its tools; its prose
     * return value is only the summary line shown in the chat, so an empty one
     * still needs to read as an answer.
     *
     * Streamed rather than awaited: a turn that rewrites several blocks takes
     * long enough that a spinner reads as a hang, and the model narrates as it
     * goes. Tools execute DURING the iteration, so the draft is only complete
     * once the loop ends — `$response->text` is likewise only populated then.
     *
     * @param  (Closure(string): void)|null  $onDelta
     * @param  (Closure(string): void)|null  $onActivity
     * @param  list<ChatAttachment>  $attachments
     */
    private function ask(Page $page, PageDraft $draft, ?SiteStyleDraft $style, ?SiteChromeDraft $chrome, string $message, ?Closure $onDelta, ?Closure $onActivity = null, ?string $selectedBlockKey = null, ?Closure $onEdit = null, ChatMode $mode = ChatMode::Edit, array $attachments = []): string
    {
        $prompt = new PageEditPrompt(
            $page,
            $draft,
            $this->vocabulary->all(),
            $this->bindResolver->business(),
            $message,
            $this->site,
            $style,
            $chrome,
            $selectedBlockKey,
            $attachments,
        );

        // Started before the first request, not after: the slowest turns are the
        // ones where step one already takes too long.
        $deadline = now()->addSeconds(self::TURN_BUDGET_SECONDS);

        // THE ROUTING INVARIANT. A turn runs on the vision chain (ai.vision)
        // whenever any message in the agent's conversation window carries an
        // attachment — this one, or an earlier one still inside HISTORY_LIMIT.
        // Two things depend on it: the default chain's provider throws on
        // document attachments (so it must never see a window containing one),
        // and a follow-up like "now add the desserts too" must still be able
        // to read the menu PDF sent three messages ago. Once the attachment
        // rows age out of the window, the thread falls back to the default
        // chain by itself. PageEditorAgent::messages() rehydrates on the
        // strength of this — change one side only with the other in hand.
        $vision = $attachments !== [] || $this->threadHasRecentAttachments($page);

        // The failover chain (ai.failover): with a fallback provider configured,
        // a turn whose PRIMARY refuses to even start (down, rate-limited, bad
        // key) silently retries there instead of costing the operator the whole
        // turn. A stream that has already emitted cannot fail over — the SDK
        // rethrows then, and the catch in handle() apologises as before.
        $response = new PageEditorAgent($draft, $page, $style, $chrome, $mode, $vision)
            ->stream((string) $prompt, provider: config()->array($vision ? 'ai.vision' : 'ai.failover'));

        foreach ($response as $event) {
            // Between events is the only place a turn can be stopped: tools run
            // inside the iteration. Whatever the draft holds is discarded by the
            // caller's catch, so a half-finished turn never reaches the page.
            throw_if(
                now()->greaterThan($deadline),
                RuntimeException::class,
                sprintf('The chat turn exceeded its %d second budget.', self::TURN_BUDGET_SECONDS),
            );

            if ($event instanceof TextDelta && $onDelta instanceof Closure) {
                $onDelta($event->delta);
            }

            // The tool calls ARE the turn — the prose is written last, once every
            // edit has been made — so without these the operator watches a
            // blinking cursor for the whole of a multi-block rewrite.
            if ($event instanceof ToolCall && $onActivity instanceof Closure) {
                $line = $this->activity->forToolCall(
                    $draft,
                    $event->toolCall->name,
                    $event->toolCall->arguments,
                );

                if ($line !== null) {
                    $onActivity($line);
                }
            }

            // On the RESULT, not the call: ToolCall is announced before the tool
            // runs, so the draft only holds the edit once its result comes back —
            // painting on the call would show the state from one edit ago.
            if ($event instanceof ToolResult && $onEdit instanceof Closure) {
                $onEdit($draft->blocks());
            }
        }

        // `text` is only populated once the iteration above completes, and stays
        // null for a turn that streamed no prose at all — a tool-only reply.
        $reply = mb_trim($response->text ?? '');

        return $reply === '' ? __('Done.') : $reply;
    }

    /**
     * Whether any message inside the agent's conversation window carries an
     * attachment — the other half of the routing invariant documented in
     * {@see ask()}. An inner LIMIT rather than a plain WHERE, because a
     * six-month-old attachment the agent can no longer see must not pin the
     * thread to the vision chain forever.
     */
    private function threadHasRecentAttachments(Page $page): bool
    {
        return PageChatMessage::query()
            ->whereIn('id', PageChatMessage::query()
                ->where('page_id', $page->id)
                ->orderByDesc('id')
                ->limit(PageEditorAgent::HISTORY_LIMIT)
                ->select('id'))
            ->whereNotNull('attachments')
            ->exists();
    }

    /**
     * The assistant's answer as HTML.
     *
     * The model's output is untrusted text that ends up in the operator's
     * browser, so raw HTML in it is STRIPPED rather than passed through, and
     * javascript:/data: links are refused — a prompt injection reaching the
     * transcript must not become markup. Everything the panel renders comes from
     * commonmark's own escaped output.
     */
    private function markdown(string $content): string
    {
        return Str::markdown($content, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * @param  list<string>|null  $activity
     */
    private function record(Page $page, ?User $user, ChatRole $role, string $content, ?int $changed = null, bool $failed = false, ?array $activity = null): void
    {
        $this->transcript->handle($page, $user, $role, $content, $changed, $failed, $activity);
    }
}
