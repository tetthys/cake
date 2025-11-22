<?php

declare(strict_types=1);

namespace Tetthys\Cake\Engine;

use Tetthys\Cake\Model\Actor;
use Tetthys\Cake\Model\Action;
use Tetthys\Cake\Model\ObjectRef;
use Tetthys\Cake\Model\Context;
use Tetthys\Cake\Rule\RuleSet;

/**
 * The Engine is responsible for making authorization decisions
 * based on the provided Actor, Action, ObjectRef, Context, and RuleSet.
 */
final class Engine
{
    public function decide(
        Actor $u,
        Action $a,
        ObjectRef $o,
        Context $c,
        RuleSet $rules,
    ): Decision {
        $trace = [];
        $evaluatedAnyRule = false;

        foreach ($rules as $rule) {
            $evaluatedAnyRule = true;
            $ok = $rule->matches($u, $a, $o, $c);
            $trace[] = sprintf("[%s] %s", $rule->name, $ok ? "match" : "no-match");

            if (!$ok) {
                return Decision::deny($trace);
            }
        }

        if (!$evaluatedAnyRule) {
            return Decision::deny($trace);
        }

        return Decision::permit('all-rules', $trace);
    }
}
