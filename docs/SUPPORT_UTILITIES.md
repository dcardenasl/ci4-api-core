# Support Utilities

`ci4-api-core` ships a small set of utilities that solve recurring problems in API
services: batch-loading related labels without N+1 queries, caching expensive lookups
with one line of code, and normalising every date format CI4 and PHP throw at you
into a single safe representation.

These classes live in `dcardenasl\Ci4ApiCore\Support\`. They have no framework
dependencies beyond what your CI4 project already provides, and they are designed to
be used directly in your service layer — no need to subclass or configure anything to
get started.

---

## RelationLabelLoader

### The problem it solves

When you return a list of entities, FK columns (`user_id`, `role_id`, `category_id`)
carry numeric IDs that are useless to the end user. The instinctive fix is to resolve
each ID inside a loop:

```php
foreach ($entities as $entity) {
    $entity->user_name = $this->userModel->find($entity->user_id)?->full_name; // N+1
}
```

On a page of 50 rows with two FK columns that is 100 extra queries per request.

`RelationLabelLoader` collapses those N queries into **one query per relation**,
regardless of page size. It collects all unique IDs from the entity list, fetches the
labels in a single `WHERE id IN (...)`, and maps the results back onto the objects.

### Quick start

```php
use dcardenasl\Ci4ApiCore\Support\RelationLabelLoader;

$loader   = new RelationLabelLoader();
$entities = $this->repository->paginate($request); // returns array<object>

// Attach a single label from any table
$entities = $loader->attachLabel(
    entities:     $entities,
    sourceField:  'category_id',   // FK column on the entity
    targetField:  'category_name', // new field to attach
    relatedTable: 'categories',
    relatedLabel: 'name',          // column to copy from categories
);
```

After this call every entity object in the array has a `category_name` property set.
Entities where `category_id` is null or not found in the table are left untouched.

### Attaching actor labels (email + full name)

When the related table is a user or actor table, use `attachActorLabels()`. It attaches
three fields in one query: `{prefix}_email`, `{prefix}_full_name`, and `{prefix}_label`
(a combined `"Name <email>"` string useful for dropdowns and autocomplete).

```php
// FK is 'created_by', actor table is 'users', prefix defaults to 'user'
$entities = $loader->attachActorLabels(
    entities:    $entities,
    sourceField: 'created_by',
);

// Each entity now has:
//   created_by         → 7              (original, unchanged)
//   user_email         → "ada@example.com"
//   user_full_name     → "Ada Lovelace"
//   user_label         → "Ada Lovelace <ada@example.com>"
```

When your actor table is not `users`, pass the table and columns explicitly:

```php
$entities = $loader->attachActorLabels(
    entities:     $entities,
    sourceField:  'assigned_to',
    relatedTable: 'staff',
    emailColumn:  'work_email',
    nameColumns:  ['display_name'],   // one or more columns; concatenated with a space
    targetPrefix: 'assignee',         // → assignee_email, assignee_full_name, assignee_label
);
```

### Chaining multiple relations

Call the loader once per relation. Each call is still one query:

```php
$entities = $loader->attachLabel($entities, 'category_id', 'category_name', 'categories', 'name');
$entities = $loader->attachLabel($entities, 'status_id',   'status_label',  'statuses',   'label');
$entities = $loader->attachActorLabels($entities, 'created_by');
$entities = $loader->attachActorLabels($entities, 'updated_by', targetPrefix: 'editor');
// Total: 4 queries for any page size
```

### Where to call it in your service

Call it after retrieving entities and before mapping them to Response DTOs. A typical
service `index()` method looks like this:

```php
public function index(IndexProductRequest $request, ?SecurityContext $context = null): PaginatedResponseDTO
{
    $entities = $this->repository->paginate($request);

    $entities = $this->labels->attachLabel($entities, 'category_id', 'category_name', 'categories', 'name');
    $entities = $this->labels->attachActorLabels($entities, 'created_by');

    return $this->responseMapper->makeCollection($entities, $request->page, $request->limit);
}
```

Inject `RelationLabelLoader` through the constructor so it can be mocked in unit tests:

```php
public function __construct(
    private readonly ProductRepository $repository,
    private readonly ResponseMapperInterface $responseMapper,
    private readonly RelationLabelLoader $labels = new RelationLabelLoader(),
) {}
```

### API reference

#### `attachLabel()`

```php
public function attachLabel(
    array  $entities,       // array<int, object> — modified in place and returned
    string $sourceField,    // FK column on each entity (e.g. 'category_id')
    string $targetField,    // name of the new property to attach (e.g. 'category_name')
    string $relatedTable,   // database table to look up
    string $relatedLabel,   // column to copy as the label (e.g. 'name')
    string $relatedKey = 'id', // PK of the related table (almost always 'id')
): array
```

#### `attachActorLabels()`

```php
public function attachActorLabels(
    array  $entities,
    string $sourceField,               // FK column on each entity (e.g. 'user_id')
    string $relatedTable  = 'users',   // actor table
    string $emailColumn   = 'email',   // email column
    array  $nameColumns   = ['first_name', 'last_name'], // concatenated for full_name
    string $targetPrefix  = 'user',    // prefix for attached fields
    string $relatedKey    = 'id',
): array
// Attaches: {prefix}_email, {prefix}_full_name, {prefix}_label
```

### What it does NOT do

- It does not join tables — it always runs a separate `SELECT … WHERE id IN (…)`.
- It does not cache results between calls. Wrap with `CacheHelper` if the related
  table is large and changes infrequently (e.g. a `roles` or `statuses` lookup table).
- It does not handle deep nesting. Attach labels only on the top-level entity, not on
  nested objects — keep your Response DTOs flat.

---

## CacheHelper

### The problem it solves

The cache-aside pattern in plain CI4 is verbose and easy to get wrong:

```php
$user = cache()->get("user:{$id}");
if ($user === null) {
    $user = $this->model->find($id);
    cache()->save("user:{$id}", $user, 300);
}
return $user;
```

Spread across a service layer this becomes repetitive, the null-check is easy to
forget, and the TTL values scatter across the codebase.

`CacheHelper` collapses it into one line and works with any CI4 cache backend (File,
Redis, Memcached) without configuration:

```php
$user = CacheHelper::remember("user:{$id}", 300, fn() => $this->model->find($id));
```

### Quick start

```php
use dcardenasl\Ci4ApiCore\Support\CacheHelper;

// Cache a DB lookup for 5 minutes
$roles = CacheHelper::remember('roles:all', 300, fn() => $this->roleModel->findAll());

// Invalidate when data changes
CacheHelper::forget('roles:all');
```

The closure runs only on a cache miss. On a hit the closure is never called.

### API reference

#### `remember(string $key, int $ttl, callable $compute): mixed`

Return the cached value, or compute it on miss and cache it for `$ttl` seconds.
`$ttl = 0` means no expiry (equivalent to `rememberForever()`).

#### `rememberForever(string $key, callable $compute): mixed`

Same as `remember()` with no expiry. Use only for data that never changes (e.g.
configuration seeded at deploy time). Prefer `remember()` with a long TTL for
anything that could be updated.

#### `forget(string $key): bool`

Delete a single cache entry. Returns `true` on success.

#### `forgetMany(array $keys): void`

Delete multiple entries at once. Useful when a write invalidates several derived keys.

### Common patterns

**Per-resource key with ID**

```php
// Cache individual records
public function findCached(int $id): ?object
{
    return CacheHelper::remember("product:{$id}", 600, fn() => $this->repository->find($id));
}

// Invalidate on update or delete
public function update(int $id, UpdateProductRequest $request): OperationResult
{
    $result = $this->repository->update($id, $request);
    CacheHelper::forget("product:{$id}");
    return $result;
}
```

**Caching lookup tables (with RelationLabelLoader)**

Lookup tables (`roles`, `statuses`, `categories`) rarely change. Cache the label map
so the batch query only runs once per TTL window, not once per request:

```php
$roles = CacheHelper::remember('roles:label_map', 3600, function () {
    $loader = new RelationLabelLoader();
    return $loader->attachLabel(
        $this->roleRepository->findAll(), 'id', 'label', 'roles', 'name'
    );
});
```

**Invalidating multiple keys**

```php
CacheHelper::forgetMany([
    "product:{$id}",
    "products:page:1",    // first page of listing
    "catalog:summary",    // aggregate
]);
```

### When NOT to use it

- Do not cache entities that carry user-specific data (permissions, balances, personal
  info) unless the key includes the user ID.
- Do not use `rememberForever()` on mutable data — a stale entry will silently return
  wrong results with no expiry to bound the inconsistency window.
- Do not cache at multiple layers for the same data (service + controller). Pick one
  layer; the service layer is almost always the right choice.

---

## DateHelper

### The problem it solves

CI4 applications deal with dates in at least three formats simultaneously: PHP Unix
timestamps (`int`), MySQL date strings (`Y-m-d H:i:s`), and CI4's `Time` objects.
Converting between them scattered throughout a service leads to subtle bugs — wrong
timezone, lost precision on float timestamps, `strtotime()` returning `false` on
unexpected input.

`DateHelper` provides a single set of utilities that accepts any of these formats and
always returns a safe, predictable value. Null or empty inputs never throw — they
return `null` / `true` / `0` so callers can handle missing data with a simple
null-check rather than a try/catch.

### Quick start

```php
use dcardenasl\Ci4ApiCore\Support\DateHelper;

// Get the current datetime as a MySQL string
$now = DateHelper::now(); // "2026-05-10 14:32:00"

// Add time to any date representation
$expiry = DateHelper::addHours($entity->created_at, 24); // "2026-05-11 14:32:00"

// Check if a token or link has expired
if (DateHelper::isExpired($token->expires_at)) {
    throw new AuthenticationException('Token expired.');
}
```

### API reference

#### `now(): string`

Returns the current datetime as `Y-m-d H:i:s`. Use this instead of `date('Y-m-d H:i:s')`
so you have a single point to mock in tests.

#### `dateNow(): string`

Returns the current date as `Y-m-d`.

#### `toTimestamp(mixed $datetime): ?int`

Converts any supported input to a Unix timestamp. Returns `null` on empty or
unparseable input — never throws. Supported inputs: `null`, `''`, `int`,
`string` (any format `strtotime()` accepts), CI4 `Time` object.

```php
DateHelper::toTimestamp(null);                    // null
DateHelper::toTimestamp('');                      // null
DateHelper::toTimestamp('2026-05-10 14:00:00');   // 1747483200
DateHelper::toTimestamp(new Time('2026-05-10'));   // 1747440000
DateHelper::toTimestamp(1747483200);              // 1747483200  (passthrough)
```

#### `addMinutes(mixed $datetime = null, int $minutes = 0): string`

Returns a `Y-m-d H:i:s` string `$minutes` after `$datetime`. When `$datetime` is null
or empty, the calculation starts from `now()`.

#### `addHours(mixed $datetime = null, int $hours = 0): string`

Convenience wrapper around `addMinutes($datetime, $hours * 60)`.

#### `addDays(mixed $datetime = null, int $days = 0): string`

Convenience wrapper around `addMinutes($datetime, $days * 24 * 60)`.

#### `isExpired(mixed $datetime): bool`

Returns `true` when `$datetime` is in the past, null, or empty. Designed for token and
link expiry checks where a missing value should be treated as expired.

```php
DateHelper::isExpired(null);                         // true
DateHelper::isExpired('');                           // true
DateHelper::isExpired('2020-01-01 00:00:00');        // true  (in the past)
DateHelper::isExpired(DateHelper::addHours(null, 1)); // false (1 hour from now)
```

#### `format(mixed $datetime, string $format = 'Y-m-d H:i:s'): ?string`

Format any date representation with a custom PHP date format string. Returns `null` on
empty or unparseable input.

```php
DateHelper::format($entity->created_at, 'd/m/Y'); // "10/05/2026"
```

#### `toIso8601(mixed $datetime): ?string`

Returns the ISO 8601 string (`c` format, e.g. `"2026-05-10T14:32:00+00:00"`).
Use this for API responses consumed by JavaScript clients — the `c` format is
natively parsed by all major browsers via `new Date(string)`.

#### `diffMinutes(mixed $from, mixed $to = null): int`

Returns the number of minutes between `$from` and `$to`. When `$to` is null, compares
against `now()`. Returns `0` when `$from` is null or unparseable.

#### `humanDiff(string $datetime, ?string $compare = null): string`

Returns a human-readable relative string via CI4's `Time::humanize()` (e.g.
`"3 hours ago"`, `"in 2 days"`). Useful for timestamps displayed in the UI.

### Common patterns

**Token expiry in a service**

```php
$expiresAt = DateHelper::addHours(null, 2); // 2 hours from now
$this->repository->saveToken($userId, $token, $expiresAt);

// Later, when the token is used:
if (DateHelper::isExpired($tokenRecord->expires_at)) {
    throw new AuthenticationException('Token expired.');
}
```

**API response with ISO 8601 timestamps**

```php
return new ProductResponse(
    id:         $entity->id,
    name:       $entity->name,
    created_at: DateHelper::toIso8601($entity->created_at), // JS-friendly
    updated_at: DateHelper::toIso8601($entity->updated_at),
);
```

---

## RequestDataCollector

`RequestDataCollector` is the HTTP boundary normaliser used internally by
`ApiController::collectRequestData()`. It merges GET parameters, POST fields, raw JSON
body, multipart form data, and route captures into a single flat array that the
`RequestDtoFactory` passes to your Request DTO.

**You do not need to use this class directly.** It is wired by `core:install` and
called automatically for every request through `handleRequest()`.

If you are building a custom controller that does not extend `ApiController`, inject
it as a service:

```php
$collector = service('requestDataCollector'); // RequestDataCollector
$data      = $collector->collect($this->request, $params);
// Pass $data to RequestDtoFactory::make()
```

The collector handles all content types transparently:
- `application/json` — decodes the body and throws `BadRequestException` on malformed JSON
- `multipart/form-data` — merges fields and uploaded files
- `application/x-www-form-urlencoded` — merges raw input
- Route params (`$params`) always win over body fields of the same name
