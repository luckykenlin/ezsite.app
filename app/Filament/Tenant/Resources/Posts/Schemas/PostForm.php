<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\Posts\Schemas;

use App\Enums\PostCtaAction;
use App\Enums\PostKind;
use App\Filament\Fabricator\Fields\ImageInput;
use App\Filament\Fabricator\Fields\LinkInput;
use App\Filament\Fabricator\Fields\SeoFields;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * The update composer.
 *
 * The PHOTO IS THE FIRST FIELD, and that is the one deliberate thing about the
 * order. The unit of content for a nail salon or a takeaway is a photograph —
 * they already took it, it is already on their phone — and a form that opens with
 * "Title" asks them to be a writer before it asks them to be themselves.
 *
 * Sectioned rather than one long column because two of the five sections are
 * conditional: the dates only exist for kinds that need them, and the offer
 * fields only for an offer. Eighteen fields in a modal was the alternative, which
 * is why this resource now has real create and edit pages.
 */
final class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('What you are announcing')
                    ->schema([
                        ImageInput::make('cover_media_id')
                            ->label('Photo')
                            ->helperText('The one thing a visitor actually looks at. Pick from your library or upload from your phone.'),
                        Select::make('kind')
                            ->label('Kind')
                            ->options(PostKind::class)
                            ->default(PostKind::Update)
                            ->required()
                            ->live()
                            ->helperText(fn (Get $get): string => self::kind($get)?->hint() ?? ''),
                        TextInput::make('title')
                            ->required()
                            // 58 is where Google truncates a local post's title,
                            // and it is a good discipline for a card anyway.
                            ->maxLength(58)
                            ->helperText('Short. It has to fit on a card and in a search result.'),
                        Textarea::make('excerpt')
                            ->label('One line')
                            ->rows(2)
                            ->maxLength(300)
                            ->helperText('The sentence that shows on the card, in the share preview and in search results.'),
                        Textarea::make('body')
                            ->label('The rest, if there is more')
                            ->rows(8)
                            ->helperText('Plain text. Leave a blank line between paragraphs.'),
                    ]),

                Section::make('When it runs')
                    ->schema([
                        DateTimePicker::make('starts_at')
                            ->label('From')
                            ->seconds(false)
                            ->required(),
                        DateTimePicker::make('ends_at')
                            ->label('Until')
                            ->seconds(false)
                            ->required()
                            ->after('starts_at')
                            ->helperText('After this it stops showing on your site, but its own page stays up — anyone who already has the link still sees it.'),
                    ])
                    ->columns(2)
                    // Offer AND Event: Google requires the date range for both, and
                    // an offer with no end is a price rather than an offer.
                    ->visible(fn (Get $get): bool => self::kind($get)?->requiresDateRange() ?? false),

                Section::make('The offer')
                    ->schema([
                        TextInput::make('offer_coupon_code')
                            ->label('Code')
                            ->maxLength(60)
                            ->helperText('Leave empty if there is nothing to quote.'),
                        Textarea::make('offer_terms')
                            ->label('Small print')
                            ->rows(2),
                    ])
                    ->visible(fn (Get $get): bool => self::kind($get)?->isOffer() ?? false),

                Section::make('The button')
                    ->schema([
                        Select::make('cta_action')
                            ->label('Button')
                            ->options(PostCtaAction::class)
                            ->live()
                            ->helperText('An announcement with nothing to click is just an announcement.'),
                        LinkInput::make('cta_url')
                            ->label('Where it goes')
                            // Call uses the business phone number, and Google
                            // rejects a CALL action that carries a url at all.
                            ->visible(fn (Get $get): bool => self::needsCtaUrl($get))
                            ->required(fn (Get $get): bool => self::needsCtaUrl($get)),
                    ]),

                Section::make(SeoFields::SECTION_HEADING)
                    ->schema([
                        TextInput::make('author_name')
                            ->label('Signed by')
                            ->maxLength(120)
                            ->placeholder('Mei, owner')
                            ->helperText('Optional. A name makes an update read like a person wrote it.'),
                        SeoFields::title(),
                        SeoFields::description(),
                        SeoFields::indexable()
                            ->default(true),
                    ])
                    ->collapsed(),
            ]);
    }

    /**
     * The kind currently selected in the form.
     *
     * Form state is `mixed`, but a Select given `->options(PostKind::class)` on an
     * enum-cast attribute always holds the ENUM — both when hydrated from a record
     * and after the browser picks a new one (verified: the coverage gate showed a
     * string branch here was never once reached). So the narrowing is an instanceof
     * check and nothing else; anything unexpected degrades to null, which hides the
     * conditional sections rather than showing the wrong ones.
     */
    private static function kind(Get $get): ?PostKind
    {
        $value = $get('kind');

        return $value instanceof PostKind ? $value : null;
    }

    /**
     * Whether the chosen button needs somewhere to go. False for `Call`, which uses
     * the business's own phone number — Google rejects a CALL action that carries a
     * url at all. Same narrowing as {@see kind()}.
     */
    private static function needsCtaUrl(Get $get): bool
    {
        $value = $get('cta_action');

        return $value instanceof PostCtaAction && $value->requiresUrl();
    }
}
