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
 * cakeCan - minimal and flexible permission helper.
 *
 * @param string $action  e.g. 'post.update'
 * @param mixed $object   domain object (Eloquent, DTO, stdClass)
 * @param RuleSet|callable|string|null $rules
 */
function cakeCan(
    string $action,
    mixed $object = null,
    mixed $rules = null,
): bool {
    /** @var Container $app */
    $app = app();
    $request = $app->make(Request::class);
    $engine = $app->make(Engine::class);
    $resolver = $app->make(ActorResolver::class);

    $actor = $resolver->fromRequest($request);
    $actionV = new Action($action);
    $objectV = new ObjectRef(
        $object instanceof \Illuminate\Database\Eloquent\Model
            ? $object->getTable()
            : get_debug_type($object),
        $object,
    );
    $context = new Context([
        "ip" => $request->ip(),
        "now" => now()->toISOString(),
    ]);

    $ruleSet = resolveCakeRules($rules, $request, $action, $object);
    return $engine
        ->decide($actor, $actionV, $objectV, $context, $ruleSet)
        ->isPermit();
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

    // Auto infer from action/object
    $method = str_contains($action, ".") ? explode(".", $action, 2)[1] : "index";
    $base = $object ? class_basename($object::class ?? (string) $object) : null;
    if (!$base) {
        throw new \InvalidArgumentException("Cannot infer rules without object");
    }
    $class = "App\\Policies\\{$base}Rules";

    $policy = app($class);
    $result = $policy->{$method}($request);
    return $result instanceof RuleSet
        ? $result
        : throw new \TypeError("{$class}@{$method} must return RuleSet");
}
