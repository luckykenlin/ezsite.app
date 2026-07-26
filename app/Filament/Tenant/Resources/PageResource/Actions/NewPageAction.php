<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Actions;

use App\Enums\PageStatus;
use App\Filament\Tenant\Resources\PageResource;
use App\Filament\Tenant\Resources\PageResource\Pages\PageEditor;
use App\Models\Page as PageModel;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * The left pane's "New page" modal: the title auto-fills the slug, the page
 * is created as a draft inheriting the current page's layout, and the editor
 * navigates straight to it.
 */
final readonly class NewPageAction
{
    public static function make(PageEditor $editor): Action
    {
        $fields = new PageIdentityFields;

        return Action::make('newPage')
            ->label('New page')
            ->icon(Heroicon::OutlinedPlus)
            ->color('gray')
            ->schema([
                TextInput::make('title')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        $set('slug', Str::slug($state ?? ''));
                    }),
                $fields->slug(),
                $fields->parent(),
            ])
            ->action(function (array $data) use ($editor): void {
                $page = PageModel::query()->create([
                    'tenant_id' => tenant('id'),
                    'title' => $data['title'],
                    'slug' => $data['slug'],
                    'layout' => $editor->pageRecord()->layout,
                    'parent_id' => $data['parent_id'] ?? null,
                    'blocks' => [],
                    'status' => PageStatus::Draft,
                ]);

                $editor->redirect(PageResource::getUrl('edit', ['record' => $page]), navigate: true);
            });
    }
}
