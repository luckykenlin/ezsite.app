---
paths:
  - 'app/Themes/**'
---

# Themes

## The theme owns a site's look; never add design globally
A `Theme` (enum index + one `Definitions\*` class per case, mirroring SiteTemplate) decides which block types a site may use, which layout variants each has (`ThemeBlock`), the views that draw them (`resources/themes/{slug}/blocks/**`, resolved by `Theme::blockView()` via the `themes::` namespace) and the stylesheet that paints them (`resources/themes/{slug}/theme.css`, one Vite entry per theme, emitted by the Fabricator HEAD_END hook). A theme also owns its page set (`ThemeDefinition::$pages`, the "Add a page" picker's designed pages) and, through each page's `draftSlug`, the closed menu of supporting pages the AI site draft may produce (`draftSlugs()`). Read the theme through `CurrentTheme` (not scoped, not memoized — it re-reads `tenant()` every call because nothing flushes scoped instances across `RunInTenant`). Do not add a global variant, view directory or stylesheet; add it to a theme. `App\Themes` must not import `App\Filament` (LayeringTest). The `Default` theme is a verbatim wrap of the pre-theme product and is not a place to design.
