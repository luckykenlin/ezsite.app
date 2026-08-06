<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Actions;

use App\Filament\Fabricator\Fields\ImageInput;
use App\Filament\Fabricator\Fields\SeoFields;
use App\Filament\Tenant\Resources\PageResource\Pages\PageEditor;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;

/**
 * The page metadata modal (title / slug / layout / parent / SEO) — the stock
 * EditPage the visual editor replaces carried these in its sidebar. Metadata
 * persists on modal submit, independently of the blocks draft.
 */
final readonly class PageSettingsAction
{
    public static function make(PageEditor $editor): Action
    {
        $fields = new PageIdentityFields($editor->pageRecord());

        return Action::make('pageSettings')
            ->label('Page settings')
            ->color('gray')
            ->fillForm(fn (): array => [
                'title' => $editor->pageRecord()->title,
                'slug' => $editor->pageRecord()->slug,
                'layout' => $editor->pageRecord()->layout,
                'parent_id' => $editor->pageRecord()->parent_id,
                'seo_title' => $editor->pageRecord()->seo_title,
                'seo_description' => $editor->pageRecord()->seo_description,
                'seo_image_media_id' => $editor->pageRecord()->seo_image_media_id,
                'is_indexable' => $editor->pageRecord()->is_indexable,
            ])
            ->schema([
                TextInput::make('title')
                    ->required(),
                $fields->slug(),
                Select::make('layout')
                    ->options(fn (): array => array_map(
                        static fn (mixed $label): string => is_string($label) ? $label : '',
                        FilamentFabricator::getLayouts(),
                    ))
                    ->required(),
                $fields->parent(),
                self::seoSection($editor),
            ])
            ->action(function (array $data) use ($editor): void {
                $editor->updatePageSettings($data);

                Notification::make()
                    ->title('Page settings saved')
                    ->success()
                    ->send();
            });
    }

    /**
     * How the page shows up in search results and link previews. Every field
     * is optional: left empty, {@see \App\Actions\BuildPageSeoData} derives the
     * value from the page title and the Business profile, so the placeholders
     * show what visitors get today.
     */
    private static function seoSection(PageEditor $editor): Section
    {
        return Section::make(SeoFields::SECTION_HEADING)
            ->description('How this page looks on Google and when its link is shared.')
            ->collapsed()
            ->schema([
                SeoFields::title()
                    ->placeholder(fn (): string => $editor->pageRecord()->title)
                    ->helperText('Your business name is appended automatically.'),
                SeoFields::description()
                    ->placeholder(fn (): ?string => $editor->business()?->tagline),
                ImageInput::make('seo_image_media_id')
                    ->label('Share image')
                    ->helperText('Shown when the link is posted on social media. Defaults to your logo.'),
                SeoFields::indexable()
                    ->helperText('Turn off for thank-you or campaign-only pages.'),
            ]);
    }
}
