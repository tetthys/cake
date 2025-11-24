<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tetthys\Cake\Rule\RuleSet;

/**
 * MiddlewareParser (auto-infer only)
 *
 * Test hooks:
 *  - $classExistsHook: callable(string $class): bool
 *  - $makePolicyHook: callable(string $class): object
 *  - $candidateTransformHook: callable(string $candidateClass): string
 */
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

    /**
     * @param list<string>|null $policyNamespaces
     * @param callable(string):bool|null $classExistsHook
     * @param callable(string):object|null $makePolicyHook
     * @param callable(string):string|null $candidateTransformHook
     */
    public function __construct(
        ?array $policyNamespaces = null,
        ?callable $classExistsHook = null,
        ?callable $makePolicyHook = null,
        ?callable $candidateTransformHook = null,
    ) {
        // Default namespaces from config (production)
        if ($policyNamespaces === null) {
            $extra = config('cake.policy_namespaces', []);
            $extra = is_array($extra) ? $extra : [];
            $policyNamespaces = array_merge(['App\\Policies'], $extra);
        }

        $policyNamespaces = array_values(array_unique(array_filter(
            $policyNamespaces,
            fn($v) => is_string($v) && $v !== ''
        )));

        $this->policyNamespaces = $policyNamespaces ?: ['App\\Policies'];

        // Default hooks (production)
        $this->classExistsHook = $classExistsHook
            ?? static fn(string $class): bool => class_exists($class);

        $this->makePolicyHook = $makePolicyHook
            ?? static fn(string $class): object => app($class);

        $this->candidateTransformHook = $candidateTransformHook
            ?? static fn(string $candidate): string => $candidate;
    }

    /**
     * Convenience factory for tests (optional).
     *
     * @param list<string> $policyNamespaces
     */
    public static function forTesting(
        array $policyNamespaces = ['App\\Policies'],
        ?callable $classExistsHook = null,
        ?callable $makePolicyHook = null,
        ?callable $candidateTransformHook = null,
    ): self {
        return new self(
            policyNamespaces: $policyNamespaces,
            classExistsHook: $classExistsHook,
            makePolicyHook: $makePolicyHook,
            candidateTransformHook: $candidateTransformHook,
        );
    }

    /**
     * Prefer Eloquent model; otherwise first object-like route param; otherwise null.
     */
    public function resolveObjectFromRoute(Request $request): ?object
    {
        $params = $request->route()?->parameters() ?? [];

        foreach ($params as $v) {
            if ($v instanceof \Illuminate\Database\Eloquent\Model) {
                return $v;
            }
        }

        foreach ($params as $v) {
            if (is_object($v)) {
                return $v;
            }
        }

        return null;
    }

    /**
     * Auto-infer policy and build RuleSet.
     */
    public function resolveRulesAuto(
        Request $request,
        string $action,
        mixed $object = null,
    ): RuleSet {
        [$policyClass, $policyMethod] = $this->inferPolicy($action, $object);

        $makePolicy = $this->makePolicyHook;
        $policy = $makePolicy($policyClass);

        if (!method_exists($policy, $policyMethod)) {
            throw new \RuntimeException(
                sprintf(
                    'Rules factory method "%s::%s" not found.',
                    $policyClass,
                    $policyMethod,
                ),
            );
        }

        $rules = $policy->{$policyMethod}($request);

        if (!$rules instanceof RuleSet) {
            throw new \TypeError(
                sprintf(
                    'Rules factory "%s::%s" must return %s.',
                    $policyClass,
                    $policyMethod,
                    RuleSet::class,
                ),
            );
        }

        return $rules;
    }

    /**
     * Infer policy class & method from action/object.
     *
     * @return array{0:string,1:string}
     */
    public function inferPolicy(string $action, mixed $object = null): array
    {
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
        $transform = $this->candidateTransformHook;

        foreach ($candidates as $candidate) {
            $class = $transform($candidate);

            if ($exists($class)) {
                return [$class, $method];
            }
        }

        throw new \InvalidArgumentException(
            sprintf(
                '[Cake] Could not find policy for action "%s". Tried: %s',
                $action,
                implode(', ', $candidates),
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
