<?php

declare(strict_types=1);

namespace App\Site\Blocks;

/**
 * The registry of every per-section layout axis — the one list that tool,
 * validator, inspector and preset code iterate instead of naming tone,
 * spacing and the five parametric axes one by one.
 *
 * Each case's VALUE is also the axis's storage key inside `data.appearance`
 * — this enum is the single source of those key names ({@see BlockShape}'s
 * TONE_KEY/SPACING_KEY remain for existing call sites and are pinned equal
 * by test). Declaration order is load-bearing: {@see SectionLayout::store()}
 * emits keys in this order so repeated edits never reorder the stored JSON
 * and manufacture a revision (`pages.blocks` is `json`, and
 * {@see \App\Actions\Pages\RecordPageRevision} compares with `===`).
 */
enum LayoutAxis: string
{
    case Tone = 'tone';

    case Spacing = 'spacing';

    case Width = 'width';

    case Align = 'align';

    case Columns = 'columns';

    case ItemStyle = 'item_style';

    case ImageShape = 'image_shape';

    /**
     * The five parametric axes this feature added — tone and spacing keep
     * their dedicated flows everywhere they already exist.
     *
     * @return list<self>
     */
    public static function extended(): array
    {
        return [self::Width, self::Align, self::Columns, self::ItemStyle, self::ImageShape];
    }

    /**
     * The value enum behind this axis.
     *
     * @return class-string<SectionAlign|SectionColumns|SectionImageShape|SectionItemStyle|SectionSpacing|SectionTone|SectionWidth>
     */
    public function enumClass(): string
    {
        return match ($this) {
            self::Tone => SectionTone::class,
            self::Spacing => SectionSpacing::class,
            self::Width => SectionWidth::class,
            self::Align => SectionAlign::class,
            self::Columns => SectionColumns::class,
            self::ItemStyle => SectionItemStyle::class,
            self::ImageShape => SectionImageShape::class,
        };
    }

    /**
     * The axis's own Select label — its VALUES' labels live on the value enum.
     */
    public function label(): string
    {
        return match ($this) {
            self::Tone => 'Background',
            self::Spacing => 'Vertical space',
            self::Width => 'Content width',
            self::Align => 'Header alignment',
            self::Columns => 'Columns',
            self::ItemStyle => 'Item style',
            self::ImageShape => 'Image shape',
        };
    }

    /**
     * The stored value's case for this axis, or null — the tryFrom half of
     * the safety boundary, spelled once.
     */
    public function resolve(mixed $value): SectionAlign|SectionColumns|SectionImageShape|SectionItemStyle|SectionSpacing|SectionTone|SectionWidth|null
    {
        return is_string($value) ? $this->enumClass()::tryFrom($value) : null;
    }

    /**
     * @return list<string>
     */
    public function values(): array
    {
        return $this->enumClass()::values();
    }
}
