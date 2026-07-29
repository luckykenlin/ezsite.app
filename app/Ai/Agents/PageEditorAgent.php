<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Actions\Pages\AddPageBlock;
use App\Actions\Pages\RemovePageBlock;
use App\Actions\Pages\ReorderPageBlocks;
use App\Actions\Pages\UpdatePageBlock;
use App\Ai\BlockDataSanitizer;
use App\Ai\PageDraft;
use App\Ai\Tools\AddBlock;
use App\Ai\Tools\RemoveBlock;
use App\Ai\Tools\ReorderBlocks;
use App\Ai\Tools\UpdateBlockContent;
use App\Enums\ChatRole;
use App\Models\PageChatMessage;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

/**
 * The page editor's chat assistant: edits the page the operator has open, by
 * calling tools against a shared {@see PageDraft} rather than by returning
 * content. Its prose answer is only the "here's what I did" line shown in the
 * chat — every actual change lands through a tool.
 *
 * Contrast with {@see SiteDraftAgent}, which composes a whole site in ONE
 * structured response: that shape can't express "change the third heading",
 * and it can't see what is already on the page. Tools give this agent both,
 * and they reuse the same `App\Actions\Pages\*` mutations the human editor
 * uses, so there is one implementation of "add a block" and not two.
 *
 * Conversation memory comes from the SDK's {@see Conversational} contract, fed
 * by {@see PageChatMessage} — the app owns that table so a transcript is
 * tenant-scoped by RLS and keyed per page. See the migration for why the SDK's
 * own conversation tables are not used.
 *
 * `MaxSteps` bounds a turn: one request like "rewrite all the copy" legitimately
 * fans out into a tool call per block, but a model looping on a rejected
 * argument must terminate. Low temperature for the same reason as the draft
 * agent — tool arguments must be exact field names, not creative ones.
 */
#[Temperature(0.2)]
#[MaxSteps(12)]
#[Timeout(120)]
final readonly class PageEditorAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * How far back the assistant remembers. Generous enough to follow "now do
     * the same to the next section", bounded so a long session's prompt (which
     * also carries the page outline and the whole vocabulary) stays affordable.
     */
    private const int HISTORY_LIMIT = 20;

    private const string INSTRUCTIONS = 'You are the editing assistant inside a small-business '
        .'website builder. The operator has one page open and asks you to change it. '
        ."\n\n"
        .'Make every change by calling a tool — never answer with the new copy and never '
        .'output HTML, CSS, Markdown or code. Address blocks by the keys listed in the page '
        .'outline each tool returns, and only use field names from the block vocabulary you '
        .'were given: an invented field name is discarded, so re-read the outline and retry '
        .'with a real one instead of repeating yourself. '
        .'Layout and styling are not yours to set — no variant, theme or CSS choices; if the '
        .'operator asks for a different look, tell them to use the Design button. '
        .'You may add and remove sections, but only remove one when they clearly asked. '
        ."\n\n"
        .'Write copy that is concise and specific to this business, and never invent facts — '
        .'no addresses, prices, opening hours, awards or testimonials that are not already in '
        .'the page or the business profile. If a request needs a fact you do not have, make the '
        .'part you can and say what you need. '
        ."\n\n"
        .'When you are done, reply with ONE short sentence describing what you changed, in the '
        .'same language the operator wrote in. If you changed nothing, say so and why.';

    /**
     * @param  PageDraft  $draft  the shared working copy every tool mutates
     */
    public function __construct(
        private PageDraft $draft,
        private int $pageId,
    ) {
        //
    }

    public function instructions(): string
    {
        return self::INSTRUCTIONS;
    }

    /**
     * The page's transcript, oldest first. RLS scopes the query to the current
     * tenant; the page id scopes it to this thread.
     *
     * @return iterable<int, Message>
     */
    public function messages(): iterable
    {
        return PageChatMessage::query()
            ->where('page_id', $this->pageId)
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->map(fn (PageChatMessage $message): Message => new Message(
                $message->role === ChatRole::User ? 'user' : 'assistant',
                $message->content,
            ))
            ->values()
            ->all();
    }

    /**
     * @return list<AddBlock|RemoveBlock|ReorderBlocks|UpdateBlockContent>
     */
    public function tools(): iterable
    {
        $sanitizer = resolve(BlockDataSanitizer::class);

        return [
            new UpdateBlockContent($this->draft, $sanitizer, resolve(UpdatePageBlock::class)),
            new AddBlock($this->draft, $sanitizer, resolve(AddPageBlock::class)),
            new RemoveBlock($this->draft, resolve(RemovePageBlock::class)),
            new ReorderBlocks($this->draft, resolve(ReorderPageBlocks::class)),
        ];
    }
}
