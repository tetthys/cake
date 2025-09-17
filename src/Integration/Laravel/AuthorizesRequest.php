<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Tetthys\Cake\Engine\Engine;
use Tetthys\Cake\Model\Actor;
use Tetthys\Cake\Model\Action;
use Tetthys\Cake\Model\ObjectRef;
use Tetthys\Cake\Model\Context;
use Tetthys\Cake\Rule\RuleSet;

/**
 * Drop-in trait for controllers/services to authorize via Engine.
 * You provide: action name, object (model), ruleset and optional extra context.
 */
trait AuthorizesRequest
{
    /** Throws 403 on DENY, returns Decision on PERMIT. */
    protected function authorizeWithCake(
        Request $request,
        string $actionName,
        mixed $object,
        RuleSet $rules
    ) {
        $engine = app(Engine::class);

        $actor   = new Actor(id: (string)($request->user()?->getAuthIdentifier() ?? 'guest'),
            roles: (array)($request->user()?->roles?->toArray() ?? []),
            attrs: ['is_authenticated' => (bool)$request->user()]
        );

        $action  = new Action($actionName);
        $object  = new ObjectRef($object instanceof \Illuminate\Database\Eloquent\Model ? $object->getTable() : get_debug_type($object), $object);
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
