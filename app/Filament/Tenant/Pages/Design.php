<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Design\TokenSelection;
use App\Filament\Tenant\Concerns\EditsSiteStyles;
use App\Models\Business;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Locked;

/**
 * The tenant's design settings: the same Site Styles panel the page editor
 * carries in its inspector, on its own screen.
 *
 * Deliberately NOT a {@see SettingsPage} any more, and that is the whole point
 * of the rebuild. As a schema form it could only offer what Filament fields can
 * express — a `Radio` of preset names with four colour dots, then eight `Select`s
 * of `Str::headline()`'d enum values — so choosing a look meant reading
 * "Refined", "Serene" and "Quiet" and guessing at the difference. The panel
 * shows each option drawn in itself instead, which needs real markup.
 *
 * It survives the editor's rail rather than being replaced by it because the
 * rail is reachable only from a page: a tenant whose site has not been generated
 * yet would otherwise have no way to touch their design at all. What it does not
 * have is a canvas — there is no page in front of you here — so a staged
 * selection simply waits for "Apply to site" instead of previewing anywhere.
 *
 * Hidden until a Business profile exists, because the tokens live on that row.
 */
final class Design extends Page
{
    use EditsSiteStyles;

    /**
     * The staged selection, held until Save. `#[Locked]` for the same reason the
     * editor's canvas draft is: it is the array handed to `SaveDesignSelection`,
     * so a client-writable copy would be a POST straight into the `businesses`
     * row.
     *
     * @var array<string, string|null>|null
     */
    #[Locked]
    public ?array $styleDraft = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected string $view = 'filament.tenant.pages.design';

    public static function canAccess(): bool
    {
        return Business::query()->exists();
    }

    public function styleBusiness(): Business
    {
        return Business::query()->firstOrFail();
    }

    /**
     * @return array<string, string|null>|null
     */
    protected function stagedStyles(): ?array
    {
        return $this->styleDraft;
    }

    /**
     * @param  array<string, string|null>|null  $selection
     */
    protected function stageStyles(?array $selection): void
    {
        $this->styleDraft = $selection === null ? null : TokenSelection::normalise($selection);
    }
}
