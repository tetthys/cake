<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Tetthys\Cake\Engine\Engine;
use Tetthys\Cake\Integration\Laravel\Contracts\ActorResolver;
use Tetthys\Cake\Integration\Laravel\Support\CakeRequestContext;
use Tetthys\Cake\Model\Action;

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

    // Normalize chain + primary (shared logic)
    [$chain, $primary] = CakeRequestContext::normalizeObjects(
        $object,
        $request,
        $parser,
    );

    $ruleset = $parser->resolveRulesAuto($request, $action, $primary);

    return $engine
        ->decide(
            $resolver->fromRequest($request),
            new Action($action),
            CakeRequestContext::buildObjectRef($primary),
            CakeRequestContext::buildContextFromChain($request, $chain),
            $ruleset,
        )
        ->isPermit();
}
