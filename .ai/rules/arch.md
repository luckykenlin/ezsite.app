---
paths:
  - 'tests/Arch/**'
---

# Arch

## Two arch files: shape in ConventionsTest, direction in LayeringTest
Put per-class shape rules and static source guards — view markup, CSS class prefixes, migration and lang parity read with `file_get_contents` — in `ConventionsTest.php`. Put dependency-direction rules in `LayeringTest.php`, one `arch()` per namespace: `expect([...])` over an array of namespaces passes vacuously even when a rule is violated. Confirm a new rule fails before landing its fix.
