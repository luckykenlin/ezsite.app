---
paths:
  - 'tests/**'
---

# Tests

## The pest-testing skill is the source of truth for tests
Read `.ai/skills/pest-testing/SKILL.md` before writing or changing a test — it owns the folder taxonomy, naming, the all-Postgres/RLS backend and its truncation lifecycle, factories, doubles (container-swapped fakes over Mockery), assertion style, the per-model test template, and the 100% coverage gate. Do not restate any of that here; the rules in this file are only the gaps it does not cover. `.ai/rules/boost/tests.md` also matches `tests/**` — that one is generated from the stock Laravel/Pest guidelines and is the weaker authority of the two; where it disagrees with the skill, the skill wins. The same ranking applies to the Boost-rendered `testing-best-practices` skill that `CLAUDE.md` tells you to read: its `LazilyRefreshDatabase`/`RefreshDatabase` advice does not apply here because `tests/Pest.php` owns the database lifecycle (truncation, not transactions); read it for assertion and coverage judgement only, and defer to `pest-testing` on anything about the backend, layout, or naming.

## Where shared test code lives, by kind
Put shared behavior that needs `$this` in a `tests/Concerns` trait and mix it in from `tests/Pest.php`; put namespace-less free helper functions in `tests/Helpers/`; put concrete supporting classes such as jobs and stub implementations in `Tests\Fixtures` under `tests/Fixtures/`; put datasets in `tests/Datasets/`. Never declare a shared helper function inside a test file — a second file declaring it is a fatal redeclaration.

## A green `artisan test` is not the bar — run the composer gates
`php artisan test` passing proves little here. Finish with `composer test:all`. Three gates go red on their own: line coverage must be EXACTLY 100% (a new untaken branch fails it), `tests/Browser` asserts things no PHP test can — SiteStylesRailTest names the font families that must actually be FETCHED, so any `App\Design\FontPair` change breaks it — and phpstan rejects `config('x')` as `mixed` (use `config()->string()`). Two traps in running them: give the coverage run the machine to itself, or a concurrent browser run fails it on `livewire-tmp`; and `composer test:unit` exceeds composer's 300s process timeout, so invoke `vendor/bin/pest --parallel --coverage --exactly=100.0` directly.
