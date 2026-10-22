<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Concerns;

use App\Actions\SaveDesignSelection;
use App\Design\DesignTokens;
use App\Design\TokenSelection;
use App\Models\Business;
use App\Themes\CurrentTheme;
use App\Themes\Scheme;
use Filament\Notifications\Notification;

/**
 * The Site Styles panel's behaviour, shared by the two surfaces that offer it:
 * the page editor's inspector rail and {@see \App\Filament\Tenant\Pages\Design}.
 *
 * They diverged badly before this. One was a `Radio` of scheme names with four
 * colour dots plus eight `Select`s; the other a modal of nine `Select`s. Only
 * one of them could edit brand colours, and neither showed what any option
 * looked like. Both now render `partials/site-styles.blade.php` over this trait,
 * so a change to the panel lands on both or on neither.
 *
 * The panel is ONE level: the current theme's schemes, each drawn as itself,
 * then the brand colours when the look in force is built on the brand palette.
 * It used to be two — five group cards with the eight axes one tap down — and
 * that second level is gone on purpose: a site's look is a designed scheme plus
 * brand colours, and letting an operator move one axis on its own is how the
 * combinations nobody designed got made. A theme that wants its schemes to
 * take the operator's colours builds them on the Brand palette.
 *
 * What genuinely differs between the two surfaces is WHERE a staged selection
 * lives and what happens when it changes: the editor stages into the canvas
 * preview draft so the operator judges it against their real page, while the
 * settings page — the only way in for a tenant with no pages yet — has no
 * canvas and simply holds the selection until Save. That is the whole of the
 * seam, and it is these three abstract methods.
 *
 * Staging is never saving on either surface. {@see SaveDesignSelection}
 * is the single write path for both, which is what stops the two from
 * disagreeing about what applying a design means.
 */
trait EditsSiteStyles
{
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

    /**
     * Stage a whole scheme: one click sets the entire look. Resolved through
     * the current theme, so a slug from the browser that the theme does not
     * offer stages nothing.
     *
     * Replaces the staged selection rather than merging into it: a scheme is
     * the whole look, and a brand hex staged against the previous one belongs
     * to a decision the operator just walked away from.
     */
    public function stageScheme(string $slug): void
    {
        $chosen = resolve(CurrentTheme::class)->definition()->scheme($slug);

        if (! $chosen instanceof Scheme) {
            return;
        }

        $this->stageStyles(['scheme' => $chosen->slug]);
    }

    /**
     * Stage one brand hex. Only offered while the look in force is built on
     * the brand palette, which is the only palette that reads these columns.
     *
     * The hex itself is validated by {@see TokenSelection::normalise()} on the
     * way in — these values end up inside a `style` attribute, so that check is
     * the injection guard and belongs there. The KEY is checked here, because
     * an invented one would otherwise ride into the staged selection and from
     * there into a `businesses` update.
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
     * The saved side is carried as {@see DesignTokens::toArray()}, which has
     * the `scheme` key a staged selection has, so the blade reads which scheme
     * is active the same way in both states; a staged selection is only ever
     * `scheme` plus brand hexes.
     *
     * @return array<string, string|null>
     */
    public function styleSelection(): array
    {
        return $this->stagedStyles() ?? $this->styleBusiness()->design_tokens->toArray();
    }

    /**
     * The tokens the panel is showing, resolved: the staged scheme's bundle
     * through the current theme, or the saved tokens when nothing is staged.
     *
     * The saved tokens are returned as they are rather than re-resolved from
     * their slug — a template may have provisioned them with its own axis
     * overrides on top of a scheme, and the panel must show the look the site
     * actually has.
     */
    public function styleTokens(): DesignTokens
    {
        $saved = $this->styleBusiness()->design_tokens;
        $staged = $this->stagedStyles();

        return $staged === null
            ? $saved
            : resolve(CurrentTheme::class)->definition()->tokensFor($staged, $saved);
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
