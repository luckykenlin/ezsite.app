<?php

declare(strict_types=1);

namespace App\Ai\Prompts;

use App\Ai\ChatAttachment;
use App\Ai\PageDraft;
use App\Ai\SiteChromeDraft;
use App\Ai\SiteStyleDraft;
use App\Design\StylePreset;
use App\Enums\BindType;
use App\Enums\ChromeSlot;
use App\Models\Business;
use App\Models\Page;
use App\Site\Blocks\BlockType;
use App\Site\Blocks\LayoutAxis;
use App\Site\SiteContext;
use Stringable;

/**
 * The context wrapped around one chat turn for {@see \App\Ai\Agents\PageEditorAgent}:
 * which page is open, what is currently on it, which fields each block type
 * accepts, and the facts the assistant is allowed to state.
 *
 * Rebuilt every turn rather than remembered, because the operator edits the
 * page by hand between messages — a remembered outline goes stale and the model
 * starts addressing blocks that moved or no longer exist. The conversation
 * history carries the intent; this carries the state.
 *
 * Only the vocabulary entries for types actually on the page are listed in
 * full, plus the bare type names that could be added — the full contract for
 * every block type is a lot of tokens to spend on sections the operator did not
 * ask about, and {@see \App\Ai\Tools\AddBlock}'s schema enumerates the addable
 * types anyway.
 */
final readonly class PageEditPrompt implements Stringable
{
    /**
     * @param  array<string, BlockType>  $vocabulary
     * @param  list<ChatAttachment>  $attachments  the files riding on THIS
     *                                             message — the announcement of
     *                                             their media ids / filenames.
     *                                             The bytes travel as history
     *                                             attachments, not here.
     */
    public function __construct(
        private Page $page,
        private PageDraft $draft,
        private array $vocabulary,
        private ?Business $business,
        private string $message,
        private ?SiteContext $site = null,
        private ?SiteStyleDraft $style = null,
        private ?SiteChromeDraft $chrome = null,
        private ?string $selectedBlockKey = null,
        private array $attachments = [],
    ) {
        //
    }

    public function __toString(): string
    {
        return implode("\n\n", array_filter([
            $this->pageSection(),
            $this->draft->outline(),
            $this->selectionSection(),
            $this->vocabularySection(),
            $this->chromeSection(),
            $this->siteSection(),
            $this->styleSection(),
            $this->businessSection(),
            $this->attachmentsSection(),
            $this->requestSection(),
        ]));
    }

    /**
     * What the operator attached to this message, one line per file — for an
     * image, the media id the set-block-image tool takes; for a document, the
     * name to read it by. Directly above the request, because the request is
     * usually ABOUT these files ("use this as the hero image").
     */
    private function attachmentsSection(): ?string
    {
        if ($this->attachments === []) {
            return null;
        }

        $lines = array_map(
            static fn (ChatAttachment $attachment): string => '- '.$attachment->promptLine(),
            $this->attachments,
        );

        return "## Attachments on this message\n".implode("\n", $lines);
    }

    /**
     * The block the operator has selected on the canvas, as the referent for
     * requests that name no section — Lovable/Base44's "selection rides with
     * the prompt", which is what turns "make it shorter" from a guess across
     * ten blocks into an instruction about one.
     *
     * Resolved against the DRAFT rather than trusted: the selection is
     * captured when the turn is dispatched, and a key that no longer exists
     * (the operator deleted the block, an older tab) must vanish rather than
     * point the model at nothing. Directly under the outline, so "key %s"
     * reads against the list it indexes.
     */
    private function selectionSection(): ?string
    {
        if ($this->selectedBlockKey === null) {
            return null;
        }

        $block = $this->draft->find($this->selectedBlockKey);

        if ($block === null) {
            return null;
        }

        return "## The selected section\n"
            .sprintf(
                'The operator has the %s section selected on the canvas — key %s in the outline above. ',
                $block['type'],
                $block['key'],
            )
            .'A request that names no section ("make it shorter", "change this", an instruction '
            .'with no subject) refers to THIS section. A request that clearly names or describes '
            .'a different section overrides the selection.';
    }

    private function pageSection(): string
    {
        return sprintf(
            "## The open page\nTitle: %s\nAddress: %s\nStatus: %s",
            $this->page->title,
            $this->page->slug,
            $this->page->isDraft() ? 'draft (not visible to the public yet)' : 'published',
        );
    }

    /**
     * The field contract for the types on the page, plus the names of the
     * types that could be added.
     */
    private function vocabularySection(): string
    {
        $present = array_values(array_unique(array_column($this->draft->blocks(), 'type')));
        $lines = [];

        foreach ($present as $type) {
            $contract = $this->vocabulary[$type] ?? null;

            if (! $contract instanceof BlockType) {
                continue;
            }

            $lines[] = sprintf(
                '- %s — %s%s%s%s%s',
                $type,
                $contract->description,
                "\n  fields: ".($contract->fields === [] ? '(none)' : implode(', ', $contract->fields)),
                // The layouts this type offers, inline rather than behind a
                // lookup tool: "make the hero full-width" must not cost a round
                // trip, and it is ~5 tokens per type actually on the page.
                $contract->variants === [] ? '' : ' — layouts: '.implode(', ', $contract->variants),
                // Axis NAMES only (~8 tokens/type): the values and their
                // guidance live once, in SetBlockAppearance's schema — the
                // same division of labour as the appearance paragraph below.
                $contract->axes === [] ? '' : ' — axes: '.implode(', ', array_map(
                    static fn (LayoutAxis $axis): string => $axis->value,
                    $contract->supportedAxes(),
                )),
                $contract->bind instanceof BindType
                    ? ' — also shows live '.$contract->bind->value.' details automatically; write only its narrative fields'
                    : '',
            );
        }

        return "## Block vocabulary\n"
            .'The fields each block type on this page accepts, and the layouts it can be switched '
            .'to. Field names not listed here are discarded — never invent one, and never write '
            ."\"variant\", \"bind\", \"appearance\" or a layout name as if it were a content field.\n"
            .($lines === [] ? '(nothing on the page yet)' : implode("\n", $lines))
            .$this->addableSection($present)
            ."\nList fields (features, testimonials, images, nav_links) take an array of flat "
            .'objects, e.g. features: [{icon, title, description}]. For links use relative paths '
            .'like "/contact" or anchors like "#contact".'
            // Every type supports appearance, so it is stated once here rather
            // than repeated on each vocabulary line. The outline names it only
            // for blocks that have one stored — silence there means the block
            // renders whatever its own layout was designed to do, NOT that it is
            // unstyled and waiting to be fixed.
            ."\nEvery section also has layout axes — its background, vertical spacing, and the "
            .'axes listed on its vocabulary line (content width, header alignment, columns, item '
            .'style, image shape) — all set with SetBlockAppearance. "Two columns", "left-align '
            .'the heading", "drop the cards" are axis changes, not layout switches. The page '
            .'outline names axes only where they have been set; a section without them uses what '
            .'its layout was designed to do, which is usually right.';
    }

    /**
     * The types that could be ADDED, each with what it is for.
     *
     * These used to be bare names on one line, which was fine at seven types and
     * stops being fine as the vocabulary grows: several blocks present as "a
     * heading plus a repeater of titled items", so a name alone leaves the model
     * choosing between them by vibe — and picking the wrong container is a
     * mistake no later edit fixes, because the fields differ.
     *
     * Their FIELDS stay unlisted: `AddBlock` seeds sample content and returns the
     * new key, so the model reads the real shape from the outline before writing
     * to it. One line each is what it needs to choose; the full contract is what
     * it needs to write, and it gets that a moment later.
     *
     * @param  list<string>  $present
     */
    private function addableSection(array $present): string
    {
        $addable = array_diff(array_keys($this->vocabulary), $present, ChromeSlot::values());

        if ($addable === []) {
            return '';
        }

        $lines = array_map(
            fn (string $type): string => sprintf('- %s — %s', $type, $this->vocabulary[$type]->description),
            $addable,
        );

        return "\nSections you can add:\n".implode("\n", $lines);
    }

    /**
     * The site-wide header and footer, with what each currently holds.
     *
     * Present only when {@see \App\Ai\Tools\UpdateChrome} is — a tenant with no
     * Business renders no chrome at all, so describing a navigation bar nobody
     * can see would invite an edit with nowhere to land.
     *
     * Their CONTENT is listed, not just their field names, and that is the
     * difference between this and the vocabulary section: the commonest request
     * here is "add X to the menu", which the model cannot do without knowing the
     * links already there — `nav_links` is replaced whole, so a partial list
     * silently deletes the rest of the navigation.
     */
    private function chromeSection(): ?string
    {
        if (! $this->chrome instanceof SiteChromeDraft) {
            return null;
        }

        $lines = [];

        // A missing contract is handled inline rather than with a `continue`
        // guard: both slots are registered block types (arch-enforced), so that
        // guard was a branch no test could reach without building a vocabulary
        // this app cannot produce.
        foreach (ChromeSlot::values() as $slot) {
            $contract = $this->vocabulary[$slot] ?? null;
            $fields = $contract instanceof BlockType ? $contract->fields : [];
            $variants = $contract instanceof BlockType ? $contract->variants : [];

            $lines[] = sprintf(
                '- %s accepts: %s%s',
                $slot,
                $fields === [] ? '(none)' : implode(', ', $fields),
                $variants === [] ? '' : ' — layouts: '.implode(', ', $variants),
            );
        }

        return "## Site header and footer\n"
            ."These frame EVERY page on the site, not just this one. Change them with UpdateChrome, and\n"
            ."say in your answer that the change is site-wide.\n"
            .$this->chrome->outline()
            ."\n".implode("\n", $lines)
            ."\nnav_links is replaced as a whole list, so to add one link send every existing link too."
            .' The brand name and logo come from the business profile and are not fields here.';
    }

    /**
     * The rest of the site: which pages exist. It closes a real gap — the
     * vocabulary section tells the model to write relative links like
     * "/contact" without ever saying which addresses are real.
     *
     * The business and the current style are NOT here; {@see businessSection()}
     * and {@see styleSection()} own them, and a second shorter version of either
     * would only give the model two answers to one question.
     */
    private function siteSection(): ?string
    {
        return $this->site?->digest();
    }

    /**
     * The style menu, and where the site currently stands.
     *
     * Present only when the design tools are — no Business profile means no
     * token row to write to, so publishing the menu would invite a call that
     * cannot land. ~150 tokens, and it is the whole grounding for "make it more
     * premium": {@see StylePreset::vibes()} holds INDUSTRY nouns, so without
     * `synonyms()` in the prompt an adjective has nothing to match against and
     * the model free-associates among six labels.
     */
    private function styleSection(): ?string
    {
        if (! $this->style instanceof SiteStyleDraft) {
            return null;
        }

        $lines = array_map(
            static fn (StylePreset $preset): string => sprintf(
                '- %s — %s Words: %s',
                $preset->value,
                $preset->description(),
                implode(', ', $preset->synonyms()),
            ),
            StylePreset::cases(),
        );

        return "## Site style\n"
            .'The whole site currently uses: '.$this->style->current()->describe()."\n"
            ."Changing this affects every page. The styles you can choose from:\n"
            .implode("\n", $lines)
            ."\nMatch the operator's own words against the \"Words\" list. A brand or website they "
            .'name is translated into those words, never stored or repeated back.';
    }

    /**
     * The facts the assistant may state. Without a business profile it has
     * none, and the instruction not to invent any becomes the whole rule.
     */
    private function businessSection(): string
    {
        if (! $this->business instanceof Business) {
            return "## Business facts\nNone recorded. Do not state any specific facts about the business.";
        }

        $lines = array_filter([
            'Name: '.$this->business->name,
            $this->business->category !== null ? 'Category: '.$this->business->category : null,
            $this->business->tagline !== null ? 'Tagline: '.$this->business->tagline : null,
            $this->business->description !== null ? 'Description: '.$this->business->description : null,
            $this->business->contact_email !== null ? 'Email: '.$this->business->contact_email : null,
            $this->business->contact_phone !== null ? 'Phone: '.$this->business->contact_phone : null,
        ]);

        return "## Business facts\nThese are the only facts you may state.\n".implode("\n", $lines)
            ."\nWrite user-visible copy in: ".($this->business->locale ?? 'en');
    }

    private function requestSection(): string
    {
        return "## The operator's request\n".$this->message;
    }
}
