---
paths:
  - 'app/Themes/Definitions/*/Pages/**'
---

# Theme Pages

## Theme pages author in validator INPUT shape and stay trade-agnostic
A theme page (one of a theme's designed starting layouts behind the "Add a page" picker, returned as a `ThemePage` by a class under `app/Themes/Definitions/{Theme}/Pages/`) mirrors the template Definitions authoring shape: flat `variant`/`tone`/`spacing` siblings of `type`/`data`, unprefixed `image_query` inside `data`. It personalizes only through the five profile tokens `{business_name}/{tagline}/{city}/{phone}/{email}` — no TemplateField extras, no demo profile — and its copy must be trade-agnostic with obvious replace-me placeholders (never invent a person, a quote, or a number). A VERTICAL theme (one trade, e.g. `NailLuxe`) may write trade-specific copy, since every site it draws is that trade — the no-invented-facts rule still holds. Every theme page flows through `BuildThemePageBlocks` (fill → `SiteDraftValidator::blocks()` → `StampSchemeDefaults::fill()`); `ThemePageTest` fails on a dropped block or a surviving `{brace}`. A theme page may only use block types and variants its own theme declares; the stock-photo job only fills `image_query` blocks. A `draftSlug` also puts the page on the AI site draft's closed slug menu, so set one only for a page the draft should be allowed to write. "Blank" is not a theme page — it is the picker's fixed first card.
