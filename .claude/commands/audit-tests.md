---
description: Audit the test suite for low-value coverage-driven tests and produce a keep/merge/rewrite/delete report — read-only, no code changes
argument-hint: [test files or directories — defaults to one batch of the tests/ suite]
allowed-tools: Read, Grep, Glob, Write(/private/tmp/**), Bash(git log:*), Bash(git diff:*), Bash(ls:*), Bash(wc:*), Bash(php artisan test:*)
---

Analyze the current repository and identify test cases that only exist to increase code coverage but provide little real value.

Your task is NOT to maximize coverage.
Your task is to improve the quality of the test suite.

## Scope

Target: $ARGUMENTS

If no target was given above, do NOT try to audit the whole suite in one pass — quality degrades silently on later files. Instead: list the second-level directories under `tests/` with their test counts, audit the first directory not yet covered by a previous report, and end by telling the user which directory to pass next time. An explicit argument always wins and is audited in full.

Before judging any test, read the production code it exercises — never evaluate a test from its name or assertions alone. If the batch is still too large to read every production counterpart carefully, shrink the batch and say so — never skim.

## Evaluation principles

Evaluate every test against these questions:

- Does this test verify business logic or application behavior?
- Is this test simply asserting framework behavior (Laravel, Filament, Livewire, PHP, etc.)?
- Is this test only covering getters, setters, constructors, enum values, casts, or trivial code?
- Is this test only created to satisfy 100% coverage?
- Is this behavior already guaranteed by the framework or a well-tested third-party library?
- Is this test duplicated by another test?
- Does the maintenance cost outweigh its value?

## Project-specific context (weigh this before recommending)

- CI enforces `--exactly=100.0` line coverage (see the `pest-testing` skill). Deleting a test whose lines are not covered elsewhere will fail CI. For every Delete/Merge recommendation, state whether the covered production lines are also exercised by another test; if not, the recommendation must include what to do instead (cover via a behavioral test, or flag the production code itself as trivial/dead). Judge this statically by reading the other tests that touch the same production code — do NOT run the full suite with `--coverage` (too slow); running individual test files without coverage to confirm behavior is fine.
- RLS/tenancy tests (`tests/Feature/Tenancy/*`, and anything asserting isolation, policy generation, or the exemption list) are load-bearing security guards, not coverage filler — they are automatically "Keep" regardless of how mechanical they look.
- The per-model test template (relations, casts, `to array`, constraints) is a deliberate project convention from the `pest-testing` skill. If you think a template section is low-value, say so as a convention-level finding (one entry covering the pattern), not as dozens of per-file entries.
- Arch tests (`arch()`/`expect()->toUse...`) encode project rules; treat them as constraints, not framework assertions.

## For every candidate

1. Explain why the test has low value.
2. Explain whether the behavior is already guaranteed by the framework or by another test (name the test or the framework mechanism).
3. Estimate the risk of removing it: Low / Medium / High.
4. Recommend one of: **Keep** / **Merge** (into which test) / **Rewrite** (into what) / **Delete**.

## Important rules

- Base all conclusions on the CURRENT repository — read the actual production code and sibling tests before deciding.
- Do not assume something is framework behavior unless you are reasonably confident; when unsure, say so and default to Keep.
- Never recommend removing tests that validate business rules, domain logic, authorization, validation, integrations, or regression tests for past bug fixes (check `git log` on the test file when a test looks odd but deliberate).
- Prefer keeping meaningful tests even if they contribute little coverage.
- Focus on reducing maintenance cost while preserving confidence.

## Output

Do NOT modify any production or test code. Produce a report with these sections:

1. **Summary** — which directories/files this batch covered (and which remain unaudited), how many tests audited, count per recommendation, and whether applying all Delete/Merge recommendations would break the 100% gate.
2. **Findings table** — one row per candidate: test file `path:line`, test name, reason, framework/duplicate justification, risk, recommendation.
3. **Convention-level findings** — patterns that repeat across many files, with a single recommendation for the pattern.
4. **Explicit non-findings** — tests you suspected but decided to Keep after reading the production code, with the reason (this prevents re-litigating them next audit).

If the findings table exceeds ~20 rows, write the full report to a file in the scratchpad directory and present only the Summary plus the highest-risk findings in the reply, linking the file.
