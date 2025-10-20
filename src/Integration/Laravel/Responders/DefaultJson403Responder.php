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

/**
 * DefaultJson403Responder
 *
 * Backward-compatible default:
 * - Permit  -> return Decision
 * - Deny    -> throw HttpResponseException(JSON 403 with trace)
 */
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

        throw new HttpResponseException(
            response()->json(
                [
                    "message" => "Forbidden",
                    "authorization" => [
                        "action" => $action->name, // assuming Action exposes ->name
                        "trace" => $decision->trace, // explain why it was denied
                    ],
                ],
                403,
            ),
        );
    }
}
