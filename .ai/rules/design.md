---
paths:
  - app/Design/StylePreset.php
---

# Design

## Preset appearance maps list axes in LayoutAxis order
Write each `blockAppearanceDefaults()` entry with its axes in `App\Site\Blocks\LayoutAxis` declaration order (tone, spacing, width, align, columns, item_style, image_shape). `SectionLayout::store()` re-emits stored axes in that order, so an entry written out of order round-trips into a different array than the one declared here — and `StampPresetDefaultsTest` compares with `toBe()`, which is order-sensitive. The failure reads as a mysterious "two arrays are identical" diff with the same keys.
