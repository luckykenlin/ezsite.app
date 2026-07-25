<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Jobs\GenerateSiteDraftJob;
use App\Models\Business;
use App\Models\Tenant;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use RuntimeException;

/**
 * Singleton settings page for the tenant's Business (unique tenant_id — a
 * list resource would only ever hold one row). First save creates the row,
 * later saves update it.
 *
 * @property-read Schema $form
 */
final class BusinessProfile extends Page
{
    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    protected string $view = 'filament.tenant.pages.business-profile';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $title = 'Business profile';

    public function mount(): void
    {
        $business = Business::query()->first();

        if ($business === null) {
            // No-argument fill applies the field defaults (e.g. status =
            // draft); filling an empty array would bypass them and submit
            // nulls into NOT NULL columns.
            $this->form->fill();

            return;
        }

        $attributes = $business->attributesToArray();

        // Not a form field, and Livewire can't hydrate the value object.
        unset($attributes['design_tokens']);

        $this->form->fill($attributes);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Identity')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(1),
                            TextInput::make('category')
                                ->maxLength(255)
                                ->columnSpan(1),
                            TextInput::make('tagline')
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Textarea::make('description')
                                ->rows(4)
                                ->columnSpanFull(),
                            FileUpload::make('logo_path')
                                ->label('Logo')
                                ->image()
                                ->directory('logos')
                                ->visibility('public')
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Brand colors')
                    ->schema([
                        Grid::make(3)->schema([
                            ColorPicker::make('brand_primary')
                                ->columnSpan(1),
                            ColorPicker::make('brand_secondary')
                                ->columnSpan(1),
                            ColorPicker::make('brand_accent')
                                ->columnSpan(1),
                        ]),
                    ]),
                Section::make('Contact & locale')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('contact_email')
                                ->email()
                                ->maxLength(255)
                                ->columnSpan(1),
                            TextInput::make('contact_phone')
                                ->maxLength(255)
                                ->columnSpan(1),
                            TextInput::make('website_url')
                                ->url()
                                ->maxLength(2048)
                                ->columnSpanFull(),
                            Select::make('timezone')
                                ->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                                ->searchable()
                                ->columnSpan(1),
                            TextInput::make('locale')
                                ->maxLength(10)
                                ->helperText('e.g. en, zh_TW')
                                ->columnSpan(1),
                            TextInput::make('currency')
                                ->length(3)
                                ->helperText('ISO 4217, e.g. USD')
                                ->columnSpan(1),
                            Select::make('status')
                                ->options([
                                    'draft' => 'Draft',
                                    'active' => 'Active',
                                    'archived' => 'Archived',
                                ])
                                ->default('draft')
                                ->selectablePlaceholder(false)
                                ->columnSpan(1),
                        ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $business = Business::query()->first();

        if ($business === null) {
            Business::query()->create([...$data, 'tenant_id' => tenant('id')]);
        } else {
            $business->update($data);
        }

        Notification::make()
            ->title('Business profile saved')
            ->success()
            ->send();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateSiteDraft')
                ->label('Generate site draft')
                ->icon(Heroicon::OutlinedSparkles)
                ->requiresConfirmation()
                ->modalDescription('AI will compose a home page draft from this profile. An existing draft will be replaced; a published home page is never touched.')
                ->disabled(fn (): bool => ! $this->profileReadyForGeneration())
                ->tooltip(fn (): ?string => $this->profileReadyForGeneration()
                    ? null
                    : 'Fill in the name, category and description first.')
                ->action(function (): void {
                    $tenant = tenant();
                    $user = auth()->user();

                    throw_unless($tenant instanceof Tenant && $user instanceof User, RuntimeException::class, 'Tenant panel context missing.');

                    dispatch(new GenerateSiteDraftJob($tenant->id, $user->id));

                    Notification::make()
                        ->title('Generation queued')
                        ->body("You'll be notified when the draft is ready.")
                        ->info()
                        ->send();
                }),
        ];
    }

    private function profileReadyForGeneration(): bool
    {
        $business = Business::query()->first();

        return $business !== null
            && filled($business->name)
            && filled($business->category)
            && filled($business->description);
    }
}
