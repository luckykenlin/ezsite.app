<?php

declare(strict_types=1);

namespace App\Ai;

use App\Design\DesignTokens;
use App\Themes\Scheme;

/**
 * The turn-scoped working copy of the site's design tokens — the design-side
 * twin of {@see PageDraft}, and the reason giving the assistant design
 * authority does not let it repaint a live website.
 *
 * Blocks are safe to edit in a chat turn because nothing about them is
 * persisted: {@see \App\Actions\Pages\ChatEditPage} hands the edited list back
 * and the operator still has to press Save. Design tokens are not like that in
 * three ways. They are SITE-scoped, so they reach every page including
 * published ones. They are not part of the block list, so neither
 * {@see ChangedBlocks} nor the editor's undo snapshot can see them. And the
 * turn runs in a queue worker, where a tool calling
 * {@see \App\Actions\ApplyScheme} would write immediately —
 * {@see \App\Design\ThemeVariables::style()} reads saved tokens on every public
 * render, so the assistant would restyle the operator's live site while they
 * were still reading its reply.
 *
 * So a tool mutates this instead, and nothing here touches the database. What
 * it holds rides back to the editor as a preview draft (the same one the Site
 * Styles rail produces), and {@see \App\Actions\SaveDesignSelection} stays
 * the single write path.
 *
 * Two moves only — a whole scheme, or brand hexes — because those are the two
 * things a site's look is made of. There is no per-axis `apply()`: it was the
 * assistant's door onto combinations nobody designed, and it went with the
 * operator's.
 *
 * Mutable and therefore not `readonly`: several tools in one turn have to see
 * each other's work, exactly as they do with {@see PageDraft}.
 */
final class SiteStyleDraft
{
    /**
     * The scheme THIS turn applied, or null when it applied none. Null is what
     * lets a brand-only turn stage colours without staging a scheme — and a
     * staged scheme is a "replace the look with this bundle" instruction, so
     * inventing one from the saved slug would wipe a template's overrides.
     */
    private ?Scheme $applied = null;

    /**
     * Whether THIS turn changed anything. Its own flag rather than
     * `$applied !== null`, because a brand-only turn changes something without
     * applying a scheme, and a turn that merely STARTS from a seeded preview
     * must not re-stage it through {@see toArray()}: the Apply gate is already
     * up, and a re-staged copy would snapshot an edit nobody made.
     */
    private bool $dirty = false;

    /**
     * Brand hexes staged by THIS turn — the "make the primary colour deep
     * blue" lever. Validated by the tool before they get here; ride inside
     * {@see toArray()} beside the scheme so the whole staged look travels as
     * one draft.
     *
     * @var array<string, string>
     */
    private array $stagedBrand = [];

    /**
     * @param  DesignTokens  $saved  the tokens as they are on disk
     * @param  Scheme|null  $seeded  the scheme a previous turn staged that the
     *                               operator has not applied yet, carried back
     *                               in so "a bit darker" refines what the
     *                               operator is LOOKING AT rather than silently
     *                               restarting from the saved tokens. A Scheme,
     *                               not tokens: an unapplied draft only ever
     *                               holds a slug, and re-emitting it is what
     *                               keeps it on the canvas after a brand-only
     *                               follow-up
     * @param  array<string, string>  $savedBrand  the business row's persisted brand
     *                                             hexes. Never re-emitted by
     *                                             toArray(): they are already on disk.
     * @param  array<string, string>  $seededBrand  hexes riding in the editor's
     *                                              still-unapplied draft, re-emitted by
     *                                              toArray() so a later fine-tune does
     *                                              not silently drop them from the
     *                                              preview
     */
    public function __construct(
        private readonly DesignTokens $saved,
        private readonly ?Scheme $seeded = null,
        private readonly array $savedBrand = [],
        private readonly array $seededBrand = [],
    ) {
        //
    }

    /**
     * The effective brand hexes — this turn's staged ones over the seeded
     * draft's over the saved row's.
     *
     * @return array<string, string>
     */
    public function brand(): array
    {
        return [...$this->savedBrand, ...$this->seededBrand, ...$this->stagedBrand];
    }

    /**
     * Stage validated brand hexes on top of whatever this turn holds. The look
     * is left exactly as it is — {@see current()} keeps answering the scheme
     * staged so far, or the saved tokens — because the hexes are colours for
     * the brand palette, not a choice of look.
     *
     * @param  array<string, string>  $hexes
     */
    public function stageBrand(array $hexes): void
    {
        $this->stagedBrand = [...$this->stagedBrand, ...$hexes];
        $this->dirty = true;
    }

    /**
     * The look in force: this turn's scheme, then the seeded draft's, then the
     * saved tokens — which a brand-only turn leaves untouched.
     */
    public function current(): DesignTokens
    {
        return ($this->applied ?? $this->seeded)->tokens ?? $this->saved;
    }

    /**
     * Whether the style the operator is looking at is a still-unapplied
     * preview from an earlier turn — what lets the prompt say so, so the
     * model does not describe it as the site's actual style.
     */
    public function seededPreview(): bool
    {
        return $this->seeded instanceof Scheme || $this->seededBrand !== [];
    }

    /**
     * The tokens as they are ON DISK, ignoring anything this turn staged.
     *
     * Read by {@see Tools\CreatePage}, and the distinction matters there: a new
     * page is WRITTEN to the database, so its block layouts should match the style
     * the site actually has. Stamping this turn's staged scheme onto it would
     * produce a page laid out for a look the operator may never apply — and
     * nothing would later go back and fix it.
     */
    public function saved(): DesignTokens
    {
        return $this->saved;
    }

    public function touched(): bool
    {
        return $this->dirty;
    }

    /**
     * Stage a scheme as the whole look — the staged equivalent of
     * {@see \App\Actions\ApplyScheme}.
     */
    public function applyScheme(Scheme $scheme): void
    {
        $this->applied = $scheme;
        $this->dirty = true;
    }

    /**
     * The staged selection in the shape the editor stages and
     * {@see \App\Actions\SaveDesignSelection} consumes
     * ({@see \App\Design\TokenSelection::normalise()}), or null when this
     * turn left the style alone — including a turn that merely STARTED from a
     * seeded, still-unapplied preview: the editor already holds that draft,
     * and handing it back would re-stage an edit nobody made this turn.
     *
     * `scheme` is present only when a scheme was actually picked — this turn
     * or the seeded draft — never the saved slug: every reader treats a staged
     * scheme as "replace the look with this bundle", so a brand-only turn must
     * hand back only its hexes. No axes ride along; every reader resolves the
     * slug through the current theme. Brand hexes: this turn's staged ones,
     * plus the seeded draft's — the editor's draft is REPLACED by this array,
     * so leaving a seeded hex (or the seeded scheme) out would silently strip
     * it from the preview.
     *
     * @return array<string, string>|null
     */
    public function toArray(): ?array
    {
        if (! $this->dirty) {
            return null;
        }

        $scheme = $this->applied ?? $this->seeded;

        return [
            ...($scheme instanceof Scheme ? ['scheme' => $scheme->slug] : []),
            ...$this->seededBrand,
            ...$this->stagedBrand,
        ];
    }
}
