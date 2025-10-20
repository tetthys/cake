<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel\Contracts;

use Illuminate\Http\Request;
use Tetthys\Cake\Model\Action;
use Tetthys\Cake\Model\Context;
use Tetthys\Cake\Model\ObjectRef;
use Tetthys\Cake\Engine\Decision;

/**
 * AuthorizationResponder
 *
 * Handle the final step of authorization:
 * - If permitted, may return the Decision as-is (or decorate it).
 * - If denied, may throw/return a response/exception (developer's choice).
 *
 * Implementations are free to:
 *   - Throw HttpResponseException / AuthorizationException
 *   - Return a Laravel Response / JsonResponse
 *   - Log/Audit the decision trace, etc.
 */
interface AuthorizationResponder
{
    /**
     * Handle an authorization decision.
     *
     * @return mixed
     *   Suggested:
     *   - Permit: return $decision (or a decorated value)
     *   - Deny: throw or return a Response (implementation-defined)
     */
    public function respond(
        Request $request,
        Action $action,
        ObjectRef $object,
        Context $context,
        Decision $decision,
    ): mixed;
}
