<?php

namespace Tests\Support;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Auth\Authenticatable;

/**
 * Minimal user stub for actingAs().
 * - Provides id and roles accessors for DefaultActorResolver.
 */
final class FakeUser implements AuthenticatableContract
{
    use Authenticatable;

    public function __construct(
        public string $id,
        /** @var array<string> */
        public array $roles = ["user"],
    ) {}

    public function getAuthIdentifierName()
    {
        return "id";
    }

    public function getAuthIdentifier()
    {
        return $this->id;
    }

    // Expose roles in multiple common patterns
    public function getRoleNames()
    {
        return collect($this->roles);
    }

    public function roles()
    {
        return $this->roles;
    }
}
