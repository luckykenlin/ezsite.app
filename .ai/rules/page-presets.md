---
paths:
  - 'app/Templates/PagePresets/**'
---

# Page Presets

## Page presets author in validator INPUT shape and stay trade-agnostic
A page preset (the "Add a page" picker's starting layouts) mirrors the template Definitions authoring shape: flat `variant`/`tone`/`spacing` siblings of `type`/`data`, unprefixed `image_query` inside `data`. It personalizes only through the five profile tokens `{business_name}/{tagline}/{city}/{phone}/{email}` — no TemplateField extras, no demo profile — and its copy must be trade-agnostic with obvious replace-me placeholders (never invent a person, a quote, or a number). Every preset flows through `BuildPresetPageBlocks` (fill → `SiteDraftValidator::blocks()` → `StampPresetDefaults::fill()`); `PagePresetTest` fails on a dropped block or a surviving `{brace}`. Blocks needing real image URLs to render (logos) don't belong in a preset — the stock-photo job only fills `image_query` blocks.
