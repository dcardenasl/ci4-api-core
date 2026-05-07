<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Http;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use Config\App;

class ApiRequest extends IncomingRequest
{
    private ?int $authUserId = null;
    /** @var list<string> */
    private array $authPermissions = [];
    private ?float $requestStartTime = null;
    /** @var array{limit:int,remaining:int,reset:int}|null */
    private ?array $rateLimitInfo = null;
    /** @var array{limit:int,remaining:int,reset:int}|null */
    private ?array $authRateLimitInfo = null;
    /** ID of the resolved API key (set by ThrottleFilter when X-App-Key is valid) */
    private ?int $appKeyId = null;

    public function __construct(App $config, URI $uri, $body = 'php://input', ?UserAgent $userAgent = null)
    {
        parent::__construct($config, $uri, $body, $userAgent);
    }

    /**
     * @param list<string> $permissions
     */
    public function setAuthContext(?int $user_id, array $permissions = []): void
    {
        $this->authUserId = $user_id;
        $this->authPermissions = array_values($permissions);
    }

    public function getAuthUserId(): ?int
    {
        return $this->authUserId;
    }

    /**
     * @return list<string>
     */
    public function getAuthPermissions(): array
    {
        return $this->authPermissions;
    }

    public function setRequestStartTime(float $value): void
    {
        $this->requestStartTime = $value;
    }

    public function getRequestStartTime(): ?float
    {
        return $this->requestStartTime;
    }

    /**
     * @param array{limit:int,remaining:int,reset:int} $info
     */
    public function setRateLimitInfo(array $info): void
    {
        $this->rateLimitInfo = $info;
    }

    /**
     * @return array{limit:int,remaining:int,reset:int}|null
     */
    public function getRateLimitInfo(): ?array
    {
        return $this->rateLimitInfo;
    }

    /**
     * @param array{limit:int,remaining:int,reset:int} $info
     */
    public function setAuthRateLimitInfo(array $info): void
    {
        $this->authRateLimitInfo = $info;
    }

    /**
     * @return array{limit:int,remaining:int,reset:int}|null
     */
    public function getAuthRateLimitInfo(): ?array
    {
        return $this->authRateLimitInfo;
    }

    public function setAppKeyId(?int $id): void
    {
        $this->appKeyId = $id;
    }

    public function getAppKeyId(): ?int
    {
        return $this->appKeyId;
    }
}
