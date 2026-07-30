<?php

declare(strict_types=1);

namespace App\Providers;

use App\Design\ThemeVariables;
use App\Site\BindResolver;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;
use Z3d0X\FilamentFabricator\Layouts\Layout;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;
use Z3d0X\FilamentFabricator\View\LayoutRenderHook;

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

        // Fabricator's own service provider SKIPS layout/block registration in
        // console processes (except unit tests) — but the queue worker runs the
        // AI draft pipeline, whose vocabulary, schema and validator all read
        // the registry. Without this, every generated block is judged
        // "unknown type" on the worker. Mirror the package's discovery here.
        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            $this->registerFabricatorComponentsForConsole();
        }

        // Per-tenant theme: emit the tenant's font preloads and design-token
        // CSS variables into the Fabricator front-end <head>. Registered
        // unconditionally (unlike registerStyles above) — the closure is lazy,
        // it only runs when a Fabricator page renders, and Vite::fonts()
        // degrades to '' when no build manifest exists (tests, CI).
        FilamentView::registerRenderHook(LayoutRenderHook::HEAD_END, function (): HtmlString {
            // Reuse the request-scoped BindResolver so theming shares the
            // page render's single memoized business query — and never
            // touch it outside tenancy, where the query would be unscoped.
            $business = tenant() === null ? null : resolve(BindResolver::class)->business();

            if ($business === null) {
                return new HtmlString('');
            }

            return new HtmlString(
                resolve(Vite::class)->fonts($business->design_tokens->fontPair->viteAliases())
                .ThemeVariables::style($business)->toHtml(),
            );
        });

        // The builder's Alpine components ship as real modules so the
        // eslint/tsc gates cover them, emitted BEFORE Filament's own scripts
        // so their `alpine:init` listeners are in place by the time Alpine
        // boots.
        //
        // The two stylesheets ride along for a different reason: they are only
        // safe panel-wide because every selector in them is `.pe-`/`.pc-`
        // prefixed, and scoping them would mean a page-scoped render hook, which
        // the arch test forbids for exactly the reason below.
        //
        // Deliberately NOT scoped to the builder pages. `alpine:init` fires
        // once, on the first full page load; the panel navigates with
        // wire:navigate, which swaps the body and calls Alpine.initTree()
        // without firing that event again. A page-scoped module therefore
        // arrives too late to ever register, and every x-data on it dies with
        // "pageCanvas is not defined". Loading them panel-wide costs a couple
        // of gzipped kilobytes and makes the entry path irrelevant.
        FilamentView::registerRenderHook(
            PanelsRenderHook::SCRIPTS_BEFORE,
            fn (): HtmlString => new HtmlString(Blade::render(<<<'BLADE'
                @vite([
                    'resources/js/page-editor/editor.ts',
                    'resources/js/page-canvas/canvas.ts',
                    'resources/css/page-editor.css',
                    'resources/css/page-canvas.css',
                ])
            BLADE)),
        );

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

    /**
     * Console-only glue, unreachable under the test runner (there the package
     * itself registers via its runningUnitTests exception).
     *
     * @codeCoverageIgnore
     */
    private function registerFabricatorComponentsForConsole(): void
    {
        foreach (glob(app_path('Filament/Fabricator/Layouts/*.php')) ?: [] as $file) {
            $class = 'App\\Filament\\Fabricator\\Layouts\\'.basename($file, '.php');

            if (is_subclass_of($class, Layout::class)) {
                FilamentFabricator::registerLayout($class);
            }
        }

        foreach (glob(app_path('Filament/Fabricator/PageBlocks/*.php')) ?: [] as $file) {
            $class = 'App\\Filament\\Fabricator\\PageBlocks\\'.basename($file, '.php');

            if (is_subclass_of($class, PageBlock::class) && ! new ReflectionClass($class)->isAbstract()) {
                FilamentFabricator::registerPageBlock($class);
            }
        }
    }
}
