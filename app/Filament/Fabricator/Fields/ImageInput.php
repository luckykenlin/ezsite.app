<?php

declare(strict_types=1);

namespace App\Filament\Fabricator\Fields;

use Awcodes\Curator\Components\Forms\CuratorPicker;
use Filament\Actions\Action;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;

/**
 * The shared image field for block schemas: a Curator picker (browse the
 * tenant media library or upload in place) storing a single media id in the
 * block's JSON data. The render layer translates the id into the URL prop
 * the block views consume ({@see \App\Filament\Fabricator\BlockRegistry}
 * MEDIA_KEYS) — views never see media ids, and a deleted media entry
 * degrades to the block's own empty-image guard.
 *
 * A subclass rather than the factory it used to be, for one reason: Curator
 * writes this field's state from paths that announce nothing. The panel's
 * "use this image" calls `updateState()` straight from JavaScript and the
 * remove buttons set state inside their own actions; none of them calls
 * `afterStateUpdated`, and Livewire's `updated()` hook does not fire either
 * because no component property was written. So the page editor was never
 * told, and choosing an image left the canvas showing the page without it
 * until some unrelated edit happened to push a preview. The overrides below
 * close that gap — what to DO about it stays with the editor's inspector
 * section, which is where the hook is registered.
 */
final class ImageInput extends CuratorPicker
{
    public static function make(?string $name = null): static
    {
        return parent::make($name)
            ->label('Image')
            ->buttonLabel('Choose image');
    }

    /**
     * The panel's "use this image", called straight from the browser.
     *
     * The attribute is re-declared because PHP reads attributes off the method
     * that actually runs: without it Filament's `callSchemaComponentMethod()`
     * would refuse this override and picking an image would stop working
     * altogether.
     *
     * @param  array<string, mixed>  $arguments
     */
    #[ExposedLivewireMethod]
    public function updateState(array $arguments): void
    {
        parent::updateState($arguments);

        // The same guard the parent applies: the event reaches every picker on
        // the page, and only the addressed one has changed.
        if ($this->getStatePath() === ($arguments['statePath'] ?? null)) {
            $this->callAfterStateUpdated();
        }
    }

    public function getRemoveAction(): Action
    {
        return parent::getRemoveAction()
            ->after(fn (ImageInput $component): ImageInput => $component->callAfterStateUpdated());
    }

    public function getRemoveAllAction(): Action
    {
        return parent::getRemoveAllAction()
            ->after(fn (ImageInput $component): ImageInput => $component->callAfterStateUpdated());
    }
}
