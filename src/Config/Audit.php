<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Config;

use CodeIgniter\Config\BaseConfig;

class Audit extends BaseConfig
{
    public bool $asyncEnabled = true;

    public string $queueName = 'audit';

    /** @var array<int, string> */
    public array $criticalActions = [
        'authorization_denied_role',
        'api_key_auth_failed',
        'api_key_rate_limit_exceeded',
        'revoked_token_reuse_detected',
    ];

    public int $maxPayloadBytes = 60000;

    /**
     * Optional aliases to normalize free-form entity type strings before
     * persisting (e.g. `'user' => 'users'`). Empty by default — consumers
     * declare their own mapping in `app/Config/Audit.php`.
     *
     * @var array<string, string>
     */
    public array $entityTypeAliases = [];

    /**
     * Actor (user) table metadata used by `AuditService::enrichEntities()`
     * to attach `{prefix}_email`, `{prefix}_full_name`, and `{prefix}_label`
     * onto returned audit rows. Override these in `app/Config/Audit.php`
     * when the consumer uses a different actor table or column layout.
     */
    public string $actorTable = 'users';

    public string $actorEmailColumn = 'email';

    /** @var list<string> */
    public array $actorNameColumns = ['first_name', 'last_name'];

    public string $actorTargetPrefix = 'user';
}
