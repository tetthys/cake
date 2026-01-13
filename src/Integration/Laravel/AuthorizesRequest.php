<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tetthys\Cake\Engine\Engine;
use Tetthys\Cake\Integration\Laravel\Contracts\ActorResolver;
use Tetthys\Cake\Integration\Laravel\Contracts\AuthorizationResponder;
use Tetthys\Cake\Model\Action;
use Tetthys\Cake\Model\Context;
use Tetthys\Cake\Model\ObjectRef;
use Tetthys\Cake\Rule\RuleSet;

trait AuthorizesRequest
{
    public function authorizeWithCake(
        Request $request,
        string $actionName,
        mixed $object,
        RuleSet $rules,
    ): mixed {
        /** @var Engine $engine */
        $engine = app(Engine::class);

        /** @var ActorResolver $resolver */
        $resolver = app(ActorResolver::class);
        $actor = $resolver->fromRequest($request);

        /** @var AuthorizationResponder $responder */
        $responder = app(AuthorizationResponder::class);

        $action = new Action($actionName);

        // Primary object only (no nested input).
        $primaryRef = $this->toObjectRef($object);

        // Build context with multi-object chain from route.
        $context = $this->buildContextFromRequest($request);

        $decision = $engine->decide($actor, $action, $primaryRef, $context, $rules);

        return $responder->respond($request, $action, $primaryRef, $context, $decision);
    }

    private function buildContextFromRequest(Request $request): Context
    {
        $parser = app(MiddlewareParser::class);

        $route = $request->route();
        $routeName = $route instanceof Route ? $route->getName() : null;
        $routeParams = $route instanceof Route ? $route->parameters() : [];
        $routeParamNames = $route instanceof Route ? $route->parameterNames() : [];

        $objects = $parser->resolveObjectsFromRoute($request);
        $resourceRefs = array_map(fn($o) => $this->toObjectRef($o), $objects);

        return (new Context([
            'ip' => $request->ip(),
            'now' => now()->toIso8601String(),
        ]))->with([
            // Multi-object chain (route resource chain)
            'resources' => $resourceRefs,
            'parents' => array_slice($resourceRefs, 0, -1),

            // Useful request/route metadata
            'route' => $routeName,
            'route_params' => $routeParams,
            'route_param_names' => $routeParamNames,
            'method' => $request->method(),
            'path' => $request->path(),
        ]);
    }

    private function toObjectRef(mixed $object): ObjectRef
    {
        return new ObjectRef(
            $this->objectTypeForCake($object),
            $object,
        );
    }

    /**
     * Determine the ObjectRef type string.
     * Default: Eloquent -> table name, otherwise -> debug type.
     */
    protected function objectTypeForCake(mixed $object): string
    {
        if ($object instanceof Model) {
            return $object->getTable();
            // Alternative (polymorphic-friendly):
            // return $object->getMorphClass();
        }

        return get_debug_type($object);
    }
}
