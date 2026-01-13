<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tetthys\Cake\Rule\RuleSet;

final class MiddlewareParser
{
    /** @var list<string> */
    private array $policyNamespaces;

    /** @var callable(string):bool */
    private $classExists;

    /** @var callable(string):object */
    private $makePolicy;

    /** @var callable(string):string */
    private $transformCandidate;

    public function __construct(
        ?array $policyNamespaces = null,
        ?callable $classExists = null,
        ?callable $makePolicy = null,
        ?callable $transformCandidate = null,
    ) {
        $extra = config('cake.policy_namespaces', []);
        $extra = is_array($extra) ? $extra : [];

        $policyNamespaces ??= array_merge(['App\\Policies'], $extra);

        // English comment: Normalize namespaces (unique, non-empty, keep order)
        $seen = [];
        $clean = [];
        foreach ($policyNamespaces as $ns) {
            if (!is_string($ns) || $ns === '') {
                continue;
            }
            if (isset($seen[$ns])) {
                continue;
            }
            $seen[$ns] = true;
            $clean[] = $ns;
        }

        $this->policyNamespaces = $clean !== [] ? $clean : ['App\\Policies'];

        $this->classExists = $classExists
            ?? static fn(string $class): bool => class_exists($class);

        $this->makePolicy = $makePolicy
            ?? static fn(string $class): object => app($class);

        $this->transformCandidate = $transformCandidate
            ?? static fn(string $candidate): string => $candidate;
    }

    /**
     * Resolve route-bound objects in parameter order.
     *
     * @return list<object>
     */
    public function resolveObjectsFromRoute(Request $request): array
    {
        $params = $request->route()?->parameters() ?? [];
        $out = [];

        foreach ($params as $v) {
            if (is_object($v)) {
                $out[] = $v;
            }
        }

        return $out;
    }

    /**
     * Primary object = last route object (nested resources).
     */
    public function resolvePrimaryObjectFromRoute(Request $request): ?object
    {
        $objects = $this->resolveObjectsFromRoute($request);
        return $objects !== [] ? $objects[array_key_last($objects)] : null;
    }

    /**
     * Infer policy + method and return RuleSet.
     * Uses Laravel container call() for automatic dependency injection.
     */
    public function resolveRulesAuto(
        Request $request,
        string $action,
        mixed $object = null,
    ): RuleSet {
        [$resourceStudly, $method] = $this->splitAction($action);
        $method ??= 'index';

        $candidates = $this->policyCandidates($object, $resourceStudly);

        if ($candidates === []) {
            throw new \InvalidArgumentException(
                sprintf('[Cake] Cannot infer policy for action "%s".', $action),
            );
        }

        $exists = $this->classExists;
        $make = $this->makePolicy;
        $transform = $this->transformCandidate;

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

            // English comment: Use container call for Laravel-style auto injection
            $rules = app()->call([$policy, $method], [
                'request' => $request,
                'object'  => $object,
                'action'  => $action,
            ]);

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
                '[Cake] No matching policy method for action "%s". Tried: %s',
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
            $method !== '' ? $method : null,
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

    /**
     * Build policy class candidates with minimal allocations.
     *
     * @return list<string>
     */
    private function policyCandidates(mixed $object, ?string $resourceStudly): array
    {
        $out = [];
        $seen = [];

        if ($object !== null) {
            $base = class_basename($object);
            if (is_string($base) && $base !== '') {
                foreach ($this->policyNamespaces as $ns) {
                    $c = $ns . '\\' . $base . 'Rules';
                    if (!isset($seen[$c])) {
                        $seen[$c] = true;
                        $out[] = $c;
                    }
                }
            }
        }

        if ($resourceStudly !== null && $resourceStudly !== '') {
            foreach ($this->policyNamespaces as $ns) {
                $c = $ns . '\\' . $resourceStudly . 'Rules';
                if (!isset($seen[$c])) {
                    $seen[$c] = true;
                    $out[] = $c;
                }
            }
        }

        return $out;
    }
}
