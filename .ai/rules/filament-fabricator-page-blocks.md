---
paths:
  - 'resources/views/components/filament-fabricator/page-blocks/**'
---

# Filament Fabricator Page Blocks

## Type and layout through site-* classes, not utilities
Type through the shared `.site-*` classes and take widths, columns, tone and spacing from the resolved layout axes; never paste a typography or layout utility literal into a block view. Add a new design dimension as a token enum plus a CSS variable so the tenant's tokens can reach it.

## Chrome is the .site-* layer, not DaisyUI's components
Never emit DaisyUI component classes (`btn`, `card`, `card-body`, `navbar`, `footer`, `link`, `badge`, `table`, `input`) from a block view. The plugin is kept only for its `--color-*` / `--radius-*` variables and native form-control styling; the silhouette is ours, in `resources/css/site.css` — `.site-btn`, `.site-card`, `.site-nav`, `.site-footer-*`, `.site-field`, `.site-hours`, `.site-menu-*`. That is what stopped every generated site being legible at a glance as the same site. Button and card chrome are chosen by SectionTone/SectionItemStyle, never by the view; the arch test bans the axis-owned strings.
