<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel;

use Closure;
use Illuminate\Http\Request;
use Tetthys\Cake\Rule\RuleSet;

/**
 * Example Middleware signature:
 *   ->middleware('cake:order.approve,App\\Policies\\OrderRules@approve')
 *
 * The rules factory (callable string) must return a RuleSet for the request.
 */
final class AuthorizationMiddleware
{
    public function handle(Request $request, Closure $next, string $actionName, string $rulesFactory)
    {
        // Resolve the rules factory like "App\Policies\OrderRules@approve"
        if (!str_contains($rulesFactory, '@')) {
            abort(500, 'Invalid rules factory string.');
        }
        [$class, $method] = explode('@', $rulesFactory, 2);

        /** @var object $factory */
        $factory = app($class);
        if (!method_exists($factory, $method)) {
            abort(500, 'Rules factory method not found.');
        }

        /** @var RuleSet $rules */
        $rules = $factory->$method($request);

        // Guess object from route model binding (first model argument)
        $object = collect($request->route()?->parameters() ?? [])
            ->first(fn($p) => $p instanceof \Illuminate\Database\Eloquent\Model) 
            ?? (object)[];

        // Use trait to perform decision & throw 403 if denied
        $trait = new class { use AuthorizesRequest; };
        $trait->authorizeWithCake($request, $actionName, $object, $rules);

        return $next($request);
    }
}
