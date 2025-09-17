<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Tetthys\Cake\Engine\Engine;
use Tetthys\Cake\Integration\Laravel\Contracts\ActorResolver;
use Tetthys\Cake\Model\Action;
use Tetthys\Cake\Model\Context;
use Tetthys\Cake\Model\ObjectRef;
use Tetthys\Cake\Rule\RuleSet;

trait AuthorizesRequest
{
    /** Throws 403 on DENY, returns Decision on PERMIT. */
    protected function authorizeWithCake(
        Request $request,
        string $actionName,
        mixed $object,
        RuleSet $rules
    ) {
        /** @var Engine $engine */
        $engine = app(Engine::class);

        /** @var ActorResolver $resolver */
        $resolver = app(ActorResolver::class);
        $actor    = $resolver->fromRequest($request);

        $action  = new Action($actionName);
        $object  = new ObjectRef(
            $object instanceof \Illuminate\Database\Eloquent\Model ? $object->getTable() : get_debug_type($object),
            $object
        );
        $context = new Context(['ip' => $request->ip(), 'now' => now()->toISOString()]);

        $decision = $engine->decide($actor, $action, $object, $context, $rules);

        if (!$decision->isPermit()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Forbidden',
                'authorization' => [
                    'action' => $actionName,
                    'trace'  => $decision->trace,
                ],
            ], 403));
        }
        return $decision;
    }
}
