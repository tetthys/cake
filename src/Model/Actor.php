<?php

declare(strict_types=1);

namespace Tetthys\Cake\Model;

/**
 * Immutable, framework-agnostic subject model.
 * - id: string|int identifier
 * - roles: set-like list of strings
 * - attrs: arbitrary subject attributes (department, flags, etc.)
 *
 * @psalm-type ActorAttrs = array<string, mixed>
 */
final readonly class Actor
{
    /**
     * @param string|int $id
     * @param string[]   $roles
     * @param ActorAttrs $attrs
     */
    public function __construct(
        public string|int $id,
        public array $roles = [],
        public array $attrs = [],
    ) {}

    /** Whether this actor has a given role. */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    /** Whether this actor has any of the given roles. */
    public function hasAnyRole(string ...$roles): bool
    {
        return (bool) array_intersect($this->roles, $roles);
    }

    /** Whether this actor has all of the given roles. */
    public function hasAllRoles(string ...$roles): bool
    {
        return empty(array_diff($roles, $this->roles));
    }

    /** Retrieve an attribute value from attrs. */
    public function attr(string $key, mixed $default = null): mixed
    {
        return $this->attrs[$key] ?? $default;
    }

    /** Check whether a specific attribute exists. */
    public function hasAttr(string $key): bool
    {
        return array_key_exists($key, $this->attrs);
    }

    /** Return all attributes. */
    public function allAttrs(): array
    {
        return $this->attrs;
    }

    public function __toString(): string
    {
        $roles = implode(",", $this->roles);
        return sprintf("Actor(%s)[%s]", (string) $this->id, $roles);
    }
}
