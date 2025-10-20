<?php

declare(strict_types=1);

namespace Tetthys\Cake\Model;

/**
 * ObjectRef wraps an arbitrary domain object without coupling.
 * - type: e.g., 'Order'
 * - data: state snapshot or adapter
 */
final readonly class ObjectRef
{
    public function __construct(public string $type, public mixed $data) {}

    /** Alias for readability in predicates. */
    public function value(): mixed
    {
        return $this->data;
    }

    /** Attempt to fetch a property or key within the wrapped data. */
    public function get(string $key, mixed $default = null): mixed
    {
        return match (true) {
            is_array($this->data) && array_key_exists($key, $this->data) => $this
                ->data[$key],
            is_object($this->data) && isset($this->data->$key) => $this->data->$key,
            default => $default,
        };
    }

    /** Try to call a method on the wrapped object if available. */
    public function call(string $method, mixed ...$args): mixed
    {
        return is_object($this->data) && method_exists($this->data, $method)
            ? $this->data->$method(...$args)
            : null;
    }

    public function __toString(): string
    {
        return sprintf("ObjectRef(%s)", $this->type);
    }
}
