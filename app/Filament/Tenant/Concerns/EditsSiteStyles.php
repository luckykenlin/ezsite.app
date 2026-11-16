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
     * Replaces any previously staged SCHEME but keeps staged brand hexes: they
     * are bound for the business's brand columns, not for the look, so a scheme
     * pick is no reason to throw away a colour the operator just chose.
     */
    public function stageScheme(string $slug): void
    {
        $chosen = resolve(CurrentTheme::class)->definition()->scheme($slug);

        if (! $chosen instanceof Scheme) {
            return;
        }

        $this->stageStyles([...$this->stagedBrandColors(), 'scheme' => $chosen->slug]);
    }

    /**
     * Stage one brand hex. Only offered while the look in force is built on
     * the brand palette, which is the only palette that reads these columns.
     *
     * Merged into what is STAGED, never into the saved tokens: a staged
     * `scheme` means "replace the look with this scheme's bundle", so seeding
     * one from the saved slug would turn a colour tweak into a scheme re-apply
     * — and wipe whatever a template folded in on top of it (DimSumHouse's
     * Brand palette is the case that made this visible). No scheme staged
     * means the saved look stays exactly as it is, with these colours.
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
     * The staged selection, or an empty one when nothing is staged.
     *
     * Deliberately NOT the saved tokens as a fallback: a selection carrying a
     * `scheme` is an instruction to replace the look with that scheme's bundle,
     * so presenting the saved slug as if it were staged is what turned a
     * brand-colour edit into a scheme re-apply. Which scheme is on is
     * {@see activeScheme()}'s question.
     *
     * @return array<string, string|null>
     */
    public function styleSelection(): array
    {
        return $this->stagedStyles() ?? [];
    }

    /**
     * The scheme the panel marks as active: the one staged this session, else
     * the one the saved tokens came from.
     */
    public function activeScheme(): ?string
    {
        return $this->styleSelection()['scheme'] ?? $this->styleBusiness()->design_tokens->scheme;
    }

    /**
     * The tokens the panel is showing, resolved: the staged scheme's bundle
     * through the current theme, or the saved tokens when no scheme is staged.
     *
     * The saved tokens are returned as they are rather than re-resolved from
     * their slug — a template may have provisioned them with its own axis
     * overrides on top of a scheme, and the panel must show the look the site
     * actually has.
     */
    public function styleTokens(): DesignTokens
    {
        return resolve(CurrentTheme::class)->definition()->tokensFor(
            $this->styleSelection(),
            $this->styleBusiness()->design_tokens,
        );
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

    /**
     * The brand hexes in the staged selection — what a scheme pick carries
     * forward.
     *
     * @return array<string, string>
     */
    private function stagedBrandColors(): array
    {
        $hexes = [];

        foreach (TokenSelection::BRAND_KEYS as $key) {
            $hex = $this->styleSelection()[$key] ?? null;

            if (is_string($hex)) {
                $hexes[$key] = $hex;
            }
        }

        return $hexes;
    }
}
