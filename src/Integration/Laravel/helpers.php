<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tetthys\Cake\Engine\Engine;
use Tetthys\Cake\Integration\Laravel\Contracts\ActorResolver;
use Tetthys\Cake\Model\{Action, Context, ObjectRef};

function cake(
    string $action,
    mixed $object = null,
    ?Request $request = null,
    ?Engine $engine = null,
    ?ActorResolver $resolver = null,
    ?Container $container = null,
): bool {
    $container ??= app();
    $request ??= $container->make(Request::class);
    $engine ??= $container->make(Engine::class);
    $resolver ??= $container->make(ActorResolver::class);
    $parser ??= $container->make(MiddlewareParser::class);

    // Primary defaults to last route object if not provided.
    if ($object === null) {
        $object = $parser->resolvePrimaryObjectFromRoute($request) ?? new \stdClass();
    }

    $ruleset = $parser->resolveRulesAuto($request, $action, $object);

    return $engine
        ->decide(
            $resolver->fromRequest($request),
            new Action($action),
            buildObjectRef($object),
            buildContext($request),
            $ruleset,
        )
        ->isPermit();
}

function buildObjectRef(mixed $object): ObjectRef
{
    return new ObjectRef(
        $object instanceof Model ? $object->getTable() : get_debug_type($object),
        $object,
    );
}

function buildContext(Request $request): Context
{
    $parser = app(MiddlewareParser::class);

    $route = $request->route();
    $routeName = $route instanceof Route ? $route->getName() : null;

    $objects = $parser->resolveObjectsFromRoute($request);
    $resourceRefs = array_map(
        static fn($o) => buildObjectRef($o),
        $objects,
    );

    return (new Context([
        'ip' => $request->ip(),
        'now' => now()->toIso8601String(),
    ]))->with([
        'resources' => $resourceRefs,
        'parents' => array_slice($resourceRefs, 0, -1),

        'route' => $routeName,
        'route_params' => $route?->parameters() ?? [],
        'route_param_names' => $route?->parameterNames() ?? [],
        'method' => $request->method(),
        'path' => $request->path(),
    ]);
}
