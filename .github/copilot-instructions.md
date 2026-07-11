# Commit Messages

When generating commit messages, ALWAYS follow
https://www.conventionalcommits.org/en/v1.0.0/

Rules:

- Format:
  <type>(<scope>): <description>

- Valid types:
  feat
  fix
  refactor
  perf
  docs
  test
  build
  ci
  chore
  style
  revert

- Subject:
    - imperative mood
    - lowercase
    - no trailing period
    - max 72 characters

- Prefer including scope.

Examples:

feat(auth): add password reset endpoint

fix(payment): prevent duplicate webhook processing

refactor(campaign): simplify audience counting
