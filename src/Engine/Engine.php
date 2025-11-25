<?php

declare(strict_types=1);

namespace Tetthys\Cake\Engine;

use Tetthys\Cake\Model\Actor;
use Tetthys\Cake\Model\Action;
use Tetthys\Cake\Model\ObjectRef;
use Tetthys\Cake\Model\Context;
use Tetthys\Cake\Rule\RuleSet;
use Tetthys\Cake\Rule\RuleSetMode;

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

        // Deny immediately if no rules are present (deny-by-default).
        if ($rules->count() === 0) {
            return Decision::deny($trace);
        }

        return match ($rules->mode) {
            RuleSetMode::AllMustMatch => $this->decideAllMustMatch($u, $a, $o, $c, $rules, $trace),
            RuleSetMode::AnyMayMatch  => $this->decideAnyMayMatch($u, $a, $o, $c, $rules, $trace),
        };
    }

    /**
     * AND semantics: deny on first non-match, permit only if all match.
     *
     * @param list<string> $trace
     */
    private function decideAllMustMatch(
        Actor $u,
        Action $a,
        ObjectRef $o,
        Context $c,
        RuleSet $rules,
        array &$trace,
    ): Decision {
        foreach ($rules as $rule) {
            $ok = $rule->matches($u, $a, $o, $c);
            $trace[] = \sprintf("[%s] %s", $rule->name, $ok ? "match" : "no-match");

            if (!$ok) {
                // One rule failed -> deny.
                return Decision::deny($trace);
            }
        }

        // All rules matched -> permit.
        return Decision::permit('all-rules', $trace);
    }

    /**
     * OR semantics: permit on first match, deny if none match.
     *
     * @param list<string> $trace
     */
    private function decideAnyMayMatch(
        Actor $u,
        Action $a,
        ObjectRef $o,
        Context $c,
        RuleSet $rules,
        array &$trace,
    ): Decision {
        $anyMatched = false;

        foreach ($rules as $rule) {
            $ok = $rule->matches($u, $a, $o, $c);
            $trace[] = \sprintf("[%s] %s", $rule->name, $ok ? "match" : "no-match");

            if ($ok) {
                $anyMatched = true;

                // First matching rule -> permit, reason = rule name.
                return Decision::permit($rule->name, $trace);
            }
        }

        if (!$anyMatched) {
            // No rule matched -> deny.
            return Decision::deny($trace);
        }

        // Technically unreachable, but kept for completeness.
        return Decision::deny($trace);
    }
}
