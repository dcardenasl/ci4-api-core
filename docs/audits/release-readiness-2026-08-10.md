# Release Readiness Audit — ci4-api-core

## Objective

Assess whether `ci4-api-core` is ready for a new GitHub release under the
repository's release discipline. This is an analysis only: no version bump,
commit, push, PR, merge, tag, or GitHub Release is being performed.

## Environment

- Repository: `dcardenasl/ci4-api-core`
- Local branch: `dev`, tracking `origin/dev`
- Remote default branch: `origin/main`
- Audit date: 2026-08-10
- Initial working-tree state: one pre-existing untracked file,
  `src/Commands/HousekeepingClean.php`

## Process Log

### Initial repository detection

- Expected: identify remote, release branches, recent history, and local
  changes before considering a release.
- Observed: GitHub remote and `dev` → `main` branch structure are present.
- Result: release topology is detectable; the working tree is not clean.
- Evidence: `git status --short --branch`, `git remote -v`,
  `git symbolic-ref refs/remotes/origin/HEAD`, and `git branch -r`.
- Next check: inspect tags, pending commits, release workflow, version files,
  and changelog state.

## Findings

### R-001 — Release candidate is not prepared yet

- Symptom: `dev` contains two commits after `origin/main`, but no release
  commit or versioned changelog section for the current work.
- Evidence: `origin/main..dev` contains `26437e6 feat(sparse-fieldsets): ...`
  and `cb074d5 docs(changelog): ...`; `CHANGELOG.md` still has the feature
  under `[Unreleased]`; the latest tag is `v1.3.0`.
- Impact: the tag/release workflow would not have a matching changelog section
  until a release version is chosen and prepared.
- Status: blocking for publication, not a code defect.

### R-002 — Working tree contains an untracked command referenced by Composer

- Symptom: `composer.json` registers
  `dcardenasl\\Ci4ApiCore\\Commands\\HousekeepingClean`, while
  `src/Commands/HousekeepingClean.php` is untracked.
- Evidence: `git status --short --branch` reports the source file as `??` and
  the branch diff shows the Composer registration.
- Impact: a commit containing only the tracked Composer change would publish a
  package whose autoloaded CI4 command class is missing.
- Status: release blocker until ownership and intended inclusion are resolved.

### R-003 — Composer lock is stale relative to composer.json

- Symptom: `composer validate --strict --no-check-publish` reports that the
  lock file is not up to date.
- Evidence: Composer explicitly reports that `composer.lock` has errors after
  the command registration was added to `composer.json`.
- Impact: reproducibility and the CI quality gate are not clean for a release
  candidate.
- Status: release blocker until the lockfile is regenerated or the JSON change
  is intentionally reverted.

### R-004 — Branch alias and release documentation need reconciliation

- Symptom: `composer.json` still declares `dev-main` as `1.2.x-dev` even though
  `v1.3.0` is the latest tag and the next pending feature is additive. The
  changelog/TASKS state v1.3.0 is published, while `CONTRIBUTING.md` still says
  the package is not yet published.
- Impact: consumers using the development branch can resolve an incorrect
  development version, and the release procedure gives contradictory guidance.
- Status: release-process/documentation risk; exact target alias should follow
  the project's chosen next version.

### R-005 — PHPStan gate fails on both local-only and release-candidate code

- Symptom: `composer quality` stops at PHPStan with four errors.
- Release-candidate errors: `Support/FieldsetValidator.php:36` checks
  `is_string($field)` even though the PHPDoc promises `list<string>`; and
  `Traits/SparseFieldsetTrait.php:32` is reported as unused because this
  public trait is only consumed by downstream controllers and is not used by a
  class inside the package.
- Local-only errors: `src/Commands/HousekeepingClean.php:55` and `:60` use the
  consumer-provided `WRITEPATH` constant but the file is currently untracked.
- Impact: the current local quality gate is red. The tracked sparse-fieldset
  changes require a PHPStan-compatible design/configuration adjustment; the
  housekeeping errors must be handled only if that command is intentionally
  included in the release.
- Status: blocking until the tracked errors are resolved and the local-only
  file is either committed correctly or excluded from the intended change.

### R-006 — CS-Fixer finds two style diffs in the new feature

- Symptom: the normal parallel `composer cs-check` is environment-blocked by
  a local socket permission error; the sequential retry completed and found two
  files that would be changed.
- Evidence: `tests/Unit/Traits/SparseFieldsetTraitTest.php` has two empty
  constructor bodies that need PSR formatting, and
  `src/Traits/SparseFieldsetTrait.php` has imports in the wrong order.
- Impact: CI's style step will fail unless these diffs are applied.
- Status: blocking quality issue; no fix was applied during this audit.

### R-007 — PHPUnit passes, with deprecations

- Evidence: `composer test` completed with 334 tests and 689 assertions.
- Caveat: PHPUnit reported one deprecation and two PHPUnit deprecations.
- Status: functional test suite passes, but the deprecations should be reviewed
  before treating the release as fully clean.

### R-008 — Security audit passes when network access is available

- Evidence: `composer security` completed successfully after the sandbox DNS
  limitation was bypassed with approved network access and reported no
  security vulnerability advisories.
- Status: passing.

### R-009 — Public documentation reports stale release metadata

- Symptom: `README.md` still reports status `v1.1.1`, while the repository's
  latest tag and `origin/main` are at `v1.3.0`.
- Related inconsistency: `CONTRIBUTING.md` says Packagist publication is still
  pending, while `TASKS.md` and the v1.3.0 changelog note say v1.3.0 was
  published.
- Impact: consumers and contributors may follow outdated installation and
  release instructions.
- Status: documentation blocker for a polished release; not a runtime defect.

## Corrections Applied

- Updated the fieldset validator contract so its runtime non-string guard is
  consistent with the documented input type.
- Registered the public sparse-fieldset trait in the package's PHPStan
  consumer-only trait exclusions and added the CI4 `WRITEPATH` runtime
  constant to the command analysis exclusions.
- Applied PHP CS-Fixer changes to the new sparse-fieldset source and tests.
- Prepared the v1.4.0 changelog section, advanced the Composer branch alias to
  `1.4.x-dev`, and refreshed stale release metadata in README/contributing
  documentation.
- Synchronized `composer.lock` with `composer.json`.
- Included `HousekeepingClean.php` as part of the v1.4.0 scope because the
  branch already registers that command in `composer.json`.

## Evidence

- `git describe --tags --abbrev=0 origin/main`: `v1.3.0`.
- `git log --oneline origin/main..dev`: two pending commits, one `feat` and one
  changelog documentation commit.
- `git diff --stat origin/main...dev`: nine tracked files changed, including
  the sparse-fieldset implementation and tests.
- `composer validate --strict --no-check-publish`: fails because the lock file
  is not up to date.
- `.github/workflows/release.yml`: tag-triggered workflow extracts notes from a
  matching `## [VERSION]` changelog heading and creates/updates a GitHub
  Release.
- `composer test`: 334 tests / 689 assertions passed; three deprecations were
  reported.
- Sequential `composer cs-check`: two formatting diffs reported in the new
  sparse-fieldset files.
- `composer security`: passed with no advisories.
- `git diff --check origin/main...dev`: no whitespace errors.
- After corrections: `composer validate --strict --no-check-publish` passed;
  PHPStan level 8 passed with `--debug`; sequential CS-Fixer found zero files;
  PHPUnit still passed with 334 tests and 689 assertions.
- Final `composer security` rerun passed with no vulnerability advisories.

## Pending Work

- Determine the latest published tag and the release candidate version.
- Confirm whether the release workflow and CHANGELOG satisfy the intended
  process.
- Run the repository's quality checks if dependencies and environment permit.
- Decide whether the next SemVer should be `v1.4.0` (the pending `feat` adds a
  public sparse-fieldset API) or another explicitly chosen version.
- Create the final one-line release commit for `v1.4.0`.
- Push `dev`, open the `dev` → `main` release PR, and complete CI/merge/tag
  verification.

## Automation Opportunities

- Add the new public trait to the package's explicit PHPStan public-trait
  suppression/analysis strategy, or provide an in-package analysis fixture.
- Make the release checklist validate `composer validate --strict`, PHPStan,
  sequential CS-Fixer in restricted environments, PHPUnit, and security audit
  before a release PR is opened.

## Final Summary

The repository is technically ready for the `v1.4.0` release candidate: the
metadata is prepared, the previously failing local quality gates are clean,
the housekeeping command is tracked, and the security audit passes. The final
release commit and GitHub publication remain to be completed.
