<?php

declare(strict_types=1);

namespace Tetthys\Cake\Model;

/**
 * Context is environment info (time, ip, tenant, etc.).
 * Keep it extensible; no framework dependencies.
 */
final class Context
{
    public function __construct(public readonly array $kv = []) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->kv[$key] ?? $default;
    }
}
