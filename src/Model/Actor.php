<?php

declare(strict_types=1);

namespace Tetthys\Cake\Model;

/**
 * Lightweight actor wrapper. Keep it framework-agnostic.
 * 'id' is scalar-ish (string|int). 'roles' is a set-like list of strings.
 * 'attrs' are arbitrary subject attributes (department, flags, etc.).
 */
final class Actor
{
    public function __construct(
        public readonly string|int $id,
        public readonly array $roles = [],
        public readonly array $attrs = []
    ) {}
}
