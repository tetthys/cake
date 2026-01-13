<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tetthys\Cake\Rule\RuleSet;

final class MiddlewareParser
{
    /** @var list<string> */
    private array $policyNamespaces;

    /** @var callable(string):bool */
    private $classExistsHook;

    /** @var callable(string):object */
    private $makePolicyHook;

    /** @var callable(string):string */
    private $candidateTransformHook;

    public function __construct(
        ?array $policyNamespaces = null,
        ?callable $classExistsHook = null,
        ?callable $makePolicyHook = null,
        ?callable $candidateTransformHook = null,
    ) {
        if ($policyNamespaces === null) {
            $extra = config('cake.policy_namespaces', []);
            $extra = is_array($extra) ? $extra : [];
            $policyNamespaces = array_merge(['App\\Policies'], $extra);
        }

        $policyNamespaces = array_values(array_unique(array_filter(
            $policyNamespaces,
            static fn($v): bool => is_string($v) && $v !== ''
        )));

        $this->policyNamespaces = $policyNamespaces ?: ['App\\Policies'];

        $this->classExistsHook = $classExistsHook
            ?? static fn(string $class): bool => class_exists($class);

        $this->makePolicyHook = $makePolicyHook
            ?? static fn(string $class): object => app($class);

        $this->candidateTransformHook = $candidateTransformHook
            ?? static fn(string $candidate): string => $candidate;
    }

    /**
     * Return route objects in route-parameter order.
     * - Prefer Eloquent models first (common case)
     * - Then other objects
     *
     * @return list<object>
     */
    public function resolveObjectsFromRoute(Request $request): array
    {
        $params = $request->route()?->parameters() ?? [];

        $models = [];
        $objects = [];

        foreach ($params as $v) {
            if ($v instanceof Model) {
                $models[] = $v;
                continue;
            }
            if (is_object($v)) {
                $objects[] = $v;
            }
        }

        // Keep order stable: models first is usually what you want for resource chains.
        // If you strictly want original parameter order, remove this split and just collect in one pass.
        return [...$models, ...$objects];
    }

    /**
     * Primary object = last resolved object (typical nested route: forum -> post, primary is post).
     */
    public function resolvePrimaryObjectFromRoute(Request $request): ?object
    {
        $objs = $this->resolveObjectsFromRoute($request);
        return $objs[array_key_last($objs)] ?? null;
    }

    /**
     * Auto-infer policy and build RuleSet.
     * IMPORTANT: choose the first candidate where BOTH class and method exist.
     */
    public function resolveRulesAuto(
        Request $request,
        string $action,
        mixed $object = null,
    ): RuleSet {
        [$resourceStudly, $actionMethod] = $this->splitAction($action);
        $method = $actionMethod ?: 'index';

        $candidates = $this->policyCandidates($object, $resourceStudly);

        if (!$candidates) {
            throw new \InvalidArgumentException(
                sprintf(
                    '[Cake] Cannot infer policy class for action "%s". Provide an object/resource.',
                    $action,
                ),
            );
        }

        $exists = $this->classExistsHook;
        $make = $this->makePolicyHook;
        $transform = $this->candidateTransformHook;

        $tried = [];

        foreach ($candidates as $candidate) {
            $class = $transform($candidate);
            $tried[] = $class . '@' . $method;

            if (!$exists($class)) {
                continue;
            }

            $policy = $make($class);

            if (!method_exists($policy, $method)) {
                continue;
            }

            $rules = $policy->{$method}($request);

            if (!$rules instanceof RuleSet) {
                throw new \TypeError(
                    sprintf(
                        'Rules factory "%s::%s" must return %s.',
                        $class,
                        $method,
                        RuleSet::class,
                    ),
                );
            }

            return $rules;
        }

        throw new \InvalidArgumentException(
            sprintf(
                '[Cake] Could not find policy+method for action "%s". Tried: %s',
                $action,
                implode(', ', $tried),
            ),
        );
    }

    /** @return array{0:?string,1:?string} */
    private function splitAction(string $action): array
    {
        if (!str_contains($action, '.')) {
            return [$this->normalizeResource($action), null];
        }

        [$resource, $method] = explode('.', $action, 2);

        return [
            $this->normalizeResource($resource),
            $method ?: null,
        ];
    }

    private function normalizeResource(?string $resource): ?string
    {
        if ($resource === null || $resource === '') {
            return null;
        }

        $resource = str_replace(['-', '_'], ' ', $resource);

        return Str::studly($resource);
    }

    /** @return list<string> */
    private function policyCandidates(mixed $object, ?string $resourceStudly): array
    {
        $candidates = [];

        if ($object !== null) {
            $base = class_basename($object);
            if ($base) {
                foreach ($this->policyNamespaces as $ns) {
                    $candidates[] = "{$ns}\\{$base}Rules";
                }
            }
        }

        if ($resourceStudly) {
            foreach ($this->policyNamespaces as $ns) {
                $candidates[] = "{$ns}\\{$resourceStudly}Rules";
            }
        }

        return array_values(array_unique($candidates));
    }
}
