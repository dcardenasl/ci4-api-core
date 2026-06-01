# ci4-api-core

Runtime foundation Composer package (`dcardenasl/ci4-api-core`) for all CI4 API projects.
Does NOT contain scaffolding commands — those live in `ci4-api-scaffolding`.

## Entry Points

- `src/Http/` — `ApiController`, `ApiRequest`, `ApiResponse`, `ContextHolder`; 9 concrete filters + 3 abstract auth bases
- `src/Services/` — `BaseCrudService`, `HandlesTransactions` trait, `AuditService`, `AuditWriter`
- `src/Dto/` — `BaseRequestDTO`, `PaginatedResponseDTO`, `SecurityContext`
- `src/Repositories/` — `BaseRepository`, `GenericRepository`, `PivotRepositoryInterface`
- `src/Exceptions/` — `ApiException` base + 8 concrete subclasses
- `src/Queue/` — `QueueManager`, `SyncQueueManager`, `WriteAuditLogJob`
- `docs/ARCHITECTURE_CONTRACT.md` — authoritative design rules for all consumers

## Contracts & Invariants

- This is a Composer package — every change can break consumer projects. Breaking changes require a version bump.
- Consumer projects must wire 4 service factories (`auditService`, `requestAuditContextFactory`, `requestDtoFactory`, `requestDataCollector`) in their `Config/Services.php`.
- `docs/ARCHITECTURE_CONTRACT.md` is the authority on layer rules — consumer copies are reference snapshots only.
- PHPStan runs at level 8. `composer quality` must be clean before any merge.
- Do not add scaffolding commands or generators here — those belong in `ci4-api-scaffolding`.
- Do not introduce procedural helpers — use namespaced classes (`Security\Hasher`, `Request\RequestHelper`).

## Commands

```bash
composer test       # PHPUnit
composer analyse    # PHPStan level 8
composer cs-fix     # Apply PHP CS-Fixer
composer quality    # analyse + cs-check + security + test
```

## Anti-patterns

- Don't add `make:crud`, `module:check`, or generator code here.
- Don't skip `composer quality` — PHPStan level 8 catches real type errors.
- Don't let consumer copies of `ARCHITECTURE_CONTRACT.md` override the canonical version in `docs/`.

## Related Context

- Detailed reference: `CLAUDE.md` (this repo)
- Scaffolding engine: `dcardenasl/ci4-api-scaffolding` package
- Consumer example: `dcardenasl/ci4-api-starter`
