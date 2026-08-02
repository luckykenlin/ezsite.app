<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Pages;

use App\Actions\SaveSiteCapture;
use App\Enums\LeadFieldSet;
use App\Enums\PopupTrigger;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Editor for the two site-wide capture surfaces: the offer popup and the
 * sticky mobile call bar.
 *
 * Neither is a page block, and that is the point — a block is a section in a
 * page's vertical rhythm, and these float above every page instead. They share
 * a screen because they are one decision ("how hard do I chase a visitor who
 * hasn't contacted me?") and one stored column.
 *
 * @property-read Schema $form
 */
final class CaptureSettings extends Page
{
    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    protected string $view = 'filament.tenant.pages.capture-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $title = 'Popup & call bar';

    /**
     * Right after the Leads inbox: this page is where an operator goes when
     * the inbox is emptier than they want.
     */
    protected static ?int $navigationSort = 2;

    public function mount(): void
    {
        // `->` not `?->`: `??` has isset() semantics, so it absorbs a missing
        // row without reading the property at all.
        $capture = SiteSetting::query()->first()->capture ?? [];

        $popup = is_array($capture['popup'] ?? null) ? $capture['popup'] : [];
        $callBar = is_array($capture['call_bar'] ?? null) ? $capture['call_bar'] : [];

        $this->form->fill([
            'popup' => $popup,
            'call_bar' => $callBar,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Offer popup')
                    ->description('A small window offering something in return for an email or a phone number. It only works if it gives the visitor a reason — a discount, a quote, a callback.')
                    ->schema([
                        Toggle::make('popup.enabled')
                            ->label('Show the popup')
                            ->live(),
                        Toggle::make('popup.follow_offer')
                            ->label('Use my latest offer')
                            ->helperText('When an offer update is running, the popup shows that instead of the wording below — and goes back to it the moment the offer ends. Nothing to remember to switch off.')
                            ->visible(fn (Get $get): bool => $get('popup.enabled') === true),
                        TextInput::make('popup.heading')
                            ->label('Heading')
                            ->maxLength(120)
                            ->required(fn (Get $get): bool => $get('popup.enabled') === true)
                            ->visible(fn (Get $get): bool => $get('popup.enabled') === true),
                        Textarea::make('popup.offer')
                            ->label('What they get')
                            ->rows(2)
                            ->maxLength(300)
                            ->visible(fn (Get $get): bool => $get('popup.enabled') === true),
                        Select::make('popup.fields')
                            ->label('Ask for')
                            ->options(LeadFieldSet::options())
                            ->default(LeadFieldSet::Email->value)
                            ->selectablePlaceholder(false)
                            ->helperText('One field converts best in a popup. Anything longer belongs on the page.')
                            ->visible(fn (Get $get): bool => $get('popup.enabled') === true),
                        TextInput::make('popup.button_label')
                            ->label('Button')
                            ->maxLength(60)
                            ->placeholder('Get it')
                            ->visible(fn (Get $get): bool => $get('popup.enabled') === true),
                        TextInput::make('popup.success_message')
                            ->label('Thank-you message')
                            ->maxLength(200)
                            ->visible(fn (Get $get): bool => $get('popup.enabled') === true),
                        TextInput::make('popup.fine_print')
                            ->label('Fine print')
                            ->maxLength(120)
                            ->placeholder('No spam. Unsubscribe anytime.')
                            ->visible(fn (Get $get): bool => $get('popup.enabled') === true),
                        Select::make('popup.trigger')
                            ->label('Show it')
                            ->options(PopupTrigger::options())
                            ->default(PopupTrigger::Delay->value)
                            ->selectablePlaceholder(false)
                            ->live()
                            ->visible(fn (Get $get): bool => $get('popup.enabled') === true),
                        TextInput::make('popup.trigger_value')
                            ->label('After')
                            ->numeric()
                            ->suffix(fn (Get $get): string => $this->trigger($get)->valueLabel())
                            ->placeholder(fn (Get $get): string => (string) $this->trigger($get)->defaultValue())
                            ->visible(fn (Get $get): bool => $get('popup.enabled') === true && $this->trigger($get)->takesValue()),
                        TextInput::make('popup.frequency_days')
                            ->label('Then leave them alone for')
                            ->numeric()
                            ->suffix('days')
                            ->placeholder('7')
                            ->helperText('A visitor who has seen it — or already got in touch — will not see it again until this many days have passed. 0 shows it every visit.')
                            ->visible(fn (Get $get): bool => $get('popup.enabled') === true),
                    ]),
                Section::make('Mobile call bar')
                    ->description('A button fixed to the bottom of the screen on phones, dialling the number from your business profile. Most people who find a local business on a phone would rather call than fill in a form.')
                    ->schema([
                        Toggle::make('call_bar.enabled')
                            ->label('Show the call bar')
                            ->live(),
                        TextInput::make('call_bar.label')
                            ->label('Button')
                            ->maxLength(40)
                            ->placeholder('Call now')
                            ->visible(fn (Get $get): bool => $get('call_bar.enabled') === true),
                        Toggle::make('call_bar.show_popup_button')
                            ->label('Also offer the popup form')
                            ->helperText('Adds a second button for visitors who would rather write than call. Needs the popup switched on.')
                            ->visible(fn (Get $get): bool => $get('call_bar.enabled') === true),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        resolve(SaveSiteCapture::class)->handle(
            $this->group($data, 'popup'),
            $this->group($data, 'call_bar'),
        );

        Notification::make()
            ->title('Capture settings saved')
            ->success()
            ->send();
    }

    /**
     * One of the form's two nested groups, in the string-keyed shape
     * {@see SaveSiteCapture} takes.
     *
     * Filament's state is always string-keyed here, but `getState()` is typed
     * loosely enough that the guarantee has to be made rather than assumed —
     * and the group is missing entirely on a form that was never filled.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function group(array $data, string $key): array
    {
        $group = is_array($data[$key] ?? null) ? $data[$key] : [];

        $fields = [];

        foreach ($group as $field => $value) {
            $fields[(string) $field] = $value;
        }

        return $fields;
    }

    /**
     * The trigger currently selected in the form, for the fields that describe
     * it. Falls back to the default rather than throwing — the select is
     * `live()`, so this runs mid-edit with whatever is in state.
     */
    private function trigger(Get $get): PopupTrigger
    {
        $value = $get('popup.trigger');

        return (is_string($value) ? PopupTrigger::tryFrom($value) : null) ?? PopupTrigger::Delay;
    }
}
