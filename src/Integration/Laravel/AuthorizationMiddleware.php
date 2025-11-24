<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel;

use Closure;
use Illuminate\Http\Request;
use Tetthys\Cake\Rule\RuleSet;

/**
 * Supports both:
 *  - cake:post.update,App\Policies\PostRules@update  (explicit)
 *  - cake:post.update                                (auto-infer policy)
 *
 * Auto-infer order:
 *  1) If a route-model/object exists: App\Policies\{ClassBase}Rules@{method}
 *  2) Fallback to action's domain:   App\Policies\{Studly(domain)}Rules@{method}
 */
final class AuthorizationMiddleware
{
    /**
     * @param string      $actionName   e.g. "post.update"
     * @param string|null $rulesFactory Optional "Class@method"; if null, auto-infer.
     */
    public function handle(
        Request $request,
        Closure $next,
        string $actionName,
        ?string $rulesFactory = null,
    ) {
        // 1) Resolve domain object from route (eloquent model or any object)
        $object = $this->resolveObjectFromRoute($request);

        // 2) Resolve policy class & method (explicit or auto)
        [$policyClass, $policyMethod] = $this->resolvePolicyAndMethod(
            $actionName,
            $object,
            $rulesFactory,
        );

        // 3) Build RuleSet by calling the policy factory method
        /** @var object $policy */
        $policy = app($policyClass);

        if (!method_exists($policy, $policyMethod)) {
            abort(
                500,
                sprintf(
                    'Rules factory method "%s::%s" not found.',
                    $policyClass,
                    $policyMethod,
                ),
            );
        }

        /** @var RuleSet $rules */
        $rules = $policy->{$policyMethod}($request);

        if (!$rules instanceof RuleSet) {
            abort(
                500,
                sprintf(
                    'Rules factory "%s::%s" must return %s.',
                    $policyClass,
                    $policyMethod,
                    RuleSet::class,
                ),
            );
        }

        // 4) Run decision via the reusable trait; responder will throw 403 on DENY
        $trait = new class {
            use AuthorizesRequest;
        };

        // Throws on DENY; otherwise continue.
        $trait->authorizeWithCake(
            $request,
            $actionName,
            $object ?? new \stdClass(),
            $rules,
        );

        return $next($request);
    }

    /** Prefer an Eloquent model; otherwise the first object-like route param; otherwise null. */
    private function resolveObjectFromRoute(Request $request): ?object
    {
        $params = $request->route()?->parameters() ?? [];

        // Prefer Eloquent model
        foreach ($params as $value) {
            if ($value instanceof \Illuminate\Database\Eloquent\Model) {
                return $value;
            }
        }
        // Any object
        foreach ($params as $value) {
            if (is_object($value)) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Resolve [policyClass, method] either from explicit "Class@method" or by auto-inference.
     *
     * Auto-infer:
     * - method = suffix after ".", e.g. "post.update" -> "update" (no dot => whole action)
     * - try classes in order:
     *     a) App\Policies\{class_basename(object)}Rules
     *     b) App\Policies\{Studly(domain)}Rules  (domain = prefix before ".")
     */
    private function resolvePolicyAndMethod(
        string $actionName,
        ?object $object,
        ?string $rulesFactory,
    ): array {
        if ($rulesFactory !== null) {
            try {
                return cakeParseRulesFactory($rulesFactory);
            } catch (\InvalidArgumentException $e) {
                abort(500, $e->getMessage());
            }
        }

        try {
            return cakeInferPolicy($actionName, $object);
        } catch (\InvalidArgumentException $e) {
            abort(500, $e->getMessage());
        }
    }
}
