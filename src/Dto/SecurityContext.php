<?php

declare(strict_types=1);

namespace dcardenasl\Ci4ApiCore\Dto;

/**
 * Security Context DTO
 *
 * Encapsulates the identity and effective permissions of the actor performing
 * an operation. Keeps business DTOs clean of session/auth metadata.
 */
readonly class SecurityContext
{
    /**
     * @param array<string, mixed> $metadata Flat key-value pairs only; nested arrays and
     *                                       objects are rejected at runtime to prevent
     *                                       accidental mutation through reference sharing.
     *                                       Values must be scalar or null.
     * @param list<string> $permissions Effective permission codes for the active application.
     */
    public function __construct(
        public ?int $user_id = null,
        public array $metadata = [],
        public array $permissions = []
    ) {
        foreach ($metadata as $key => $value) {
            if ($value !== null && !is_scalar($value)) {
                throw new \InvalidArgumentException(
                    "SecurityContext::\$metadata[\"{$key}\"] must be scalar or null; " .
                    gettype($value) . ' given.'
                );
            }
        }
    }

    /**
     * Check if the context belongs to a specific user
     */
    public function isUser(int $id): bool
    {
        return $this->user_id === $id;
    }

    /**
     * Check whether the current actor holds a specific permission.
     */
    public function hasPermission(string $code): bool
    {
        return in_array($code, $this->permissions, true);
    }

    /**
     * Create an anonymous context
     */
    public static function anonymous(): self
    {
        return new self();
    }
}
