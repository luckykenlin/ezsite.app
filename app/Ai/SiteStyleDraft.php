<?php

declare(strict_types=1);

namespace App\Ai;

use App\Design\AccentStyle;
use App\Design\ColorPalette;
use App\Design\DesignTokens;
use App\Design\FontPair;
use App\Design\RadiusScale;
use App\Design\SectionDivider;
use App\Design\SpacingDensity;
use App\Design\StylePreset;
use App\Design\TokenKey;
use App\Design\TypeStyle;

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
 * {@see \App\Actions\UpdateDesignTokens} would write immediately —
 * {@see \App\Design\ThemeVariables::style()} reads saved tokens on every public
 * render, so the assistant would restyle the operator's live site while they
 * were still reading its reply.
 *
 * So a tool mutates this instead, and nothing here touches the database. What
 * it holds rides back to the editor as a preview draft (the same one the Design
 * modal's live fields produce), and {@see \App\Actions\SaveDesignSelection}
 * stays the single write path.
 *
 * Mutable and therefore not `readonly`: several tools in one turn have to see
 * each other's work, exactly as they do with {@see PageDraft}.
 */
final class SiteStyleDraft
{
    /**
     * Null until something in this turn changes the style — which is what lets
     * {@see toArray()} distinguish "left alone" from "deliberately set back to
     * what it already was". Only the former must avoid staging a draft, because
     * a staged draft raises an Apply affordance the operator would have nothing
     * to apply.
     */
    private ?DesignTokens $staged = null;

    /**
     * Whether THIS turn changed anything. Its own flag rather than
     * `$staged !== null`, because a turn can start with `$seeded` — a style a
     * previous turn staged that the operator has not applied yet — and a turn
     * that merely reads that seed must not re-stage it through
     * {@see toArray()}: the Apply gate is already up, and a re-staged copy
     * would snapshot an edit nobody made.
     */
    private bool $dirty = false;

    /**
     * Brand hexes staged by THIS turn — the "make the primary colour deep
     * blue" lever. Validated by the tool before they get here; ride inside
     * {@see toArray()} beside the tokens so the whole staged look travels as
     * one draft.
     *
     * @var array<string, string>
     */
    private array $stagedBrand = [];

    /**
     * @param  DesignTokens  $saved  the tokens as they are on disk
     * @param  DesignTokens|null  $seeded  a previous turn's still-unapplied staged
     *                                     style, carried back in so "a bit darker"
     *                                     refines what the operator is LOOKING AT
     *                                     rather than silently restarting from the
     *                                     saved tokens
     * @param  array<string, string>  $savedBrand  the business row's persisted brand
     *                                             hexes — what makes `palette: brand`
     *                                             legal without staging a new hex.
     *                                             Never re-emitted by toArray(): they
     *                                             are already on disk.
     * @param  array<string, string>  $seededBrand  hexes riding in the editor's
     *                                              still-unapplied draft, re-emitted by
     *                                              toArray() so a later fine-tune does
     *                                              not silently drop them from the
     *                                              preview
     */
    public function __construct(
        private readonly DesignTokens $saved,
        private readonly ?DesignTokens $seeded = null,
        private readonly array $savedBrand = [],
        private readonly array $seededBrand = [],
    ) {
        //
    }

    /**
     * The effective brand hexes — this turn's staged ones over the seeded
     * draft's over the saved row's. What the brand-palette guard reads to
     * decide whether `palette: brand` has a primary to derive from.
     *
     * @return array<string, string>
     */
    public function brand(): array
    {
        return [...$this->savedBrand, ...$this->seededBrand, ...$this->stagedBrand];
    }

    /**
     * Stage validated brand hexes on top of whatever this turn holds. The
     * caller pairs this with a `palette: brand` fine-tune — a hex without the
     * brand palette would change nothing visible — but the tokens are
     * materialised here too, so a caller that forgets still produces a
     * complete draft rather than a dirty flag with nothing behind it.
     *
     * @param  array<string, string>  $hexes
     */
    public function stageBrand(array $hexes): void
    {
        $this->staged ??= $this->current();
        $this->stagedBrand = [...$this->stagedBrand, ...$hexes];
        $this->dirty = true;
    }

    public function current(): DesignTokens
    {
        return $this->staged ?? $this->seeded ?? $this->saved;
    }

    /**
     * Whether the style the operator is looking at is a still-unapplied
     * preview from an earlier turn — what lets the prompt say so, so the
     * model does not describe it as the site's actual style.
     */
    public function seededPreview(): bool
    {
        return $this->seeded instanceof DesignTokens;
    }

    /**
     * The tokens as they are ON DISK, ignoring anything this turn staged.
     *
     * Read by {@see Tools\CreatePage}, and the distinction matters there: a new
     * page is WRITTEN to the database, so its block layouts should match the style
     * the site actually has. Stamping this turn's staged preset onto it would
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
     * Replace every token with a preset's bundle, keeping the preset marker —
     * the staged equivalent of {@see \App\Actions\ApplyStylePreset}.
     */
    public function applyPreset(StylePreset $preset): void
    {
        $this->staged = $preset->tokens();
        $this->dirty = true;
    }

    /**
     * Fine-tune individual tokens on top of whatever is staged so far. Keys
     * absent from `$changes` are left alone.
     *
     * Delegates to {@see DesignTokens::with()}, so it inherits the rule that any
     * manual override detaches the preset marker — a preset-then-tweak turn
     * ends up custom, the same as it would through the Design modal.
     *
     * Callers hand over raw enum VALUES, already checked against the tool
     * schema's enum, so an unrecognised one here means a caller bypassed that
     * check; it leaves the token alone rather than throwing, because the
     * alternative is an exception inside a queue worker that would lose the
     * whole turn over one field.
     *
     * @param  array<string, string>  $changes  TokenKey value => enum value
     */
    public function apply(array $changes): void
    {
        $this->dirty = true;
        $this->staged = $this->current()->with(
            palette: $this->value(ColorPalette::class, $changes, TokenKey::Palette),
            fontPair: $this->value(FontPair::class, $changes, TokenKey::FontPair),
            radius: $this->value(RadiusScale::class, $changes, TokenKey::Radius),
            density: $this->value(SpacingDensity::class, $changes, TokenKey::Density),
            typeStyle: $this->value(TypeStyle::class, $changes, TokenKey::TypeStyle),
            divider: $this->value(SectionDivider::class, $changes, TokenKey::Divider),
            accent: $this->value(AccentStyle::class, $changes, TokenKey::Accent),
        );
    }

    /**
     * The staged tokens in the shape the editor stages and
     * {@see \App\Actions\SaveDesignSelection} consumes, or null when this turn
     * left the style alone — including a turn that merely STARTED from a
     * seeded, still-unapplied preview: the editor already holds that draft,
     * and handing it back would re-stage an edit nobody made this turn.
     *
     * {@see DesignTokens::toArray()} already emits exactly the keys both of
     * those expect, so there is no adapter anywhere on this path. Brand hexes
     * ride beside the tokens: this turn's staged ones, plus the seeded draft's
     * — the editor's draft is REPLACED by this array, so leaving a seeded hex
     * out would silently strip it from the preview.
     *
     * @return array<string, string|null>|null
     */
    public function toArray(): ?array
    {
        // Every dirty-setter also materialises $staged, so the second half of
        // this condition is the type-level restatement of that invariant, not
        // a reachable branch of its own.
        if (! $this->dirty || ! $this->staged instanceof DesignTokens) {
            return null;
        }

        $tokens = $this->staged->toArray();
        $brand = [...$this->seededBrand, ...$this->stagedBrand];

        return $brand === [] ? $tokens : [...$tokens, ...$brand];
    }

    /**
     * One token from a raw change map, typed. Mirrors
     * {@see \App\Actions\UpdateDesignTokens::enumValue()} — see that method for
     * why the class is passed rather than read from `$key->tokenClass()`.
     *
     * @template TEnum of \BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @param  array<string, string>  $changes
     * @return TEnum|null
     */
    private function value(string $enum, array $changes, TokenKey $key): mixed
    {
        $raw = $changes[$key->value] ?? null;

        return is_string($raw) ? $enum::tryFrom($raw) : null;
    }
}
