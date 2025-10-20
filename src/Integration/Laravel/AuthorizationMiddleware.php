<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Tetthys\Cake\Rule\RuleSet;

/**
 * Supports both:
 *  - cake:post.update,App\Policies\PostRules@update  (explicit)
 *  - cake:post.update                                (auto-infer policy from route model)
 */
final class AuthorizationMiddleware
{
    /**
     * @param  string       $actionName   e.g. "post.update"
     * @param  string|null  $rulesFactory Optional "Class@method" factory; if null, auto-infer.
     */
    public function handle(
        Request $request,
        Closure $next,
        string $actionName,
        ?string $rulesFactory = null,
    ) {
        // 1) Resolve domain object from route (first object-like param; eloquent or plain object)
        $object = $this->resolveObjectFromRoute($request) ?? (object) [];

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

        // This will throw on DENY (via AuthorizationResponder), otherwise proceed.
        $trait->authorizeWithCake($request, $actionName, $object, $rules);

        return $next($request);
    }

    /** Pick the first object-like route parameter (Eloquent model or any object). */
    private function resolveObjectFromRoute(Request $request): ?object
    {
        $params = $request->route()?->parameters() ?? [];

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
     * Auto-infer rules:
     * - method: suffix of actionName after ".", e.g. "post.update" -> "update"
     * - class : "App\Policies\{Base}Rules" where {Base} is class_basename($object)
     */
    private function resolvePolicyAndMethod(
        string $actionName,
        object $object,
        ?string $rulesFactory,
    ): array {
        // Explicit "Class@method"
        if ($rulesFactory !== null) {
            if (!str_contains($rulesFactory, "@")) {
                abort(500, 'Invalid rules factory string. Expected "Class@method".');
            }
            [$class, $method] = explode("@", $rulesFactory, 2);
            return [$class, $method];
        }

        // Auto method: "post.update" -> "update"
        $method = str_contains($actionName, ".")
            ? explode(".", $actionName, 2)[1]
            : $actionName;

        // Auto class: "App\Policies\{Base}Rules"
        $fqcn = $object::class;
        $base =
            ($pos = strrpos($fqcn, "\\")) === false ? $fqcn : substr($fqcn, $pos + 1);
        $class = "App\\Policies\\{$base}Rules";

        if (!class_exists($class)) {
            abort(
                500,
                sprintf(
                    'Auto-inferred policy class "%s" does not exist for action "%s". ' .
                        'Pass an explicit "Class@method" or create the policy.',
                    $class,
                    $actionName,
                ),
            );
        }

        return [$class, $method];
    }
}
