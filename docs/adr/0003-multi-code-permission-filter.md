# ADR-0003 — `permission:` filter accepts alternative codes

**Status:** Accepted

**Date:** 2026-08-20
**Audit ref:** CORE-023 (planned, triggered by teatromuseo-cms-domain's editorial-scope authorization work)

## Context

`AbstractPermissionFilter::before()` reads a single required code from `$arguments[0]` and denies unless
that exact code is present in the caller's effective permissions. Every consumer route declares one code:
`permission:cms.pages.read`, `permission:users.write`, etc.

teatromuseo-cms-domain needs routes that admit two different callers under two different authorization
models: a caller with a global capability (`cms.pages.read`) and a caller with a narrower, per-resource
capability (`cms.pages.scoped-read`) whose actual access to the specific row is decided later, inside the
controller, by a resource-scoped policy. The route-level filter only needs to admit either caller class far
enough to reach that policy — it isn't and shouldn't try to make the row-level decision itself.

The filter currently cannot express "any of these codes." Three options were considered:

1. Drop the route-level `permission:` filter for the affected routes and decide everything inside the
   controller. Rejected: it leaves routes.php looking unprotected at a glance, breaking the fleet-wide
   convention (documented in every consumer's own CLAUDE.md) that a route's required permission is legible
   from its filter declaration alone.
2. Register an umbrella permission code that every relevant role (global and scoped) carries, used only to
   pass the route gate. Rejected: it adds a permission that carries no real access decision by itself —
   pure ceremony, and a foot-gun for whoever assigns it thinking it grants something.
3. Teach the filter to accept a list of alternative codes. Chosen.

CI4's own `Filters::getCleanName()` already splits a filter's argument string on `,` into an array before
`AbstractPermissionFilter::before()` ever sees it — the filter class was just never written to look past
index 0.

## Decision

`AbstractPermissionFilter::before()` treats `$arguments` as a list of acceptable alternative codes instead
of reading only `$arguments[0]`. The caller passes if their effective permissions contain **any** listed
code (or the bypass code, unchanged). A single-code argument list behaves exactly as before.

Route syntax: `permission:cms.pages.read,cms.pages.scoped-read` — comma-separated, using CI4's existing
argument-splitting, not a new separator. No change to the `.`-vs-`:` code-format rule from ADR-0001/the
class docblock.

This is additive and backward compatible: every existing single-code route across every consumer
(catalog-domain, event-domain, multi-subscription, weblink, ci4-website-builder, etc.) keeps working
unchanged. No consumer is forced to adopt multi-code routes; teatromuseo-cms-domain is the first to use the
capability, by bumping its `dcardenasl/ci4-api-core` constraint to the release that ships this.

## Consequences

**Positive**

- Route files stay declarative and legible — the permission requirement for a route is still fully visible
  in `Routes.php` without opening the controller.
- Generic, reusable: any future domain app that needs a global/scoped split gets it for free.
- Zero migration cost for existing consumers.

**Negative**

- The filter now allows *reaching* a controller action without guaranteeing *row-level* access — consumers
  using multi-code routes must pair them with an in-controller authorization decision (e.g. a resource
  access policy) that actually enforces the scope. The filter was never a full authorization system by
  itself, but this makes the gap more visible: passing the route filter is necessary, not sufficient.
- One more thing to explain in the filter's docblock/README for new consumers.

## Pointer

- [`docs/adr/0001-flat-crud-only-in-v0x.md`](0001-flat-crud-only-in-v0x.md) — unrelated scope, referenced
  only to confirm this ADR doesn't touch CRUD/relation semantics.
- `src/Http/Filters/AbstractPermissionFilter.php` — the class this ADR governs.
- teatromuseo monorepo, `docs/plan/2026-08-20-plan-autorizacion-editorial-por-recurso-cms-v2.md` — the
  consumer plan that triggered this change (once published).
