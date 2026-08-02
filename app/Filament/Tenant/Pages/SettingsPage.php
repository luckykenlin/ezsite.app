<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

/**
 * A tenant settings screen: one form over one singleton row, a Save button,
 * a success toast.
 *
 * {@see BusinessProfile}, {@see SiteChromeSettings}, {@see CaptureSettings}
 * and {@see Design} are all this shape — before this base they carried four
 * byte-identical Blade views and the same `$data`/`statePath`/notification
 * boilerplate each. What stays per-page is what actually differs: `mount()`
 * (each reads a different row into a different state shape), the components,
 * and how the state persists.
 *
 * @property-read Schema $form
 */
abstract class SettingsPage extends Page
{
    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    protected string $view = 'filament.tenant.pages.settings-form';

    /**
     * @return array<int, Component>
     */
    abstract protected function components(): array;

    /**
     * @param  array<string, mixed>  $state
     */
    abstract protected function persist(array $state): void;

    abstract protected function savedNotificationTitle(): string;

    final public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components($this->components());
    }

    final public function save(): void
    {
        $this->persist($this->form->getState());

        Notification::make()
            ->title($this->savedNotificationTitle())
            ->success()
            ->send();
    }
}
