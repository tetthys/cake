<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
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
        $object instanceof \Illuminate\Database\Eloquent\Model
            ? $object->getTable()
            : get_debug_type($object),
        $object,
    );
}

function buildContext(Request $request): Context
{
    return new Context([
        'ip' => $request->ip(),
        'now' => now()->toISOString(),
    ]);
}
