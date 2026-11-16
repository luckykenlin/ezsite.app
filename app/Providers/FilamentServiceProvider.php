<?php

declare(strict_types=1);

namespace App\Providers;

use App\Design\ThemeVariables;
use App\Site\BindResolver;
use App\Site\Favicon;
use App\Themes\CurrentTheme;
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
        // The site's only script: progressive enhancement for the capture
        // forms and the offer popup, shared by every theme. Skipped in the
        // console: registerScripts evaluates Vite eagerly at boot and there is
        // no build manifest during artisan commands (tests, `package:discover`
        // in CI, queue workers) — and the front-end is never served from the
        // console anyway. Everything it does degrades to plain HTML, and it
        // bails out inside the editor canvas (see resources/js/site.ts).
        //
        // The STYLESHEET is not registered here: it is the theme's, and the
        // theme is the tenant's, which boot cannot know. It is emitted by the
        // HEAD_END hook below, which runs per render.
        if (! $this->app->runningInConsole()) {
            FilamentFabricator::registerScripts([resolve(Vite::class)('resources/js/site.ts')]);
        }

        // Fabricator's own service provider SKIPS layout/block registration in
        // console processes (except unit tests) — but the queue worker runs the
        // AI draft pipeline, whose vocabulary, schema and validator all read
        // the registry. Without this, every generated block is judged
        // "unknown type" on the worker. Mirror the package's discovery here.
        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            $this->registerFabricatorComponentsForConsole();
        }

        // Per-tenant theme and identity, emitted into the Fabricator front-end
        // <head>: the current theme's stylesheet, then — when the tenant has a
        // Business — its font preloads, its design-token CSS variables and its
        // favicon. This hook rather than the base-layout override, which is a
        // deliberate verbatim copy of the package's and stays that way.
        // Registered unconditionally (unlike registerScripts above) — the
        // closure is lazy, it only runs when a Fabricator page renders.
        FilamentView::registerRenderHook(LayoutRenderHook::HEAD_END, function (): HtmlString {
            // The stylesheet is skipped in a console process (a queue worker
            // rendering a page), where there is no Vite manifest to read —
            // the guard registerScripts above has always had. Unit tests run
            // in the console but fake Vite, so they keep the tag.
            $stylesheet = $this->app->runningInConsole() && ! $this->app->runningUnitTests() ? '' : resolve(Vite::class)([resolve(CurrentTheme::class)->definition()->stylesheet])->toHtml();

            // Reuse the request-scoped BindResolver so theming shares the
            // page render's single memoized business query — and never
            // touch it outside tenancy, where the query would be unscoped.
            $business = tenant() === null ? null : resolve(BindResolver::class)->business();

            if ($business === null) {
                return new HtmlString($stylesheet);
            }

            return new HtmlString(
                $stylesheet
                .resolve(Vite::class)->fonts($business->design_tokens->fontPair->viteAliases())
                .ThemeVariables::style($business)->toHtml()
                .Favicon::links($business)->toHtml(),
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
