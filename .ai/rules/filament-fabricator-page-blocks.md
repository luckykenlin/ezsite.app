---
paths:
  - 'resources/views/components/filament-fabricator/page-blocks/**'
---

# Filament Fabricator Page Blocks

## Type and layout through site-* classes, not utilities
Type through the shared `.site-*` classes and take widths, columns, tone and spacing from the resolved layout axes; never paste a typography or layout utility literal into a block view. Add a new design dimension as a token enum plus a CSS variable so the tenant's tokens can reach it.
