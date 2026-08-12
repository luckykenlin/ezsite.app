---
paths:
  - 'app/Templates/Definitions/**'
---

# Definitions

## New template touches five registries and two artifacts
A new Definitions class is not live until: (1) SiteTemplate gets the case AND the definition() match arm; (2) lang/en + lang/zh marketing.php get label/description/exactly-3 highlights/per-field labels (flattened key parity is arch-tested); (3) the case joins $footerFood or $footerServices in components/central/layout.blade.php; (4) `php artisan demo:seed {slug}` builds the demo tenant; (5) `CENTRAL_URL=https://ezsite.test node --use-system-ca scripts/capture-template-screenshots.mjs {slug}` produces the committed -800.webp/-780.webp/-1440.jpg trio (CentralSiteTest asserts they exist) — seed BEFORE capturing, and eyeball every capture. Also: never use a plausible industry slug (sushi-bar…) as the "unknown template" fixture in tests — two tests broke the day it became real; use tattoo-parlor.

## Brand hexes are inert unless the template overrides palette to Brand
TemplateDefinition's brandPrimary/Secondary/Accent do NOT tint the public site by themselves — the preset's ColorPalette wins, and the hexes only reach --color-primary/--color-accent when the definition also passes `palette: ColorPalette::Brand` (which swaps the base ramp to plain white/grey). DimSumHouse does this to get lacquer red + gold; ChineseRestaurant deliberately does not (its hexes are stored for mail identity/AI only, WarmSand paints the site). Check the emitted `color-primary:` in the demo HTML before trusting a screenshot.
