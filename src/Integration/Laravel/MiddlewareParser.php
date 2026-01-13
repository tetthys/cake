<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

    /** @var array<string, list<string>> */
    private array $candidatesCache = [];

    /** @var array<string, string> */
    private array $winnerCache = []; // key => "Class@method"

    /** @var array<string, bool> */
    private array $classExistsCache = [];

    /** @var array<string, bool> */
    private array $methodExistsCache = [];

    /** @var array<string, object> */
    private array $policyInstanceCache = [];

    private bool $usePersistentCache;
    private string $cachePrefix;
    private int $cacheTtlSeconds;

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
            if (!is_string($ns) || $ns === '' || isset($seen[$ns])) {
                continue;
            }
            $seen[$ns] = true;
            $clean[] = $ns;
        }
        $this->policyNamespaces = $clean ?: ['App\\Policies'];

        $this->classExists = $classExists ?? static fn(string $class): bool => class_exists($class);
        $this->makePolicy = $makePolicy ?? static fn(string $class): object => app($class);
        $this->transformCandidate = $transformCandidate ?? static fn(string $candidate): string => $candidate;

        $cacheEnabled = config('cake.cache.enabled');
        $this->usePersistentCache = is_bool($cacheEnabled)
            ? $cacheEnabled
            : app()->isProduction();

        $this->cachePrefix = $this->buildDeployAwarePrefix();
        $this->cacheTtlSeconds = (int) config('cake.cache.ttl_seconds', 3600);
    }

    private function buildDeployAwarePrefix(): string
    {
        $base = (string) config('cake.cache.prefix', 'cake:policy-map:');
        $env = app()->environment();

        // English comment: Prefer deploy_id (commit hash/build id), fallback to version
        $deployId = (string) (config('app.deploy_id') ?? '');
        $version = (string) (config('app.version') ?? '');
        $deployMarker = $deployId !== '' ? $deployId : ($version !== '' ? $version : 'unknown');

        // English comment: Avoid collisions on shared cache stores
        $appKeyHash = sha1((string) config('app.key', 'nokey'));

        return $base . $env . ':' . $deployMarker . ':' . $appKeyHash;
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

    public function resolvePrimaryObjectFromRoute(Request $request): ?object
    {
        $objects = $this->resolveObjectsFromRoute($request);
        return $objects !== [] ? $objects[array_key_last($objects)] : null;
    }

    public function resolveRulesAuto(Request $request, string $action, mixed $object = null): RuleSet
    {
        [$resourceStudly, $method] = $this->splitAction($action);
        $method ??= 'index';

        $objectClass = is_object($object) ? $object::class : '';
        $winnerKey = $this->winnerKey($action, $method, $objectClass, $resourceStudly);

        // 1) Request-local fast path
        if (isset($this->winnerCache[$winnerKey])) {
            return $this->callRules($this->winnerCache[$winnerKey], $request, $action, $object);
        }

        // 2) Persistent cache fast path (enabled env only)
        if ($this->usePersistentCache) {
            $cached = Cache::get($winnerKey);
            if (is_string($cached) && str_contains($cached, '@')) {
                $this->winnerCache[$winnerKey] = $cached;
                return $this->callRules($cached, $request, $action, $object);
            }
        }

        // 3) Compute mapping
        $candidates = $this->policyCandidatesCached($object, $resourceStudly);

        if ($candidates === []) {
            throw new \InvalidArgumentException(sprintf('[Cake] Cannot infer policy for action "%s".', $action));
        }

        $tried = [];

        foreach ($candidates as $candidate) {
            $class = ($this->transformCandidate)($candidate);
            $tried[] = $class . '@' . $method;

            if (!$this->classExistsCached($class)) {
                continue;
            }

            $policy = $this->policy($class);

            if (!$this->methodExistsCached($policy, $class, $method)) {
                continue;
            }

            $winner = $class . '@' . $method;

            $this->winnerCache[$winnerKey] = $winner;

            if ($this->usePersistentCache) {
                Cache::put($winnerKey, $winner, $this->cacheTtlSeconds);
            }

            return $this->callRules($winner, $request, $action, $object);
        }

        throw new \InvalidArgumentException(
            sprintf('[Cake] No matching policy method for action "%s". Tried: %s', $action, implode(', ', $tried)),
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

    private function winnerKey(string $action, string $method, string $objectClass, ?string $resourceStudly): string
    {
        $raw = $action . '|' . $method . '|' . $objectClass . '|' . ($resourceStudly ?? '');
        return $this->cachePrefix . ':winner:' . sha1($raw);
    }

    /**
     * @return list<string>
     */
    private function policyCandidatesCached(mixed $object, ?string $resourceStudly): array
    {
        $objectClass = is_object($object) ? $object::class : '';
        $key = $objectClass . '|' . ($resourceStudly ?? '');

        if (isset($this->candidatesCache[$key])) {
            return $this->candidatesCache[$key];
        }

        $out = [];
        $seen = [];

        if ($objectClass !== '') {
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

        return $this->candidatesCache[$key] = $out;
    }

    private function classExistsCached(string $class): bool
    {
        return $this->classExistsCache[$class]
            ??= ($this->classExists)($class);
    }

    private function policy(string $class): object
    {
        return $this->policyInstanceCache[$class]
            ??= ($this->makePolicy)($class);
    }

    private function methodExistsCached(object $policy, string $class, string $method): bool
    {
        $key = $class . '::' . $method;

        return $this->methodExistsCache[$key]
            ??= method_exists($policy, $method);
    }

    private function callRules(string $winner, Request $request, string $action, mixed $object): RuleSet
    {
        [$class, $method] = explode('@', $winner, 2);

        if (!$this->classExistsCached($class)) {
            $this->forgetWinnerKey($action, $method, is_object($object) ? $object::class : '', null);
            throw new \InvalidArgumentException(sprintf('[Cake] Policy class missing: %s', $class));
        }

        $policy = $this->policy($class);

        if (!$this->methodExistsCached($policy, $class, $method)) {
            $this->forgetWinnerKey($action, $method, is_object($object) ? $object::class : '', null);
            throw new \InvalidArgumentException(sprintf('[Cake] Policy method missing: %s::%s', $class, $method));
        }

        $rules = app()->call([$policy, $method], [
            'request' => $request,
            'object'  => $object,
            'action'  => $action,
        ]);

        if (!$rules instanceof RuleSet) {
            throw new \TypeError(sprintf('Rules factory "%s::%s" must return %s.', $class, $method, RuleSet::class));
        }

        return $rules;
    }

    private function forgetWinnerKey(string $action, string $method, string $objectClass, ?string $resourceStudly): void
    {
        $key = $this->winnerKey($action, $method, $objectClass, $resourceStudly);

        unset($this->winnerCache[$key]);

        if ($this->usePersistentCache) {
            Cache::forget($key);
        }
    }
}
