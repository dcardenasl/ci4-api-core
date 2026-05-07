---
name: Feature request
about: Propose a new capability for ci4-api-core
title: '[Feature] '
labels: enhancement
assignees: ''
---

## Problem statement

<!-- What pain are we solving? Concrete consumer-side example helps. -->

## Proposed solution

<!-- High-level description. New field type? Generator change? New CLI flag? -->

## Scope check

- New **field type** (`int`, `string`, ...): goes in `src/Core/TypeMapper.php` + tests in `tests/Unit/TypeMapperTest.php`.
- New **modifier** (`searchable`, `unique`, ...): touches `src/Validators/FieldStringParser.php` + the generator that consumes it.
- New **generator** (or major change): affects ARCHITECTURE_CONTRACT.md. Open a discussion first.
- **Relation-aware generation** (`hasMany`/`belongsTo` accessors): tracked under the v0.3 candidate work in `docs/adr/0001-flat-crud-only-in-v0x.md`. Re-read that ADR before proposing.

## Alternatives considered

<!-- What else did you think about? Why is this the right path? -->

## Pre-1.0 caveat

This package is **pre-1.0**. We accept breaking changes per minor bump, but we still want them to be deliberate. Tag the PR / issue with `breaking-change` if that's what you're proposing.

## Definition of done

- [ ] Tests cover the new path (Unit + at least one Integration if a generator changed)
- [ ] PHPStan level 8 stays clean (no new baseline entries)
- [ ] Generated code (if any) compiles cleanly in `ci4-api-starter` after a smoke `make:crud` call
- [ ] CHANGELOG `[Unreleased]` entry
- [ ] CLAUDE.md updated if architecture or commands change
- [ ] ARCHITECTURE_CONTRACT.md updated if a non-negotiable invariant moved
