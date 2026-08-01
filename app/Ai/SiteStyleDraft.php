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

    public function __construct(private readonly DesignTokens $saved)
    {
        //
    }

    public function current(): DesignTokens
    {
        return $this->staged ?? $this->saved;
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
        return $this->staged instanceof DesignTokens;
    }

    /**
     * Replace every token with a preset's bundle, keeping the preset marker —
     * the staged equivalent of {@see \App\Actions\ApplyStylePreset}.
     */
    public function applyPreset(StylePreset $preset): void
    {
        $this->staged = $preset->tokens();
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
     * left the style alone.
     *
     * {@see DesignTokens::toArray()} already emits exactly the keys both of
     * those expect, so there is no adapter anywhere on this path.
     *
     * @return array{preset: string|null, palette: string, font_pair: string, type_style: string, radius: string, density: string, divider: string, accent: string}|null
     */
    public function toArray(): ?array
    {
        return $this->staged?->toArray();
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
