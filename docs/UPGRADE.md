# Upgrade Guide

## v0.2.0 → v0.3.0

**Type:** Breaking — scaffolding code extracted; two sets of procedural helpers removed.

### 1. Add `ci4-api-scaffolding` to `require-dev`

All scaffolding code (`src/Commands/`, `src/Generators/`, `src/Core/`, `src/Orchestration/`, `src/Validators/`, `src/Wiring/`, `src/Config/`, `bin/`) was moved to the new companion package. If your project calls `make:crud`, `make:crud:remove`, `module:check`, or `make-crud.sh`, add the package:

```bash
composer require --dev dcardenasl/ci4-api-scaffolding:dev-main
```

### 2. Update `Config\Scaffolding` namespace

Change the import in `app/Config/Scaffolding.php`:

```php
// Before (0.2.0)
use dcardenasl\Ci4ApiCore\Config\BaseScaffoldingConfig;
use dcardenasl\Ci4ApiCore\Config\ScaffoldingConfig;

// After (0.3.0)
use dcardenasl\Ci4ApiScaffolding\Config\BaseScaffoldingConfig;
use dcardenasl\Ci4ApiScaffolding\Config\ScaffoldingConfig;
```

### 3. Replace removed procedural helpers

`src/Helpers/request.php` and `src/Helpers/security.php` were removed. Use the namespaced classes instead:

**Request helpers:**

| Before | After |
|--------|-------|
| `require_id($request)` | `RequestHelper::requireId($request)` |
| `require_fields($data, $fields)` | `RequestHelper::requireFields($data, $fields)` |
| `get_int($data, $key)` | `RequestHelper::getInt($data, $key)` |
| `get_bool($data, $key)` | `RequestHelper::getBool($data, $key)` |
| `get_string($data, $key)` | `RequestHelper::getString($data, $key)` |
| `get_array($data, $key)` | `RequestHelper::getArray($data, $key)` |
| `pick_fields($data, $keys)` | `RequestHelper::pickFields($data, $keys)` |
| `filter_null($data)` | `RequestHelper::filterNull($data)` |
| `filter_empty($data)` | `RequestHelper::filterEmpty($data)` |
| `get_pagination_params($request)` | `RequestHelper::getPaginationParams($request)` |

Import: `use dcardenasl\Ci4ApiCore\Request\RequestHelper;`

**Security helpers:**

| Before | After |
|--------|-------|
| `hash_password($plain)` | `Hasher::hashPassword($plain)` |
| `verify_password($plain, $hash)` | `Hasher::verifyPassword($plain, $hash)` |
| `generate_token()` | `Token::generate()` |
| `hash_token($token)` | `Token::hash($token)` |
| `generate_api_key()` | `Token::generateApiKey()` |
| `hash_api_key($key)` | `Token::hashApiKey($key)` |
| `generate_uuid()` | `Token::generateUuid()` |
| `constant_time_compare($a, $b)` | `Token::constantTimeCompare($a, $b)` |
| `sanitize_filename($name)` | `Mask::sanitizeFilename($name)` |
| `mask_string($str, $visible)` | `Mask::maskString($str, $visible)` |
| `mask_email($email)` | `Mask::maskEmail($email)` |
| `generate_otp($length)` | `Token::generateOtp($length)` |
| `is_email_verification_required()` | `ApiConfigFacade::isEmailVerificationRequired()` |

Imports:
```php
use dcardenasl\Ci4ApiCore\Security\Hasher;
use dcardenasl\Ci4ApiCore\Security\Token;
use dcardenasl\Ci4ApiCore\Security\Mask;
use dcardenasl\Ci4ApiCore\Support\ApiConfigFacade;
```

### 4. Migrate `OperationResult::$state` comparisons

`OperationResult::$state` changed from `string` to the `OperationState` enum:

```php
// Before (0.2.0)
if ($result->state === 'success') { ... }
if ($result->state === 'error') { ... }

// After (0.3.0)
if ($result->state === OperationState::SUCCESS) { ... }
if ($result->state === OperationState::ERROR) { ... }
// Transitional (both work):
if ($result->state->value === 'success') { ... }
```

Named factory methods and `isError()` / `isAccepted()` are unaffected.

Import: `use dcardenasl\Ci4ApiCore\Support\OperationState;`

### 5. Update `AuditPayloadSanitizer` constructor (if used directly)

The constructor parameter was renamed from `$sensitiveFields` to `$additionalSensitiveFields`. Callers adding extra fields no longer need to include the full default list — only the extras:

```php
// Before (0.2.0) — had to copy the full default list
new AuditPayloadSanitizer(['password', 'token', 'secret', 'myExtraField']);

// After (0.3.0) — pass only the extras; defaults are merged automatically
new AuditPayloadSanitizer(additionalSensitiveFields: ['myExtraField']);
```

### 6. Update custom paginated DTOs

If you have custom DTOs that return `data/total/page/per_page` keys but do not extend `PaginatedResponseDTO`, implement `PaginatableResponse` to restore paginated response handling:

```php
use dcardenasl\Ci4ApiCore\Contracts\PaginatableResponse;

class MyCustomListDTO implements DataTransferObjectInterface, PaginatableResponse
{
    // ...
}
```

### 7. Update `BaseRequestDTO` manual instantiation (if any)

`BaseRequestDTO::validate()` no longer falls back to `service('validation')`. If you instantiate DTOs directly outside of `RequestDtoFactory`, pass a `ValidationInterface`:

```php
// Before (0.2.0) — service() fallback
$dto = new MyRequestDTO($data);

// After (0.3.0) — explicit injection
$dto = new MyRequestDTO($data, service('validation'));
// or via factory (preferred — no change needed):
$dto = $factory->make(MyRequestDTO::class, $data);
```

### Summary checklist

- [ ] `composer require --dev dcardenasl/ci4-api-scaffolding:dev-main`
- [ ] Update `Config\Scaffolding` namespace
- [ ] Replace `request.php` helper calls with `RequestHelper::`
- [ ] Replace `security.php` helper calls with `Hasher::`, `Token::`, `Mask::`, `ApiConfigFacade::`
- [ ] Update `$result->state === 'success'` comparisons to use `OperationState` enum
- [ ] Update `AuditPayloadSanitizer` constructor if using named argument
- [ ] Implement `PaginatableResponse` on custom paginated DTOs
- [ ] Update manual `BaseRequestDTO` instantiation to pass `ValidationInterface`
- [ ] Run `composer quality` to confirm no regressions
