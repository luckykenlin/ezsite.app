---
paths:
  - 'tests/**'
---

# Tests

## The pest-testing skill is the source of truth for tests
Read `.ai/skills/pest-testing/SKILL.md` before writing or changing a test — it owns the folder taxonomy, naming, the all-Postgres/RLS backend and its truncation lifecycle, factories, doubles (container-swapped fakes over Mockery), assertion style, the per-model test template, and the 100% coverage gate. Do not restate any of that here; the rules in this file are only the gaps it does not cover. `.ai/rules/boost/tests.md` also matches `tests/**` — that one is generated from the stock Laravel/Pest guidelines and is the weaker authority of the two; where it disagrees with the skill, the skill wins.

## Where shared test code lives, by kind
Put shared behavior that needs `$this` in a `tests/Concerns` trait and mix it in from `tests/Pest.php`; put namespace-less free helper functions in `tests/Helpers/`; put concrete supporting classes such as jobs and stub implementations in `Tests\Fixtures` under `tests/Fixtures/`; put datasets in `tests/Datasets/`. Never declare a shared helper function inside a test file — a second file declaring it is a fatal redeclaration.
