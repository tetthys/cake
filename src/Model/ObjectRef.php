<?php

declare(strict_types=1);

namespace Tetthys\Cake\Model;

/**
 * ObjectRef wraps arbitrary domain object without coupling.
 * 'type' (e.g., 'Order') and 'data' for state snapshot or adapter.
 */
final class ObjectRef
{
    public function __construct(
        public readonly string $type,
        public readonly mixed $data
    ) {}
}
