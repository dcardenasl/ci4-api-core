# Extending IAM — plugging in another identity provider

`ci4-api-core` is **IAM-agnostic by design**. The package ships abstract HTTP filters, an abstract authorization service, and a small set of contracts. The concrete identity model — token format, user storage, permission resolution — lives in the consumer.

This guide describes the extension contract so you can plug in any provider (CodeIgniter Shield, OAuth2 / OIDC, Keycloak, an external JWT issuer, an LDAP-backed JWT, etc.) without forking core.

---

## Why this guide?

A typical consumer might implement one specific RBAC model (`applications × permissions × roles × user_roles`, JWT with a `scope` claim, an in-process permission resolver). That is **one valid shape, not the only one**. The contract below is what core actually requires; everything else is a consumer-side convention.

---

## The contract in 5 pieces

### 1. `AbstractJwtAuthFilter` — `src/Http/Filters/AbstractJwtAuthFilter.php`

Generic Bearer-token flow. Extracts the `Authorization: Bearer …` header, decodes the token, optionally checks revocation, optionally loads the actor and runs a policy check, and finally populates `ApiRequest::setAuthContext()` and `ContextHolder` with `(user_id, permissions[])`.

**Required hook:**

```php
abstract protected function decodeToken(string $token): ?object;
```

Return any object whose public properties expose at least:

- `uid` — integer user id (omit / set to `0` for service tokens)
- `scope` — `array<string>` of permission codes
- `jti` — token id (only required if `shouldCheckRevocation()` returns true)

**Optional hooks (safe defaults):**

```php
protected function shouldCheckRevocation(): bool;          // default: false
protected function isTokenRevoked(string $jti): bool;      // default: false
protected function loadActor(int $userId): ?object;        // default: null (skip)
protected function requireActorOnUserToken(): bool;        // default: false
protected function assertAccessPolicy(object $actor, RequestInterface $request): ?ResponseInterface; // default: null
protected function accessPolicyBypassRoutes(): array;      // default: config('Api')->accessPolicyBypassRoutes ?? []
protected function getSecurityAuditLogger(): ?SecurityAuditLoggerInterface; // default: null
protected function extractBearerToken(string $authHeader): ?string;         // default: regex `Bearer\s+(.+)`
```

**Key facts:**

- Service tokens (M2M, `uid = 0`) are allowed through with `user_id = null` and the token's `scope` populated. Authorization is enforced downstream by `AbstractPermissionFilter`.
- An already-populated `ContextHolder` short-circuits the filter (preserves test ergonomics — a `TestAuthFilter` can pre-set the context).
- Throws `AuthenticationException` / `AuthorizationException` from `assertAccessPolicy()` are wrapped into 401/403 responses automatically.

### 2. `AbstractPermissionFilter` — `src/Http/Filters/AbstractPermissionFilter.php`

Per-route authorization. Reads the permissions populated by the JWT filter and enforces a single required code provided as a filter argument: `permission:<code>` (e.g. `permission:users.write`).

> Permission codes use `.` as the resource/action separator. CI4 splits filter strings on `:`, so `permission:users:write` parses with `users` as the only argument.

**Required hook:**

```php
abstract protected function getSecurityAuditLogger(): ?SecurityAuditLoggerInterface;
```

**Optional hooks:**

```php
protected function unauthenticatedMessage(): string;   // default: lang('Auth.authRequired') | 'Authentication required'
protected function forbiddenMessage(): string;         // default: lang('Auth.insufficientPermissions') | 'Insufficient permissions'
```

### 3. `AbstractIamAuthorizationService` — `src/Services/Iam/AbstractIamAuthorizationService.php`

Hierarchical authorization rules for IAM operations (granting permissions, granting roles, modifying users, modifying system roles, self-protection). Use this base only if your consumer has its own IAM admin surface (managing roles, memberships, etc.). A consumer that only authenticates and authorizes regular requests does **not** need this class.

**Constructor:**

```php
public function __construct(
    protected readonly PermissionResolverInterface $resolver,
    protected readonly SecurityAuditLoggerInterface $audit,
) {}
```

**Storage hooks (subclass must implement):**

```php
abstract protected function loadRoleSystemFlag(int $roleId): bool;
abstract protected function resolvePermissionCodes(array $permissionIds): array;
abstract protected function resolveRolePermissionCodes(array $roleIds): array;
```

**Configuration hooks (override to customise):**

```php
protected function superAdminPermission(): string;   // default: 'iam.superadmin-access'
protected function defaultApplicationId(): int;      // default: 1
protected function denyLanguagePrefix(): string;     // default: 'Iam.'
protected function denyAction(): string;             // default: 'iam.authorization.denied'
```

**Public assertions** (throw `AuthorizationException` on denial):

```php
public function isSuperAdmin(?SecurityContext $context, int $applicationId = null): bool;
public function actorPermissions(?SecurityContext $context, int $applicationId = null): array;
public function subjectPermissions(int $subjectUserId, int $applicationId = null): array;
public function assertCanGrantPermissions(?SecurityContext $context, array $permissionIds, int $applicationId = null): void;
public function assertCanGrantRoles(?SecurityContext $context, array $roleIds, int $applicationId = null): void;
public function assertCanModifyRole(?SecurityContext $context, int $roleId, int $applicationId = null): void;
public function assertNotSelf(?SecurityContext $context, int $subjectUserId): void;
public function assertCanActOnSubject(?SecurityContext $context, int $subjectUserId, int $applicationId = null): void;
public function assertCanModifySubject(?SecurityContext $context, int $subjectUserId, int $applicationId = null): void;
public function assertSuperAdmin(?SecurityContext $context, int $applicationId = null): void;
```

> SuperAdmin bypasses every assert except `assertNotSelf`, which intentionally applies to everyone (prevents accidental lock-out).

### 4. Contracts — `src/Contracts/Iam/` and `src/Contracts/`

```php
// src/Contracts/Iam/PermissionResolverInterface.php
interface PermissionResolverInterface
{
    /** @return list<string> permission codes (sorted, deduplicated) */
    public function resolve(int $userId, int $applicationId): array;
    public function invalidateForUser(int $userId, int $applicationId): void;
    public function invalidateAll(): void;
}

// src/Contracts/Iam/ApplicationPermissionResolverInterface.php
interface ApplicationPermissionResolverInterface
{
    /** @return list<string> permission codes (sorted, deduplicated) */
    public function resolve(int $applicationId): array;
    public function invalidate(int $applicationId): void;
}

// src/Contracts/SecurityAuditLoggerInterface.php
interface SecurityAuditLoggerInterface
{
    public function logAuthorizationDeniedFromRequest(
        RequestInterface $request,
        string $required,
        ?string $actorContext,
        ?int $actorId,
        string $action = 'authorization_denied_permission'
    ): void;

    /** @param array<string, mixed> $details */
    public function logAuthorizationDeniedFromContext(
        string $action,
        array $details,
        ?SecurityContext $context
    ): void;

    public function logRevokedTokenReuse(
        RequestInterface $request,
        ?int $userId,
        ?string $userContext,
        string $jti
    ): void;
}
```

The audit logger contract is non-throwing by requirement: security auditing must never alter primary control flow. A null implementation is acceptable when audit is not needed.

### 5. `SecurityContext` DTO — `src/Dto/SecurityContext.php`

Immutable DTO carrying the actor's identity and effective permissions. Populated automatically by `AbstractJwtAuthFilter` via `ContextHolder` — consumers usually don't construct it manually.

```php
readonly class SecurityContext
{
    public function __construct(
        public ?int $user_id = null,
        public array $metadata = [],     // flat key-value, scalar/null only
        public array $permissions = []   // list<string>
    ) { /* runtime check rejects nested arrays/objects in $metadata */ }

    public function isUser(int $id): bool;
    public function hasPermission(string $code): bool;
    public static function anonymous(): self;
}
```

---

## How to plug in another IAM (step by step)

The minimum surface to swap in a new identity provider is **two classes + one route registration**. The optional pieces (M2M tokens, IAM admin surface) are additive.

### 1. Implement `PermissionResolverInterface`

Decide where permission codes come from for a given `(userId, applicationId)`. Examples:

- A SQL join across your role/permission tables (the starter's approach)
- A Redis cache populated by an event listener
- A remote IAM hub queried via HTTP (with local TTL cache)
- A token-embedded list (return `[]` and rely on the JWT `scope` claim — `AbstractIamAuthorizationService` will short-circuit to `$context->permissions`)

The resolver is only consulted when `$context->permissions` is empty, so a fully populated JWT scope makes the resolver effectively optional for read paths.

### 2. Subclass `AbstractJwtAuthFilter` and implement `decodeToken()`

This is where the swap actually happens. The hook receives the raw Bearer string and must return an object with `uid` / `scope` / `jti` properties (or `null` to reject). Plug in `firebase/php-jwt`, Shield's token model, an OIDC verifier, an HTTP introspection call — whatever your provider gives you.

If your provider's JWT uses different claim names (e.g. `sub` / `permissions`), translate them inside `decodeToken()` so the rest of the pipeline keeps using the contract's names.

Override `loadActor()` + `requireActorOnUserToken()` if you need to reject tokens whose user has been deleted or disabled. Override `shouldCheckRevocation()` + `isTokenRevoked()` if you maintain a revocation list (a `jti` blacklist or a `revoked_tokens` table).

Override `assertAccessPolicy()` for cross-cutting policies that depend on the actor object — e.g. "user must have verified email", "tenant must be active".

### 3. Subclass `AbstractPermissionFilter` and wire your audit logger

A 3-line subclass is enough — its only job is to inject the consumer's `SecurityAuditLoggerInterface` implementation. If you don't want audit logging, return `null` and the filter still enforces access control.

### 4. (Optional) Subclass `AbstractIamAuthorizationService`

Only if your consumer exposes IAM admin endpoints (manage roles, grant permissions, etc.). Implement the three storage hooks against your tables. Override `superAdminPermission()` and `defaultApplicationId()` if those defaults don't match your model.

### 5. Register the filters in `app/Config/Filters.php`

```php
public array $aliases = [
    // ...
    'auth'       => \YourNamespace\Filters\YourJwtAuthFilter::class,
    'permission' => \YourNamespace\Filters\YourPermissionFilter::class,
];
```

Use them in `Routes.php` exactly as the starter does:

```php
$routes->group('users', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'UserController::index', ['filter' => 'permission:users.read']);
    $routes->put('(:num)', 'UserController::update/$1', ['filter' => 'permission:users.write']);
});
```

### 6. (Optional) Service tokens (M2M)

If your provider supports machine-to-machine tokens, mint them with `uid = 0` (or omit `uid`) and a `scope` containing the application's permission codes. `AbstractJwtAuthFilter` will let them through with `user_id = null`, and `AbstractPermissionFilter` will gate them on `scope` alone. `ApplicationPermissionResolverInterface` is the contract for resolving an application's permission set when minting these tokens.

---

## Anatomy of a consumer implementation

A complete consumer ships five small classes that together cover the contract. Typical shape:

| Component | Implements | Typical decision |
|---|---|---|
| `JwtAuthFilter` | `AbstractJwtAuthFilter` | Decodes via a JWT service (e.g. firebase/php-jwt); optionally enforces actor existence and opts into revocation checks against a `revoked_tokens` table |
| `PermissionFilter` | `AbstractPermissionFilter` | Wires the consumer's `SecurityAuditLoggerInterface` implementation |
| `EffectivePermissionsResolver` (or similar) | `PermissionResolverInterface` | SQL walk such as `user_roles → roles → role_permissions → permissions` scoped by `applications.id`, or equivalent against the consumer's storage |
| `IamAuthorizationService` | `AbstractIamAuthorizationService` | Implements the three storage hooks against the consumer's RBAC tables |
| `SecurityAuditLogger` | `SecurityAuditLoggerInterface` | Writes structured rows to a `security_audit_logs` table (or pushes to an external SIEM) |

The five files together typically run under 300 lines — most of the heavy lifting lives in the abstract bases.

---

## Integration checklist

- [ ] JWT exposes `uid` and `scope` (or `decodeToken()` translates the provider's claim names to those)
- [ ] `PermissionResolverInterface` implementation returns sorted, deduplicated permission codes
- [ ] `AbstractJwtAuthFilter` subclass registered as the `auth` filter alias
- [ ] `AbstractPermissionFilter` subclass registered as the `permission` filter alias
- [ ] `SecurityAuditLoggerInterface` implementation wired (or returning `null` if audit is disabled)
- [ ] Routes use `permission:<code>` with `.` as separator (not `:`)
- [ ] `superAdminPermission()` and `defaultApplicationId()` overridden if defaults don't match your model
- [ ] Service tokens (if used) carry `scope` and either omit `uid` or set it to `0`
