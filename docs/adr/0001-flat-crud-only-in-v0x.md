# ADR-0001 — Flat CRUD only in v0.x

**Status:** Accepted
**Date:** 2026-05-06
**Audit ref:** B6.4

## Context

Most CRUD scaffolding engines in the PHP ecosystem (Laravel `make:resource`, Yii Gii, Symfony MakerBundle) try to handle relations declaratively: a `belongsTo` flag on a field generates an Entity accessor, a Form widget, and an embedded representation in the API Response.

The downside is that "smart" relation generation:

- **Couples the generator to the consumer's ORM/Entity choice.** Eager-loading via Eloquent looks nothing like CI4's `Entity` cast methods or a hand-rolled Repository.
- **Multiplies the cross-product surface.** Each (relation type × Entity base × Response shape × validation strategy × test scaffold) combination is its own template branch.
- **Is wrong more often than right.** A `belongsTo` field at scaffold time may be intended as a denormalized FK (return `category_id: int`), as an embedded child (return `category: {id, name}`), or as a separate sub-resource route (`GET /categories/{id}/products`). The generator can't pick correctly.
- **Drifts.** Once generated, the consumer hand-tweaks the relation handling in 80% of cases anyway.

## Decision

`ci4-api-crud-maker` v0.x scaffolds **only flat resources**. A single field type (`fk:<table>`) handles the database-level concerns (column, constraint, validation), and stops there.

If the consumer wants embedded children, eager loading, nested routes, or transactional multi-resource creation, they hand-wire it in the generated Service / Response DTO. The generator deliberately does not try to anticipate these shapes.

## Consequences

**Positive**

- Generator templates stay small and stable across versions. The `fk:` modifier hasn't changed since v0.1.0.
- The generated code is **complete** — there's no "Step 2: now hand-edit these 5 files." The flat module compiles, migrates, and serves immediately.
- Consumers retain full control over relation semantics (denormalized vs. embedded vs. nested route) without fighting the generator.

**Negative**

- Domains with many related resources require boilerplate-shaped hand edits in the parent Service. (~30 LOC per `hasMany` we need to expose.)
- Newcomers expecting Laravel/Yii-style `--with-relations` flags will be initially surprised.

## v0.3 candidate (when triggered)

Relation-aware generators are a candidate for **v0.3**, gated by the following triggers (any one is sufficient):

- A real consumer reports the same hand-wiring pattern across 3+ resources in the same project.
- We have a concrete proposal that does NOT couple the generator to a specific Entity class beyond what `ScaffoldingConfig::$entityBaseClass` already allows.
- We have a strategy for representing the *intent* of the relation in the field DSL — e.g. `category_id:fk:categories:embed` (return embedded child) vs. `:link` (return ID + Link header) — without exploding the cross-product.

Until those are met, deferring is the cheaper choice.

## Pointer

- README "Scope and limitations" section
- `CLAUDE.md` "Scope: flat CRUD only" section
- `TASKS.md` backlog entry **[CRUD-003]**
