<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tetthys\Cake\Integration\Laravel\MiddlewareParser;
use Tetthys\Cake\Model\Context;
use Tetthys\Cake\Model\ObjectRef;

final class CakeRequestContext
{
    /**
     * Normalize input into: [resource chain, primary object].
     *
     * @return array{0:list<object>,1:object}
     */
    public static function normalizeObjects(
        mixed $objectOrChain,
        Request $request,
        MiddlewareParser $parser,
    ): array {
        // 1) Array => treat as explicit chain
        if (is_array($objectOrChain)) {
            $chain = [];
            foreach ($objectOrChain as $v) {
                if (is_object($v)) {
                    $chain[] = $v;
                }
            }

            if ($chain !== []) {
                $primary = $chain[array_key_last($chain)];
                return [$chain, $primary];
            }

            // Empty chain fallback to route
            return self::fromRoute($request, $parser);
        }

        // 2) Single object
        if (is_object($objectOrChain)) {
            return [[$objectOrChain], $objectOrChain];
        }

        // 3) Null/other => route fallback
        return self::fromRoute($request, $parser);
    }

    /**
     * Build Context using provided chain (do not re-resolve route objects elsewhere).
     *
     * @param list<object> $chain
     */
    public static function buildContextFromChain(
        Request $request,
        array $chain,
    ): Context {
        $route = $request->route();
        $routeName = $route instanceof Route ? $route->getName() : null;

        $resourceRefs = array_map(
            static fn(object $o) => self::buildObjectRef($o),
            $chain,
        );

        return (new Context([
            "ip" => $request->ip(),
            "now" => now()->toIso8601String(),
        ]))->with([
            "resources" => $resourceRefs,
            "parents" => array_slice($resourceRefs, 0, -1),

            "route" => $routeName,
            "route_params" => $route?->parameters() ?? [],
            "route_param_names" => $route?->parameterNames() ?? [],
            "method" => $request->method(),
            "path" => $request->path(),
        ]);
    }

    /**
     * Build an ObjectRef for Cake.
     * Default type: Eloquent table name, otherwise debug type.
     */
    public static function buildObjectRef(mixed $object): ObjectRef
    {
        return new ObjectRef(
            $object instanceof Model ? $object->getTable() : get_debug_type($object),
            $object,
        );
    }

    /**
     * Same rule as buildObjectRef(), but returns the type string only.
     */
    public static function objectType(mixed $object): string
    {
        if ($object instanceof Model) {
            return $object->getTable();
        }

        return get_debug_type($object);
    }

    /**
     * @return array{0:list<object>,1:object}
     */
    private static function fromRoute(
        Request $request,
        MiddlewareParser $parser,
    ): array {
        $chain = $parser->resolveObjectsFromRoute($request);
        $primary =
            $parser->resolvePrimaryObjectFromRoute($request) ?? new \stdClass();

        if ($chain === []) {
            $chain = [$primary];
        }

        return [$chain, $primary];
    }
}
