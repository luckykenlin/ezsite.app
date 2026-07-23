<?php

declare(strict_types=1);

namespace App\Providers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Foundation\Vite;
use Illuminate\Support\ServiceProvider;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;

final class FilamentServiceProvider extends ServiceProvider
{
    /**
     * @codeCoverageIgnore
     */
    public function boot(): void
    {
        // Load the tenant site's stylesheet into the <head> of every
        // FilamentFabricator-rendered front-end page, via the plugin's own asset
        // API. Skipped in the console: registerStyles evaluates Vite eagerly at
        // boot and there is no build manifest during artisan commands (tests,
        // `package:discover` in CI, queue workers) — and the front-end is never
        // served from the console anyway, so the stylesheet isn't needed there.
        if (! $this->app->runningInConsole()) {
            FilamentFabricator::registerStyles([resolve(Vite::class)('resources/css/site.css')]);
        }

        Repeater::configureUsing(function (Repeater $repeater): void {
            $repeater->deleteAction(
                fn (Action $action): Action => $action->requiresConfirmation(),
            )
                ->collapsible()
                ->collapsed()
                ->cloneable();
        });

        Table::configureUsing(function (Table $table): void {
            $table->striped()->deferLoading();
        });

        Column::configureUsing(function (Column $column): void {
            $column->toggleable()->translateLabel();
        });

        TextInput::configureUsing(function (TextInput $textInput): void {
            $textInput->maxLength(255);
        });

        Select::configureUsing(function (Select $select): void {
            $select
                ->searchable()
                ->preload()
                ->native(false);
        });

        SelectFilter::configureUsing(function (SelectFilter $selectFilter): void {
            $selectFilter->native(false);
        });

        Action::configureUsing(function (Action $action): void {
            $action->translateLabel();
        });

        CreateAction::configureUsing(function (CreateAction $action): void {
            $action->modalWidth(Width::ExtraLarge)->translateLabel();
        });

        EditAction::configureUsing(function (EditAction $action): void {
            $action->modalWidth(Width::ExtraLarge)->translateLabel();
        });

        Field::configureUsing(function (Field $field): void {
            $field->translateLabel();
        });
    }
}
