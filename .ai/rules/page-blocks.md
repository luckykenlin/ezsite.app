---
paths:
  - 'app/Filament/Fabricator/PageBlocks/**'
---

# Page Blocks

## Block class + variant view naming contract
A new block extends the base `Block`, sets a snake_case `$name`, a description, an intent, axes and a sample, and implements `fields()` with content fields only — never `defineBlock()`, which injects the variant, bind and appearance selects. Its view path mirrors the name (`page-blocks/{type}.blade.php`, or `{type}/{variant}.blade.php` when variants are declared), its `@props` must be exactly the stored snake_case data keys, and its markup wraps in the shared section shell.
