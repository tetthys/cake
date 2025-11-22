<?php

declare(strict_types=1);

namespace Tetthys\Cake\Integration\Laravel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
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
        [$class, $method] = explode("@", $rules, 2) + [null, null];
        $instance = app($class);
        $result = $instance->{$method}($request);
        return $result instanceof RuleSet
            ? $result
            : throw new \TypeError("{$class}@{$method} must return RuleSet");
    }

    [$resource, $actionMethod] = str_contains($action, ".")
        ? explode(".", $action, 2)
        : [null, null];
    $method = $actionMethod ?: "index";

    $base = null;
    if ($object) {
        $base = class_basename(is_object($object) ? $object : (string) $object);
    } elseif ($resource) {
        $base = \Illuminate\Support\Str::studly($resource);
    }

    if (!$base) {
        throw new \InvalidArgumentException("Cannot infer rules without object or resource");
    }

    $class = "App\\Policies\\{$base}Rules";
    $policy = app($class);
    $result = $policy->{$method}($request);

    return $result instanceof RuleSet
        ? $result
        : throw new \TypeError("{$class}@{$method} must return RuleSet");
}
