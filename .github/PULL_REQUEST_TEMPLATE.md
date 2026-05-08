## Summary

- What changed?
- Why was this needed?

## Scope

- [ ] New field type / modifier
- [ ] Generator behavior change
- [ ] CLI flag / command surface change
- [ ] Wiring (`ConfigWireman`) change
- [ ] Architecture contract change (if so, ARCHITECTURE_CONTRACT.md updated)
- [ ] Documentation only

## Pre-1.0 caveat

- [ ] No breaking change
- [ ] Breaking change — flagged for the next minor bump (added to CHANGELOG.md `[Unreleased] / Changed - **BREAKING**`)

## Validation

- [ ] `composer test` (Unit + Integration)
- [ ] `composer analyse` (PHPStan level 8 — no new baseline entries)
- [ ] `composer cs-check` (PHP CS-Fixer dry-run)
- [ ] Manual smoke: `bash bin/make-crud.sh Sample Domain 'name:string:required' yes` against a fresh consumer (e.g. ci4-api-starter)
- [ ] Generated artifacts pass consumer's `composer quality`

## Documentation

- [ ] `CHANGELOG.md` entry under `[Unreleased]`
- [ ] `CLAUDE.md` updated if architecture or commands changed
- [ ] `README.md` "Field syntax" / "Scope and limitations" updated if user-visible behavior changed
- [ ] New ADR added under `docs/adr/` if a non-trivial decision is made

## Related issues / PRs

<!-- Closes #123 -->
