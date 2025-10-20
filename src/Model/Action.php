<?php

declare(strict_types=1);

namespace Tetthys\Cake\Model;

/**
 * Action is a tiny immutable value-object (e.g., 'order.approve', 'order.cancel').
 */
final readonly class Action
{
    public function __construct(public string $name) {}

    /** Check equality with another action or name. */
    public function equals(string|self $other): bool
    {
        return $this->name === ($other instanceof self ? $other->name : $other);
    }

    /** Alias for equals(). */
    public function is(string|self $other): bool
    {
        return $this->equals($other);
    }

    /** Get the domain prefix (e.g., 'order' from 'order.cancel'). */
    public function domain(): ?string
    {
        return str_contains($this->name, ".")
            ? explode(".", $this->name, 2)[0]
            : null;
    }

    /** Get the verb part (e.g., 'cancel' from 'order.cancel'). */
    public function verb(): string
    {
        return str_contains($this->name, ".")
            ? explode(".", $this->name, 2)[1]
            : $this->name;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
