<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Concerns;

use App\Actions\SaveDesignSelection;
use App\Design\DesignTokens;
use App\Design\StyleGroup;
use App\Design\StylePreset;
use App\Design\TokenKey;
use App\Design\TokenSelection;
use App\Models\Business;
use BackedEnum;
use Filament\Notifications\Notification;

/**
 * The Site Styles panel's behaviour, shared by the two surfaces that offer it:
 * the page editor's inspector rail and {@see \App\Filament\Tenant\Pages\Design}.
 *
 * They diverged badly before this. One was a `Radio` of preset names with four
 * colour dots plus eight `Select`s; the other a modal of nine `Select`s. Only
 * one of them could edit brand colours, and neither showed what any option
 * looked like. Both now render `partials/site-styles.blade.php` over this trait,
 * so a change to the panel lands on both or on neither.
 *
 * What genuinely differs between them is WHERE a staged selection lives and what
 * happens when it changes: the editor stages into the canvas preview draft so
 * the operator judges it against their real page, while the settings page — the
 * only way in for a tenant with no pages yet — has no canvas and simply holds
 * the selection until Save. That is the whole of the seam, and it is these three
 * abstract methods.
 *
 * Staging is never saving on either surface. {@see SaveDesignSelection}
 * is the single write path and decides preset-vs-custom for both, which is what
 * stops the two from disagreeing about what applying a design means.
 */
trait EditsSiteStyles
{
    /**
     * The group drilled into, or null at the top level. Mirrors Squarespace's
     * two-level shape: five cards, details one tap down.
     *
     * Not `#[Locked]`: which card is open is the browser's business, and the
     * worst a forged value can do is read as the top level.
     */
    public ?string $styleGroup = null;

    /**
     * The Business the tokens belong to. Both surfaces are gated on one
     * existing, so this fails loud rather than returning null.
     */
    abstract public function styleBusiness(): Business;

    /**
     * The staged selection, or null when nothing is staged.
     *
     * @return array<string, string|null>|null
     */
    abstract protected function stagedStyles(): ?array;

    /**
     * Hold a staged selection, or drop it when given null. The editor previews
     * it on the canvas here; the settings page just remembers it.
     *
     * @param  array<string, string|null>|null  $selection
     */
    abstract protected function stageStyles(?array $selection): void;

    public function openStyleGroup(string $group): void
    {
        $this->styleGroup = StyleGroup::tryFrom($group)?->value;
    }

    public function closeStyleGroup(): void
    {
        $this->styleGroup = null;
    }

    /**
     * The group drilled into, resolved. Null at the top level — and also for a
     * value the browser invented, which then simply reads as the top level.
     */
    public function openedStyleGroup(): ?StyleGroup
    {
        return $this->styleGroup === null ? null : StyleGroup::tryFrom($this->styleGroup);
    }

    /**
     * Stage a whole preset: one click sets all eight tokens, which is the only
     * reason the Themes card can offer a look rather than a list.
     */
    public function stagePreset(string $preset): void
    {
        $chosen = StylePreset::tryFrom($preset);

        if (! $chosen instanceof StylePreset) {
            return;
        }

        $this->stageStyles($chosen->tokens()->toArray());
    }

    /**
     * Stage one token, leaving the rest of the selection alone.
     *
     * `preset` deliberately RIDES ALONG unchanged rather than being cleared:
     * {@see SaveDesignSelection} decides preset-vs-custom by comparing the whole
     * selection, so changing a token and changing it back is saved as the preset
     * again. Clearing it here would make that a one-way door.
     *
     * Both arguments arrive from the browser, so both are resolved against their
     * enum before use — an unrecognised pair would otherwise reach the canvas as
     * a silent fallback to the default token and then throw on save, which is a
     * far worse way to find out.
     */
    public function stageToken(string $key, string $value): void
    {
        $token = TokenKey::tryFrom($key);

        if (! $token instanceof TokenKey || ! $token->tryValue($value) instanceof BackedEnum) {
            return;
        }

        $this->stageStyles([...$this->styleSelection(), $key => $value]);
    }

    /**
     * Stage one brand hex. Only reachable while the palette is `brand`, which is
     * the only palette that reads these columns.
     *
     * The hex itself is validated by {@see TokenSelection::normalise()} on the
     * way in — these values end up inside a `style` attribute, so that check is
     * the injection guard and belongs there. The KEY is checked here for the
     * same reason `stageToken()` checks its own.
     */
    public function stageBrandColor(string $key, string $hex): void
    {
        if (! in_array($key, TokenSelection::BRAND_KEYS, true)) {
            return;
        }

        $this->stageStyles([...$this->styleSelection(), $key => $hex]);
    }

    public function applySiteStyles(): void
    {
        $staged = $this->stagedStyles();

        if ($staged === null) {
            return;
        }

        resolve(SaveDesignSelection::class)->handle($this->styleBusiness(), $staged);

        $this->stageStyles(null);

        Notification::make()
            ->title(__('Design applied to the whole site'))
            ->success()
            ->send();
    }

    public function resetSiteStyles(): void
    {
        $this->stageStyles(null);
    }

    /**
     * What the panel is currently showing: the staged selection when there is
     * one, the saved tokens otherwise.
     *
     * Carried as {@see DesignTokens::toArray()}, which is already exactly the
     * `preset` + eight-key shape {@see SaveDesignSelection} reads, so nothing
     * here rebuilds it field by field.
     *
     * @return array<string, string|null>
     */
    public function styleSelection(): array
    {
        return $this->stagedStyles() ?? $this->styleBusiness()->design_tokens->toArray();
    }

    /**
     * The same selection as tokens — the backdrop every specimen varies one axis
     * of, so a candidate is always shown against the rest of YOUR look.
     */
    public function styleTokens(): DesignTokens
    {
        return DesignTokens::fromArray($this->styleSelection());
    }

    /**
     * The brand hex the panel should show: the staged one, then the saved
     * column, then nothing.
     */
    public function brandColor(string $key): ?string
    {
        $staged = $this->styleSelection()[$key] ?? null;

        if (is_string($staged)) {
            return $staged;
        }

        $saved = $this->styleBusiness()->{$key};

        return is_string($saved) ? $saved : null;
    }

    public function hasStagedStyles(): bool
    {
        return $this->stagedStyles() !== null;
    }
}
