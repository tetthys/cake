<?php

declare(strict_types=1);

namespace Tetthys\Cake\Model;

/**
 * Immutable, framework-agnostic subject model.
 *
 * @psalm-type ActorAttrs = array<string, mixed>
 */
final readonly class Actor
{
    /**
     * @param string|int|null $id
     * @param string[]        $roles
     * @param ActorAttrs      $attrs
     */
    public function __construct(
        public string|int|null $id,
        public array $roles = [],
        public array $attrs = [],
    ) {}

    // English comment: Convenient helper for auth checks
    public function isAuthenticated(): bool
    {
        return $this->id !== null;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function hasAnyRole(string ...$roles): bool
    {
        return (bool) array_intersect($this->roles, $roles);
    }

    public function hasAllRoles(string ...$roles): bool
    {
        return empty(array_diff($roles, $this->roles));
    }

    public function attr(string $key, mixed $default = null): mixed
    {
        return $this->attrs[$key] ?? $default;
    }

    public function hasAttr(string $key): bool
    {
        return array_key_exists($key, $this->attrs);
    }

    public function allAttrs(): array
    {
        return $this->attrs;
    }

    public function __toString(): string
    {
        $roles = implode(",", $this->roles);
        return sprintf("Actor(%s)[%s]", (string) ($this->id ?? 'guest'), $roles);
    }
}
