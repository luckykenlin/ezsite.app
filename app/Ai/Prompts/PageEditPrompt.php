<?php

declare(strict_types=1);

namespace App\Ai\Prompts;

use App\Ai\PageDraft;
use App\Models\Business;
use App\Models\Page;
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
     * @param  array<string, array{type: string, variants: list<string>, bind: string|null, icon: string|null, fields: list<string>}>  $vocabulary
     */
    public function __construct(
        private Page $page,
        private PageDraft $draft,
        private array $vocabulary,
        private ?Business $business,
        private string $message,
    ) {
        //
    }

    public function __toString(): string
    {
        return implode("\n\n", array_filter([
            $this->pageSection(),
            $this->draft->outline(),
            $this->vocabularySection(),
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

            if ($contract === null) {
                continue;
            }

            $lines[] = sprintf(
                '- %s: %s%s',
                $type,
                $contract['fields'] === [] ? '(no content fields)' : implode(', ', $contract['fields']),
                $contract['bind'] === null
                    ? ''
                    : ' — also shows live '.$contract['bind'].' details automatically; write only its narrative fields',
            );
        }

        $addable = array_diff(array_keys($this->vocabulary), $present, ['header', 'footer']);

        return "## Block vocabulary\n"
            .'The fields each block type on this page accepts. Field names not listed here are '
            ."discarded — never invent one, and never write \"variant\" or \"bind\".\n"
            .($lines === [] ? '(nothing on the page yet)' : implode("\n", $lines))
            .($addable === [] ? '' : "\nTypes you can add: ".implode(', ', $addable).'.')
            ."\nList fields (features, testimonials, images, nav_links) take an array of flat "
            .'objects, e.g. features: [{icon, title, description}]. For links use relative paths '
            .'like "/contact" or anchors like "#contact". The site header and footer are '
            .'site-wide and are not part of this page.';
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
