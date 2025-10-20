<?php

declare(strict_types=1);

namespace Tetthys\Cake\Model;

/**
 * Context carries environment info (time, ip, tenant, etc.).
 * Immutable by design; framework-agnostic.
 *
 * @psalm-type Ctx = array<string, mixed>
 */
final readonly class Context
{
    /**
     * @param Ctx $kv
     */
    public function __construct(public array $kv = []) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->kv[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->kv);
    }

    /** Return all key-values. */
    public function all(): array
    {
        return $this->kv;
    }

    /** Return a new Context merged with given data (immutable). */
    public function with(array $extra): self
    {
        // $extra takes precedence over existing keys.
        return new self($extra + $this->kv);
    }

    /** Convenience accessors for common fields. */
    public function ip(): ?string
    {
        return $this->kv["ip"] ?? null;
    }
    public function now(): ?string
    {
        return $this->kv["now"] ?? null;
    }
    public function tenant(): ?string
    {
        return $this->kv["tenant"] ?? null;
    }

    public function __toString(): string
    {
        return json_encode(
            $this->kv,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}
