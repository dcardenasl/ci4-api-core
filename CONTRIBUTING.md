# Contributing to ci4-api-core

## Overview

This package is the **DTO-first runtime foundation** for CodeIgniter 4 API projects. Every change here propagates to all downstream consumers on their next `composer update`, so the bar for stability is high.

**Do not edit generated code in consumer projects to "fix" the engine** — fix it here, ship a new tag, and have consumers `composer update`.

## Development Setup

```bash
git clone https://github.com/dcardenasl/ci4-api-core.git
cd ci4-api-core
composer install                 # composer.lock is committed; reproducible
composer test                    # PHPUnit (Unit + Integration)
composer analyse                 # PHPStan level 8
```

## Branching Strategy

- `main` — stable, tagged releases only. No direct commits — PRs only.
- `dev` — integration branch for the next release.
- Feature branches: `feat/description`, `fix/description`, `docs/description`.

Always branch off `dev`, not `main`.

## Commit Conventions

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
feat(generator): support `json` field type
fix(wireman): handle preg_replace null return
docs(claude): document level-8 PHPStan baseline
chore(deps): bump phpstan to ^1.12
```

## Quality Gates

All PRs must pass these locally before review:

```bash
composer quality   # composer analyse + composer test
composer cs-check  # PHP CS-Fixer dry-run (requires php-cs-fixer; opt-in)
```

CI runs the same gates plus `composer audit` on PHP 8.2 and 8.3 (matrix in `.github/workflows/ci.yml`). Style fixes apply with:

```bash
composer cs-fix
```

PHP CS-Fixer is **not** a hard `composer require-dev` to keep the package surface small — install once locally:

```bash
composer require --dev friendsofphp/php-cs-fixer:^3.95
```

## Architecture Contract — non-negotiables

Reading order before touching the codebase:

1. `CLAUDE.md` — high-level architecture + commands.
2. `docs/ARCHITECTURE_CONTRACT.md` — the authoritative ruleset for generators.
3. `TASKS.md` — current backlog and active milestones.

Changes must respect:

- **DTO-first generation** — every scaffolded resource gets matching Request + Response DTOs. Do not generate logic that bypasses them.
- **No template files in `src/`** — generators emit code as PHP strings (heredoc / sprintf). New "templates" should live as private methods on a Generator class, not as `.stub` files.
- **PHPStan level 8** — added in audit B6.1. Do not introduce new errors. If a justified shortcoming arises, add to `phpstan.neon`'s narrow `ignoreErrors` block (no project-wide baseline).
- **Tests are required** for new generators and field types. Unit tests live in `tests/Unit/Generators/` and `tests/Unit/Core/`.
- **Field types** are registered in `src/Core/TypeMapper.php`. Every new type needs a TypeMapper entry, validation rules, and migration column type.

## Versioning

This project uses [Semantic Versioning](https://semver.org/) — but with one caveat: **until v1.0.0, the public API is unstable**. Minor bumps may include breaking changes to the generator API.

- **MAJOR** (post-1.0) — breaking changes to the generator command-line API, generator class signatures, or generated code structure.
- **MINOR** — new field types, new generators, new validators (additive).
- **PATCH** — bug fixes, dependency bumps, doc updates.

## Release Process

Releases are cut from `main`. Tags belong on `main` after a merge — never on `dev`.

### Step-by-step

1. **On `dev`, prepare the release commit:**

   a. In `CHANGELOG.md`, rename `[Unreleased]` to `[x.y.z] — YYYY-MM-DD` and add a fresh empty `[Unreleased]` section above. Update footer comparison links.

   b. Bump `extra.branch-alias` in `composer.json` if moving to a new minor (e.g. `0.1.x-dev` → `0.2.x-dev`).

   c. Commit:
   ```bash
   git commit -am "chore: release vx.y.z"
   ```

2. **Open the PR `dev → main`.**

3. **After the PR is merged, tag `main`:**
   ```bash
   git checkout main
   git pull origin main
   git tag vx.y.z
   git push origin vx.y.z
   ```

4. **Update downstream consumer projects** by running `composer update dcardenasl/ci4-api-core`.

> **Packagist:** the package is **not yet published** (currently consumed via path/VCS repository). It will be published once v1.0.0 cuts. Until then, downstream projects pin to a specific tag or to `dev-main`.

## Pull Request Checklist

- [ ] Branch is off `dev`
- [ ] `composer quality` passes (PHPStan 8 + PHPUnit)
- [ ] `composer cs-check` passes (style)
- [ ] `composer.lock` committed if dependencies changed
- [ ] `CHANGELOG.md` updated under `[Unreleased]`
- [ ] `CLAUDE.md` updated if architecture/commands changed
- [ ] New field types include TypeMapper entry + tests
- [ ] No secrets, credentials, or `.env` files committed

## Reporting Issues

Open an issue at https://github.com/dcardenasl/ci4-api-core/issues. Include:

- PHP version (`php --version`)
- CodeIgniter 4 version (`composer show codeigniter4/framework | grep version`)
- Reproduction: the `make:crud` command + `--fields` argument that triggered the issue
- Generated output vs expected
- PHPStan/PHPUnit error excerpts (redact any local paths/credentials)
