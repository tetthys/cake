<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel;

use Closure;
use Illuminate\Http\Request;

final class AuthorizationMiddleware
{
    public function handle(
        Request $request,
        Closure $next,
        string $actionName,
    ) {
        $parser = app(MiddlewareParser::class);

        try {
            $object = $parser->resolveObjectFromRoute($request);
            $rules  = $parser->resolveRulesAuto($request, $actionName, $object);
        } catch (\Throwable $e) {
            abort(500, $e->getMessage());
        }

        $trait = new class {
            use AuthorizesRequest;
        };

        $trait->authorizeWithCake(
            $request,
            $actionName,
            $object ?? new \stdClass(),
            $rules,
        );

        return $next($request);
    }
}
