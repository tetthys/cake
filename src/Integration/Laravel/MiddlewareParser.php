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
            fn($v) => is_string($v) && $v !== ''
        )));

        $this->policyNamespaces = $policyNamespaces ?: ['App\\Policies'];

        $this->classExistsHook = $classExistsHook
            ?? static fn(string $class): bool => class_exists($class);

        $this->makePolicyHook = $makePolicyHook
            ?? static fn(string $class): object => app($class);

        $this->candidateTransformHook = $candidateTransformHook
            ?? static fn(string $candidate): string => $candidate;
    }

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

            // 핵심 수정: 메서드 없으면 다음 후보로 넘어감
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

    /** inferPolicy는 테스트나 별도 사용처가 있으면 남겨도 되지만, 규칙 결정을 위해선 resolveRulesAuto만 쓰면 됩니다. */
    public function inferPolicy(string $action, mixed $object = null): array
    {
        [$resourceStudly, $actionMethod] = $this->splitAction($action);
        $method = $actionMethod ?: 'index';

        $candidates = $this->policyCandidates($object, $resourceStudly);
        $exists = $this->classExistsHook;
        $make = $this->makePolicyHook;
        $transform = $this->candidateTransformHook;

        foreach ($candidates as $candidate) {
            $class = $transform($candidate);

            if (!$exists($class)) {
                continue;
            }

            $policy = $make($class);
            if (!method_exists($policy, $method)) {
                continue;
            }

            return [$class, $method];
        }

        throw new \InvalidArgumentException(
            sprintf(
                '[Cake] Could not infer policy for action "%s". Tried: %s',
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
