<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel;

use Illuminate\Http\Request;
use Tetthys\Cake\Engine\Engine;
use Tetthys\Cake\Integration\Laravel\Contracts\ActorResolver;
use Tetthys\Cake\Integration\Laravel\Contracts\AuthorizationResponder;
use Tetthys\Cake\Integration\Laravel\Support\CakeRequestContext;
use Tetthys\Cake\Model\Action;
use Tetthys\Cake\Rule\RuleSet;

trait AuthorizesRequest
{
    public function authorizeWithCake(
        Request $request,
        string $actionName,
        mixed $object,
        RuleSet $rules,
    ): mixed {
        $engine = app(Engine::class);
        $resolver = app(ActorResolver::class);
        $responder = app(AuthorizationResponder::class);
        $parser = app(MiddlewareParser::class);

        [$chain, $primary] = CakeRequestContext::normalizeObjects(
            $object,
            $request,
            $parser,
        );

        $action = new Action($actionName);
        $primaryRef = CakeRequestContext::buildObjectRef($primary);
        $context = CakeRequestContext::buildContextFromChain($request, $chain);

        $decision = $engine->decide(
            $resolver->fromRequest($request),
            $action,
            $primaryRef,
            $context,
            $rules,
        );

        return $responder->respond(
            $request,
            $action,
            $primaryRef,
            $context,
            $decision,
        );
    }
}
