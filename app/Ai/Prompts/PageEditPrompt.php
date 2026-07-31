<?php

declare(strict_types=1);

namespace App\Ai\Prompts;

use App\Ai\PageDraft;
use App\Ai\SiteStyleDraft;
use App\Design\StylePreset;
use App\Enums\BindType;
use App\Enums\ChromeSlot;
use App\Models\Business;
use App\Models\Page;
use App\Site\Blocks\BlockType;
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
     */
    public function __construct(
        private Page $page,
        private PageDraft $draft,
        private array $vocabulary,
        private ?Business $business,
        private string $message,
        private ?SiteContext $site = null,
        private ?SiteStyleDraft $style = null,
    ) {
        //
    }

    public function __toString(): string
    {
        return implode("\n\n", array_filter([
            $this->pageSection(),
            $this->draft->outline(),
            $this->vocabularySection(),
            $this->siteSection(),
            $this->styleSection(),
            $this->businessSection(),
            $this->requestSection(),
        ]));
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
        $present = array_unique(array_column($this->draft->blocks(), 'type'));
        $lines = [];

        foreach ($present as $type) {
            $contract = $this->vocabulary[$type] ?? null;

            if (! $contract instanceof BlockType) {
                continue;
            }

            $lines[] = sprintf(
                '- %s: %s%s%s',
                $type,
                $contract->fields === [] ? '(no content fields)' : implode(', ', $contract->fields),
                // The layouts this type offers, inline rather than behind a
                // lookup tool: "make the hero full-width" must not cost a round
                // trip, and it is ~5 tokens per type actually on the page.
                $contract->variants === [] ? '' : ' — layouts: '.implode(', ', $contract->variants),
                $contract->bind instanceof BindType
                    ? ' — also shows live '.$contract->bind->value.' details automatically; write only its narrative fields'
                    : '',
            );
        }

        $addable = array_diff(array_keys($this->vocabulary), $present, ChromeSlot::values());

        return "## Block vocabulary\n"
            .'The fields each block type on this page accepts, and the layouts it can be switched '
            .'to. Field names not listed here are discarded — never invent one, and never write '
            ."\"variant\", \"bind\" or a layout name as if it were a content field.\n"
            .($lines === [] ? '(nothing on the page yet)' : implode("\n", $lines))
            .($addable === [] ? '' : "\nTypes you can add: ".implode(', ', $addable).'.')
            ."\nList fields (features, testimonials, images, nav_links) take an array of flat "
            .'objects, e.g. features: [{icon, title, description}]. For links use relative paths '
            .'like "/contact" or anchors like "#contact". The site header and footer are '
            .'site-wide and are not part of this page.';
    }

    /**
     * The rest of the site: who the business is, what the saved look is, and
     * which pages exist. That last one closes a real gap — the vocabulary
     * section tells the model to write relative links like "/contact" without
     * ever saying which addresses are real.
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
