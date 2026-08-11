<?php

declare(strict_types=1);

namespace App\Design;

use Illuminate\Support\Str;

/**
 * Curated heading/body font pairings. All families are self-hosted at build
 * time via the vite `bunny()` plugin (see vite.config.js); per-tenant
 * rendering emits `Vite::fonts()` preloads filtered to the chosen pair.
 *
 * Two rules decide what is allowed in here, and both are why the previous set
 * was replaced wholesale:
 *
 *  1. **The pair must carry a voice.** Every case sets its heading in a face
 *     drawn for display and its body in one drawn for reading, and the two are
 *     never the same family unless the family ships cuts far enough apart to
 *     read as two (Instrument Serif over Instrument Sans). The old default set
 *     Instrument Sans in both roles, so the site most tenants never restyle had
 *     no typographic contrast at all.
 *  2. **The face must not be one of the six every generator ships.** Playfair
 *     Display, Fraunces, Cormorant Garamond, Space Grotesk, Nunito and Inter
 *     are what an AI reaches for by default, and a visitor who has seen three
 *     generated sites has seen all of them. None of them appear below.
 *
 * The cases are named for the VOICE rather than for the family, so a face can
 * be replaced without a data migration — only the key is persisted.
 */
enum FontPair: string
{
    case Signage = 'signage';
    case WarmEditorial = 'warm-editorial';
    case HighContrast = 'high-contrast';
    case NeoGrotesque = 'neo-grotesque';
    case Contemporary = 'contemporary';
    case Soft = 'soft';
    case QuietSerif = 'quiet-serif';

    public function headingFamily(): string
    {
        return match ($this) {
            // A true poster grotesque — the lettering above a takeaway
            // counter, which is exactly the register a burger or pizza shop
            // is already using on its own shopfront.
            self::Signage => 'Archivo Black',
            self::WarmEditorial => 'Newsreader',
            // A didone: hairline thins against heavy stems, which is the
            // vocabulary of every salon and spa that has ever printed a price
            // list.
            self::HighContrast => 'Bodoni Moda',
            self::NeoGrotesque => 'Schibsted Grotesk',
            self::Contemporary => 'Bricolage Grotesque',
            self::Soft => 'Gabarito',
            self::QuietSerif => 'Instrument Serif',
        };
    }

    public function bodyFamily(): string
    {
        return match ($this) {
            self::Signage => 'Archivo',
            self::WarmEditorial => 'Figtree',
            self::HighContrast => 'Karla',
            self::NeoGrotesque, self::Contemporary => 'Public Sans',
            self::Soft => 'Onest',
            self::QuietSerif => 'Instrument Sans',
        };
    }

    /**
     * Whether this pair's HEADING face is drawn finely enough to be set at a
     * weight under 500 — the pairing rule {@see TypeStyle::Serene} depends on.
     *
     * A missing weight snaps to the nearest bundled one, which usually just
     * softens a style. For a light display face it does the opposite: the whole
     * point of a hairline serif at 6rem is the hairlines, and snapping 300 to
     * 500 replaces the look with a slightly-too-small version of a different
     * one. So this is a property of the FACE, not only of what is bundled.
     *
     * Newsreader carries it because it is a high-contrast text serif that ships
     * a real 300 and holds its shape at display size. Bodoni Moda is the other
     * fine-drawn face here and deliberately does NOT: its lightest cut is 400,
     * so a 300 would snap, which is the exact failure this guards.
     */
    public function supportsLightDisplay(): bool
    {
        return $this === self::WarmEditorial;
    }

    public function headingStack(): string
    {
        $fallback = match ($this) {
            self::WarmEditorial, self::HighContrast, self::QuietSerif => 'ui-serif, Georgia, serif',
            default => 'ui-sans-serif, system-ui, sans-serif',
        };

        return sprintf("'%s', %s", $this->headingFamily(), $fallback);
    }

    public function bodyStack(): string
    {
        return sprintf("'%s', ui-sans-serif, system-ui, sans-serif", $this->bodyFamily());
    }

    /**
     * The aliases to pass to `Vite::fonts()`. The vite fonts plugin slugs
     * each `bunny()` family name into the manifest (e.g. "Bodoni Moda"
     * → "bodoni-moda"), so these must be the slugged forms.
     *
     * @return list<string>
     */
    public function viteAliases(): array
    {
        return array_values(array_unique(array_map(
            fn (string $family): string => Str::slug($family),
            [$this->headingFamily(), $this->bodyFamily()],
        )));
    }
}
