<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Ai\Agents\PageEditorAgent;
use App\Ai\PageDraft;
use App\Ai\Prompts\PageEditPrompt;
use App\Enums\ChatRole;
use App\Filament\Fabricator\BlockRegistry;
use App\Models\Business;
use App\Models\Page;
use App\Models\PageChatMessage;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Streaming\Events\TextDelta;
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

    /**
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>  $blocks  the editor's current draft
     * @param  (Closure(string): void)|null  $onDelta  called with each chunk of the reply as it arrives
     * @return array{blocks: list<array{key: string, type: string, data: array<string, mixed>}>, reply: string, failed: bool}
     */
    public function handle(Page $page, array $blocks, string $message, ?User $user = null, ?Closure $onDelta = null): array
    {
        // Hand the time budget to TURN_BUDGET_SECONDS instead: PHP's own limit
        // can only fail as an uncatchable fatal, and the default 30s is shorter
        // than a single provider round trip, let alone a multi-tool turn.
        set_time_limit(0);

        $this->record($page, $user, ChatRole::User, $message);

        $draft = new PageDraft($blocks);

        try {
            $reply = $this->ask($page, $draft, $message, $onDelta);
        } catch (Throwable $throwable) {
            Log::error('page_chat.failed', [
                'page_id' => $page->id,
                'exception' => $throwable->getMessage(),
            ]);

            $reply = __("Sorry — I couldn't reach the assistant just then. Your page is unchanged; please try again.");

            $this->record($page, $user, ChatRole::Assistant, $reply);

            return ['blocks' => $blocks, 'reply' => $reply, 'failed' => true];
        }

        $edited = $draft->blocks();
        $changed = $this->changedCount($blocks, $edited);

        $this->record($page, $user, ChatRole::Assistant, $reply, $changed);

        return ['blocks' => $edited, 'reply' => $reply, 'failed' => false];
    }

    /**
     * This page's transcript, oldest first — what the chat panel renders.
     *
     * Assistant turns also come back as HTML: the model answers in light
     * markdown (lists, tables, bold) and rendering it is the difference between
     * a readable summary of six sections and a wall of pipes and asterisks. The
     * operator's own turns are NOT rendered — their text is theirs, shown
     * verbatim and escaped.
     *
     * @return list<array{role: string, content: string, html: string|null, changed: bool}>
     */
    public function transcript(Page $page): array
    {
        $transcript = [];

        foreach (PageChatMessage::query()->where('page_id', $page->id)->orderBy('id')->get() as $entry) {
            $transcript[] = [
                'role' => $entry->role->value,
                'content' => $entry->content,
                'html' => $entry->role === ChatRole::Assistant ? $this->markdown($entry->content) : null,
                'changed' => $entry->changedThePage(),
            ];
        }

        return $transcript;
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
     */
    private function ask(Page $page, PageDraft $draft, string $message, ?Closure $onDelta): string
    {
        $prompt = new PageEditPrompt(
            $page,
            $draft,
            BlockRegistry::vocabulary(),
            Business::query()->first(),
            $message,
        );

        // Started before the first request, not after: the slowest turns are the
        // ones where step one already takes too long.
        $deadline = now()->addSeconds(self::TURN_BUDGET_SECONDS);

        $response = new PageEditorAgent($draft, (int) $page->id)->stream((string) $prompt);

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
        }

        // `text` is only populated once the iteration above completes, and stays
        // null for a turn that streamed no prose at all — a tool-only reply.
        $reply = mb_trim($response->text ?? '');

        return $reply === '' ? __('Done.') : $reply;
    }

    /**
     * How many blocks the turn actually touched — added, removed, or edited.
     * Drives the "edited the page" marker, and tells the caller whether to
     * disturb the editor's state at all.
     *
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>  $before
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>  $after
     */
    private function changedCount(array $before, array $after): int
    {
        $keyed = array_column($before, null, 'key');
        $afterKeys = array_column($after, 'key');

        // A block whose key vanished was removed; a new key was added; a shared
        // key with different data was edited. A pure reorder changes no block,
        // so it is counted as one change rather than zero.
        $changed = count(array_diff(array_keys($keyed), $afterKeys));

        foreach ($after as $block) {
            $previous = $keyed[$block['key']] ?? null;

            if ($previous === null || $previous['data'] !== $block['data']) {
                $changed++;
            }
        }

        if ($changed === 0 && array_column($before, 'key') !== $afterKeys) {
            return 1;
        }

        return $changed;
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

    private function record(Page $page, ?User $user, ChatRole $role, string $content, ?int $changed = null): void
    {
        PageChatMessage::query()->create([
            'tenant_id' => $page->tenant_id,
            'page_id' => $page->id,
            'user_id' => $user?->id,
            'role' => $role,
            'content' => $content,
            'changed_blocks' => $changed,
        ]);
    }
}
