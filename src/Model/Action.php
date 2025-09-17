<?php

declare(strict_types=1);

namespace Tetthys\Cake\Model;

/**
 * Action is a tiny value-object (e.g., 'order.approve', 'order.cancel').
 */
final class Action
{
    public function __construct(public readonly string $name) {}

    public function equals(string $other): bool
    {
        return $this->name === $other;
    }
}
