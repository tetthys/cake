<?php

declare(strict_types=1);

namespace Tetthys\Cake\Engine;

use Tetthys\Cake\Model\Actor;
use Tetthys\Cake\Model\Action;
use Tetthys\Cake\Model\ObjectRef;
use Tetthys\Cake\Model\Context;
use Tetthys\Cake\Rule\RuleSet;

/**
 * Engine evaluates: R = OR_i (Si ∧ Di)
 * If any rule matches => PERMIT, else DENY (deny-by-default).
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

        foreach ($rules as $rule) {
            $ok = $rule->matches($u, $a, $o, $c);
            $trace[] = sprintf("[%s] %s", $rule->name, $ok ? "match" : "no-match");

            if ($ok) {
                return Decision::permit($rule->name, $trace);
            }
        }

        // Deny-by-default: if no (S ∧ D) branch matches.
        return Decision::deny($trace);
    }
}
