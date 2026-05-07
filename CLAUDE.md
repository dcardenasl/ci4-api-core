# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## ⚡ Workflow — read this first

**Before touching any code, read `TASKS.md` in this directory.**

1. Take the first task from `## 🔴 En progreso` (if any) or `## 🟡 Próximo`
2. If taking from Próximo: move it to `## 🔴 En progreso`
3. Work exclusively on that task — if anything is unclear, ask before implementing
4. When done: move it to `## ✅ Completadas` with one line of notes (what you did and why)
5. Never work on tasks not defined in TASKS.md without explicit confirmation

For cross-repo context (current milestone, blocked tasks), read `../TASKS.md`.

## Repository Overview

**ci4-api-crud-maker** is a Composer package that provides a DTO-first CRUD scaffolding engine for CodeIgniter 4 projects. It is consumed by `ci4-api-starter` (and projects generated from it) via a path/VCS repository reference.

**Current version:** v0.1.0 (not yet published on Packagist — APIs may still change before 1.0.0)

## Commands

```bash
composer test       # PHPUnit (Unit + Integration suites)
composer analyse    # PHPStan level 8 (raised from 5 in audit B6.1, 2026-05-06)
composer cs-check   # PHP CS-Fixer dry-run (requires php-cs-fixer installed: `composer require --dev friendsofphp/php-cs-fixer`)
composer cs-fix     # Apply PHP CS-Fixer style fixes
composer quality    # analyse + test (no cs-check; CS-Fixer is opt-in)
```

**Spark commands it exposes (in consumer projects):**
- `php spark make:crud` — Main scaffold generator (TTY only — use `make-crud.sh` in scripts)
- `php spark make:crud:remove` — Delete previously generated artifacts + un-wire
- `php spark module:check` — Validate 13 post-scaffold artifacts are wired

**Shell wrappers (safe for non-TTY / Claude Code):**
```bash
bin/make-crud.sh {Name} {Domain} '{field1:type,field2:type}' yes
bin/validate-crud.sh {Resource} {Domain}   # 6-step post-scaffold checklist
```

## Architecture

| Directory | Purpose |
|---|---|
| `src/Commands/` | Spark commands: MakeCrud, MakeCrudRemove, ModuleCheck |
| `src/Generators/` | 8 modular generators (DTOs, Controller, Service, Migration, Routes, Docs, i18n, Tests) |
| `src/Core/` | Field, ResourceSchema, TypeMapper |
| `src/Orchestration/` | ScaffoldingOrchestrator, ScaffoldRemover |
| `src/Config/` | ScaffoldingConfig (all conventions) |
| `src/Validators/` | Field parsing, name/FK validation |
| `src/Wiring/` | ConfigWireman (Services.php injection) |
| `docs/` | ARCHITECTURE_CONTRACT.md (authoritative copy) |

## Key rules

- **This is a Composer package** — changes here affect all consumer projects. Breaking changes require a version bump.
- **Run `composer quality` before any merge** — PHPStan level 8 + PHPUnit. Both must be clean.
- **`docs/ARCHITECTURE_CONTRACT.md` is the authority** — the copy in ci4-api-starter is a reference snapshot only.
- **Never invoke `php spark make:crud` directly in non-TTY contexts** — shell expansion drops pipes in `--fields`. Use `bin/make-crud.sh` instead.
- **New field types** go in `src/Core/TypeMapper.php` + tests in `tests/Unit/TypeMapperTest.php`.
- **Do not publish to Packagist until v1.0.0** — the command API may still change.

## Scope: flat CRUD only (v0.x)

The generator scaffolds **flat resources** — one resource = one table = one CRUD module. The `fk:<table>` field modifier emits the FK column, constraint, and `is_not_unique[...]` validation rule, but it does **not** generate `hasMany`/`belongsTo` accessors, eager-loaded Response DTOs, or nested routes (e.g. `GET /categories/{id}/products`). If your domain needs related-resource embedding, scaffold both sides flat and hand-wire the join in the Service. See README "Scope and limitations" for the full list. Relation-aware generation is tracked as a v0.3 candidate.
