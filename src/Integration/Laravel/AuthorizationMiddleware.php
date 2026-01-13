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
            $primary = $parser->resolvePrimaryObjectFromRoute($request);
            $rules   = $parser->resolveRulesAuto($request, $actionName, $primary);
        } catch (\Throwable $e) {
            abort(500, $e->getMessage());
        }

        $trait = new class {
            use AuthorizesRequest;
        };

        $trait->authorizeWithCake(
            $request,
            $actionName,
            $primary ?? new \stdClass(),
            $rules,
        );

        return $next($request);
    }
}
