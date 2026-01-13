<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel\Responders;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Tetthys\Cake\Engine\Decision;
use Tetthys\Cake\Integration\Laravel\Contracts\AuthorizationResponder;
use Tetthys\Cake\Model\Action;
use Tetthys\Cake\Model\Context;
use Tetthys\Cake\Model\ObjectRef;

final class DefaultJson403Responder implements AuthorizationResponder
{
    public function respond(
        Request $request,
        Action $action,
        ObjectRef $object,
        Context $context,
        Decision $decision,
    ): mixed {
        if ($decision->isPermit()) {
            return $decision;
        }

        $debug = (bool) config('app.debug');

        $payload = [
            "message" => "Forbidden",
            "authorization" => [
                "action" => $action->name, // assuming Action exposes ->name
            ],
        ];

        if ($debug) {
            $payload["authorization"]["trace"] = $decision->trace ?? null;
            $payload["authorization"]["context"] = [
                "route" => $context->get("route"),
                "method" => $context->get("method"),
                "path" => $context->get("path"),
                "resources" => array_map(
                    static fn($r) => (string) $r,
                    $context->get("resources", []),
                ),
                "parents" => array_map(
                    static fn($r) => (string) $r,
                    $context->get("parents", []),
                ),
            ];
        }

        throw new HttpResponseException(
            response()->json($payload, 403),
        );
    }
}
