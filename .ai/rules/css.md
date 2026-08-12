---
paths:
  - 'resources/css/**'
---

# Css

## CSS `inherit` does not cross a `<details>` boundary in Chrome
Chrome renders a `::details-content` box between a `<details>` and its content, so a non-inherited property set to `inherit` (e.g. `background-color`) on content inside a drawer reads that pseudo-element's initial value — transparent — not the `<details>`. This is why the mobile nav sheet once rendered see-through. Repair the chain with a standalone `.x::details-content { background-color: inherit; }` rule (standalone, because browsers without the pseudo drop the whole selector list). Regular inherited properties like `color` are unaffected.
