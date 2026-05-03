# CRUD From Zero

Canonical guide for creating a new CRUD resource using `dcardenasl/ci4-api-crud-maker`.

This flow is **recommended by default**:
1. Scaffold first with `vendor/bin/make-crud.sh`
2. Validate wiring with `module:check`
3. Apply migration, restart server, regenerate OpenAPI
4. Customize only what business rules require
5. Close tests and quality gates

Manual CRUD creation is still valid when custom structure is required.

## 1. Pre-Checklist

1. Define resource name (singular, StudlyCase): `Product`
2. Define domain folder (StudlyCase): `Catalog`
3. Define route slug (plural kebab, optional — auto-derived otherwise): `products`
4. Define access model (public read, admin write, etc.)
5. Define minimum table schema and audit requirements
6. Decide soft-delete vs. hard-delete (see §2.3)

## 2. Scaffold First

The `make:crud` engine generates all layers in one pass: Migration, Entity, Model, Request/Response DTOs, Service, Interface, Controller, OpenAPI doc class, i18n files, test skeletons, service wiring, and the route file.

### 2.1 Recommended: `vendor/bin/make-crud.sh` (shell-safe)

```bash
bash vendor/bin/make-crud.sh Product Catalog \
  'name:string:required|searchable,price:decimal:required|filterable,category_id:fk:categories:required' \
  yes
```

Signature: `bash vendor/bin/make-crud.sh <Resource> <Domain> '<Fields>' [SoftDelete=yes] [Route]`

Why prefer the wrapper:
- Single-quote handling prevents shells from eating the `|` inside `--fields`
- Runs `composer cs-fix` automatically post-generation (keeps pre-commit hooks happy)
- Prints the exact follow-up commands with placeholders resolved
- Safe in non-TTY environments (CI pipelines, Claude Code, automation scripts)

### 2.2 Alternative: `php spark make:crud` (interactive)

When you want the engine to prompt you for each field interactively:

```bash
php spark make:crud Product --domain Catalog
```

The `--fields` variant works too, but must be quoted carefully:

```bash
php spark make:crud Product --domain Catalog --fields='name:string:required|searchable,price:decimal:required|filterable'
```

> If you run `make:crud` in a non-interactive environment and forget to quote the pipes, the command silently drops all field flags and waits for interactive input — which never arrives. This is the #1 reason `vendor/bin/make-crud.sh` is preferred.

### 2.3 The `SoftDelete` flag

- **`yes` (default)** — Adds a `deleted_at TIMESTAMP NULL` column, sets `useSoftDeletes = true` in the Model, and includes `deleted_at` in the Entity `$dates`. Use for business entities (Users, Orders, Products) where you need an audit trail or restore capability.
- **`no`** — No `deleted_at` column; deletes are physical. Use for lookup tables (Permissions, Roles, Statuses) and append-only tables (AuditLog).

### 2.4 Field Syntax

Format: `name:type:modifier1|modifier2|modifier3`

Multiple fields separated by commas. Always wrap the whole string in **single quotes** when using pipes.

**Supported Types:**

| Type | Database | PHP | Example |
|------|----------|-----|---------|
| `string` | `VARCHAR(255)` | `string` | `name:string:required` |
| `text` | `TEXT` | `string` | `description:text:nullable` |
| `int` | `INT UNSIGNED` | `int` | `stock:int:required` |
| `decimal` | `DECIMAL(10,2)` | `float` | `price:decimal:required` |
| `bool` | `TINYINT(1)` | `bool` | `is_active:bool` |
| `email` | `VARCHAR(255)` | `string` | `email:email:required` |
| `date` | `DATE` | `string` | `birth_date:date:nullable` |
| `datetime` | `DATETIME` | `string` | `published_at:datetime` |
| `json` | `JSON` | `array` | `metadata:json:nullable` |
| `fk:table` | `INT UNSIGNED + FK` | `int` | `category_id:fk:categories:required` |

**Supported Modifiers:**

| Modifier | Effect |
|----------|--------|
| `required` | `NOT NULL` in DB + `required` validation rule |
| `nullable` | `NULL` allowed in DB + `permit_empty` validation rule |
| `searchable` | Included in `?search=` (LIKE). Implicit B-tree index added. |
| `filterable` | Included in `?filter[col]=` (exact match). Implicit B-tree index added. |
| `unique` | `UNIQUE` index + `is_unique[table.col]` on the Create DTO and Model |
| `index` | Non-unique B-tree index (use when you need an index but none of searchable/filterable apply) |
| `fk:table` | Foreign key to `table.id` + `is_not_unique[table.id]` validation (enforces the referenced row exists) |

Multiple modifiers combine with `|`. Example:

```text
email:email:required|unique
status:string:required|filterable|index
```

Invalid or reserved field names are rejected upfront (PHP keywords, MySQL reserved words, duplicates, and collisions with `id`/`created_at`/`updated_at`/`deleted_at`).

What the scaffold generates:
1. Database migration file
2. Controller, Service, Interface
3. Request/Response DTOs
4. Model/Entity
5. OpenAPI endpoint placeholder file (`app/Documentation/…`)
6. i18n files (`en` and `es`)
7. Unit/Integration/Feature test skeletons
8. Service registration snippet in `app/Config/Services.php` (if missing)

What it does **not** generate:
1. Domain-specific repository interface/implementation
2. Final business rules and validation specifics

## 3. After Scaffolding (What Each Follow-Up Command Does)

After `make:crud` finishes, run these commands in order. `vendor/bin/make-crud.sh` prints them at the end; this section explains what each one actually does so you know what to look for if something goes wrong.

### 3.1 `php spark module:check Product --domain Catalog`

Static check that verifies every expected artifact was generated and wired:

- All ~13 files exist (Controller, Service, Interface, 4 DTOs, OpenAPI doc class, Model, Entity, 2 language files, 3 test files).
- Namespaces match the domain folder.
- The service and its response mapper were registered in `app/Config/{Domain}DomainServices.php`.
- The route file `app/Config/Routes/v1/{domain-kebab}.php` references the new controller.
- No `markTestIncomplete` / `TODO` / `FIXME` placeholders remain in generated code.

Does **not** validate: migration SQL correctness, FK target tables exist, business logic.

### 3.2 `php spark migrate`

Applies the migration generated in Step 2. Review it first:

```bash
cat app/Database/Migrations/*_Create{Plural}Table.php
```

Check the table name (snake_case plural), soft-delete column presence, indexes, and FK constraints match your intent. Then run `php spark migrate`.

### 3.3 Restart the dev server

```bash
pkill -f 'spark serve'; php spark serve --port 8080 &
```

**Required.** CodeIgniter 4 loads all route files at boot from `app/Config/Routes/v1/*.php`. New files generated since the last start are invisible until the server restarts.

### 3.4 `php spark swagger:generate`

Re-reads DTOs (for schemas) and `app/Documentation/{Domain}/*Endpoints.php` (for path definitions) and writes the unified spec to `public/swagger.json`. Without this step, new endpoints don't appear in Swagger UI even though they respond correctly.

### 3.5 `composer cs-fix` (only if you edited generated files)

The scaffolding engine emits PSR-12-compliant code, but if you manually tweak a generated file you may introduce style violations that the pre-commit hook will reject. `vendor/bin/make-crud.sh` runs `cs-fix` automatically after generation; run it manually after edits.

## 5. Align Persistence Layer

1. Update `Entity` casts/dates to match migration
2. Update `Model`:
   - `allowedFields`
   - `validationRules`
   - `searchableFields`, `filterableFields`, `sortableFields`
   - traits (`Filterable`, `Searchable`, `Auditable`) as needed

## 6. Finalize DTO Contracts

1. Request DTOs extend `BaseRequestDTO` and remain `readonly`
2. Implement complete `rules()`, `map()`, `toArray()`
3. Response DTO includes OpenAPI `#[OA\Property]` attributes and `fromArray()`
4. Keep DTO fields strictly aligned with API contract (no leaked internal fields)

## 7. Service + Repository Strategy

Service rules:
1. Service remains pure (no HTTP response building)
2. Read flows return DTOs; command flows return `OperationResult`
3. Use transactions for write operations

Repository default:
1. Use `GenericRepository` via `RepositoryInterface` for standard CRUD
2. Create dedicated `*RepositoryInterface` + implementation only when:
   - domain-specific queries are non-trivial
   - persistence rules are reused across services
   - custom query methods are required for clarity/testability

## 8. Register Dependencies

1. Ensure service registration exists in `app/Config/Services.php`
2. If using a dedicated repository, register repository factory methods too
3. Keep constructor DI typed by interfaces where applicable

## 9. Controller, Routes, OpenAPI, i18n

1. Controller extends `ApiController`
2. Resolve service in `resolveDefaultService()`
3. Use `handleRequest('method', RequestDTO::class)` pattern
4. Add/verify routes in `app/Config/Routes.php` with proper filters
5. Complete endpoint docs in `app/Documentation/{Domain}/…`
6. Keep language parity in `app/Language/en` and `app/Language/es`

## 10. Testing and Quality Gates

1. Complete Unit tests (service behavior/contracts)
2. Complete Integration tests (model/persistence behavior)
3. Complete Feature tests (HTTP status + JSON structure + authorization)
4. Run:

```bash
php spark tests:prepare-db
composer quality
php spark swagger:generate
```

## FAQ

### When should I run migrations?
After running `make:crud` and validating the generated module skeleton with `module:check`.

### Does `make:crud` create migrations?
Yes. It uses a single schema to synchronize database, DTOs, and services.

### When do I need a dedicated repository?
Only when generic CRUD criteria are insufficient and domain persistence queries become explicit/complex.

### Is `make:crud` mandatory?
It is the recommended default. Manual CRUD creation is allowed for custom requirements — but be aware the generated structure is what the architecture tests and the admin starter expect.

### When should soft-delete be `no`?
Default `yes` fits business entities (Users, Orders, Products) where you want an audit trail and restore capability. Use `no` for:
- Lookup tables with finite, rarely-changing rows (Permissions, Roles, Statuses, Countries)
- Append-only tables where rows are never removed (AuditLog, IdempotencyKey)
- Join tables for many-to-many relationships (Role×Permission)

## Troubleshooting

### `php spark make:crud` prompted me for fields even though I passed `--fields='…'`

You're running in a non-TTY environment and your shell consumed the pipe characters. Use `vendor/bin/make-crud.sh` instead — the wrapper quotes correctly. Or, if you must use `php spark` directly, wrap `--fields` in **single** quotes: `--fields='name:string:required|searchable'`.

### Routes still return 404 after scaffolding

You didn't restart the dev server. CI4 loads route files at boot only:

```bash
pkill -f 'spark serve'; php spark serve --port 8080 &
```

### Migration fails: "table X doesn't exist" when using `fk:X`

The foreign key target table hasn't been migrated yet. Either the target module isn't scaffolded, or its migration is ordered after yours. Check `app/Database/Migrations/` — migration filenames are `YYYY-MM-DD-HHMMSS_…`; earlier ones run first. Scaffold and migrate the target first, then scaffold the FK owner.

### Swagger UI doesn't show the new endpoint

Run `php spark swagger:generate`. The spec is not generated at request time — it's a static artifact in `public/swagger.json` that must be regenerated after any change to DTOs or `app/Documentation/`.

### Pre-commit hook rejects the generated files (PHP CS Fixer)

If you used `vendor/bin/make-crud.sh`, this shouldn't happen — it runs `cs-fix` automatically. If you used `php spark make:crud` directly and got style errors:

```bash
composer cs-fix && git add -u && git commit
```

**Do not use `--no-verify`.** The hook exists to catch these cases.

### `ScaffoldConflictException: files already exist`

Some of the ~13 artifacts from a previous scaffold are still on disk. Either finish the previous module (run migrate, commit the files) or delete the stale ones manually and try again. The orchestrator rolls back partial writes on failure, so you shouldn't see this from an aborted run in normal conditions.

### Field `class` / `order` / `function` rejected with an error

That's working as intended — `FieldNameValidator` refuses PHP reserved words, MySQL reserved words, duplicates, and `id`/`created_at`/`updated_at`/`deleted_at` (those are engine-managed). Rename the field to something domain-specific (`order_number`, `class_name`, etc.).
