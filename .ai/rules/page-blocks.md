---
paths:
  - 'app/Filament/Fabricator/PageBlocks/**'
---

# Page Blocks

## A block class owns content, the theme owns layout
A new block extends the base `Block`, sets a snake_case `$name`, a description, an intent, axes and a sample, and implements `fields()` with content fields only — never `defineBlock()`, which injects the variant, bind and appearance selects. It declares NO variants: `$variants`, `$variantAxes` and `$imagelessVariants` live on the theme (`App\Themes\ThemeBlock`, listed per type in each `App\Themes\Definitions\*`), and `Block::themeBlock()` is the one place the class reads them. A type a theme does not list is absent from that theme's vocabulary (`BlockRegistry::contracts()` filters), which is also how a theme-exclusive block works: an ordinary class in this directory that only one theme names.

## View path mirrors the name, under the theme
A block's views live in `resources/themes/{theme}/blocks/{type}.blade.php`, or `{type}/{variant}.blade.php` for every variant the theme declares, and are resolved by `Theme::blockView()` through the `themes::` view namespace. Their `@props` must be exactly the stored snake_case data keys, and the markup wraps in the shared section shell. A new variant is a theme entry plus a view in that theme's directory — never a static on the class.
