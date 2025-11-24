<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tetthys\Cake\Engine\Engine;
use Tetthys\Cake\Integration\Laravel\Contracts\ActorResolver;
use Tetthys\Cake\Model\{Action, Context, ObjectRef};
use Tetthys\Cake\Rule\RuleSet;

/**
 * cake - minimal and flexible permission helper.
 *
 * @param string $action  e.g. 'post.update'
 * @param mixed $object   domain object (Eloquent, DTO, stdClass)
 * @param RuleSet|callable|string|null $rules
 */
function cake(
    string $action,
    mixed $object = null,
    mixed $rules = null,
    ?Request $request = null,
    ?Engine $engine = null,
    ?ActorResolver $resolver = null,
    ?Container $container = null,
): bool {
    $container ??= app();
    $request ??= $container->make(Request::class);
    $engine ??= $container->make(Engine::class);
    $resolver ??= $container->make(ActorResolver::class);

    return decideCake($engine, $resolver, $request, $action, $object, $rules);
}

function decideCake(
    Engine $engine,
    ActorResolver $resolver,
    Request $request,
    string $action,
    mixed $object,
    mixed $rules,
): bool {
    return $engine
        ->decide(
            $resolver->fromRequest($request),
            new Action($action),
            buildObjectRef($object),
            buildContext($request),
            resolveCakeRules($rules, $request, $action, $object),
        )
        ->isPermit();
}

function buildObjectRef(mixed $object): ObjectRef
{
    return new ObjectRef(
        $object instanceof \Illuminate\Database\Eloquent\Model
            ? $object->getTable()
            : get_debug_type($object),
        $object,
    );
}

function buildContext(Request $request): Context
{
    return new Context([
        'ip' => $request->ip(),
        'now' => now()->toISOString(),
    ]);
}

/**
 * Resolve RuleSet input (RuleSet | callable | 'Class@method' | null)
 */
function resolveCakeRules(
    mixed $rules,
    Request $request,
    string $action,
    mixed $object,
): RuleSet {
    if ($rules instanceof RuleSet) {
        return $rules;
    }

    if (is_callable($rules)) {
        $result = $rules($request);
        return $result instanceof RuleSet
            ? $result
            : throw new \TypeError("callable must return RuleSet");
    }

    if (is_string($rules)) {
        [$class, $method] = cakeParseRulesFactory($rules);
        $instance = app($class);
        $result = $instance->{$method}($request);
        return $result instanceof RuleSet
            ? $result
            : throw new \TypeError("{$class}@{$method} must return RuleSet");
    }

    [$class, $method] = cakeInferPolicy($action, $object);
    $policy = app($class);
    $result = $policy->{$method}($request);

    return $result instanceof RuleSet
        ? $result
        : throw new \TypeError("{$class}@{$method} must return RuleSet");
}

/** @return array{0:string,1:string} */
function cakeParseRulesFactory(string $factory): array
{
    if (!str_contains($factory, "@")) {
        throw new \InvalidArgumentException('Invalid rules factory string; expected "Class@method".');
    }

    [$class, $method] = explode("@", $factory, 2);

    if ($class === '' || $method === '') {
        throw new \InvalidArgumentException('Invalid rules factory string; both class and method are required.');
    }

    return [$class, $method];
}

/** @return array{0:?string,1:?string} */
function cakeSplitAction(string $action): array
{
    if (!str_contains($action, ".")) {
        return [null, null];
    }

    [$resource, $method] = explode(".", $action, 2);

    return [$resource ?: null, $method ?: null];
}

/** @return list<string> */
function cakePolicyCandidates(mixed $object, ?string $resource): array
{
    $candidates = [];

    if ($object !== null) {
        $base = class_basename(
            is_object($object)
                ? $object
                : (string) $object,
        );

        if ($base) {
            $candidates[] = "App\\Policies\\{$base}Rules";
        }
    }

    if ($resource) {
        $candidates[] = "App\\Policies\\" . Str::studly($resource) . "Rules";
    }

    return array_values(array_unique($candidates));
}

/** @return array{0:string,1:string} */
function cakeInferPolicy(string $action, mixed $object = null): array
{
    [$resource, $actionMethod] = cakeSplitAction($action);
    $method = $actionMethod ?: 'index';

    $candidates = cakePolicyCandidates($object, $resource);

    if (!$candidates) {
        throw new \InvalidArgumentException(
            sprintf(
                '[Cake] Cannot infer policy class for action "%s". Provide an object/resource or explicit rules.',
                $action,
            ),
        );
    }

    foreach ($candidates as $class) {
        if (class_exists($class)) {
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
