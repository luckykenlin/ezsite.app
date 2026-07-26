---
description: Senior-engineer comment cleanup review — strip noise comments, keep only meaningful ones, never touch code
argument-hint: [files or directories — defaults to files changed in git]
---

Act as a senior engineer doing a code cleanup review focused **exclusively on comments**.

## Scope

Target: $ARGUMENTS

If no target was given above, run `git status --porcelain` to find the changed files (including untracked ones), and review only those. If a directory is given, review the files inside it.

Only clean `.php` files by default. Other file types (Blade templates, JS/TS, CSS, Markdown) are in scope only when the user passes them explicitly as a file argument.

### Ignored paths

Never touch files matching these paths, even when explicitly passed as an argument or included in a directory target — skip them and note the skip in the summary:

```
app/Providers/TenancyServiceProvider.php   # vendor scaffolding: commented-out examples are intentional reference material
```

Files that are third-party scaffolding/stubs (vendor-published config, package-generated providers) generally belong in this list — if you encounter one that isn't listed, skip it and suggest adding it here instead of cleaning it.

## Task

Review every comment (and docblock) in the target files.

**Remove:**
- Unnecessary or verbose comments that restate what the code already says
- AI-generated explanatory comments (e.g. "// Loop through the items", "// Return the result", "// Initialize the variable", section banners narrating obvious steps)
- Commented-out dead code with no explanatory value
- Redundant docblocks that only repeat the method signature with no added information (respect project PHPDoc conventions — keep `@param`/`@return`/array-shape annotations that add type information PHP itself cannot express)

**Keep (and fix wording only if inaccurate):**
- Business logic explanations
- Non-obvious decisions and their rationale ("why", not "what")
- Edge cases and gotchas
- Important constraints (e.g. RLS/tenancy invariants, ordering requirements, security notes)
- Machine-read annotations: `@`-annotations, attributes referenced in docblocks, `->comment('no-rls')`-style markers, TODO/FIXME with real content, license headers, linter/static-analysis directives (`@phpstan-`, `@psalm-`, `// @ts-ignore`, etc.)

## Strict rules — violating any of these is a failed review

- Do NOT modify any code — comments only.
- Do NOT change logic.
- Do NOT rename variables, functions, classes, or files.
- Do NOT change formatting or indentation of code lines (removing a whole comment line is fine; do not leave trailing whitespace or double blank lines behind).
- Do NOT reorder code.
- Do NOT add new comments unless required to preserve meaning of something you removed.
- Only add, remove, or edit comments.
- Do NOT run `vendor/bin/pint` (or any formatter) as part of this command — it would reformat code and other dirty files, violating the rules above. Removing whole comment lines cannot introduce style violations. This command is an explicit exception to the project's "run pint after modifying PHP files" rule.

## Process

The target files may already contain the user's own uncommitted changes, so `git diff` CANNOT be used to verify or revert this command's edits — it would mix in (and potentially destroy) the user's work. Use file snapshots instead:

1. List the target files and state how many you will review.
2. Copy each target file to the scratchpad directory as a snapshot BEFORE touching it (preserve relative paths to avoid name collisions).
3. Edit files one by one using precise `Edit` operations that touch comment lines only.
4. After all edits, verify each file with `diff <snapshot> <current-file>`: **every changed line must be a comment line (or a blank line left by a removed comment)**. If any diff touches code, restore ONLY that file from its snapshot (`cp`) and redo it correctly. Never use `git checkout`/`git restore`/`git stash` to revert — they would wipe the user's uncommitted work.
5. Finish with a short summary: files touched, comments removed/edited, files skipped via the ignore list, and comments you deliberately kept that a reviewer might question.
