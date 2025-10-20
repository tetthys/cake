<?php

declare(strict_types=1);

namespace Tetthys\Cake\Rule;

use Tetthys\Cake\Contracts\DomainPredicate;
use Tetthys\Cake\Contracts\SubjectPredicate;
use Tetthys\Cake\Model\Action;
use Tetthys\Cake\Model\Actor;
use Tetthys\Cake\Model\Context;
use Tetthys\Cake\Model\ObjectRef;

/**
 * A single disjunct in R = OR_i (Si ∧ Di).
 * If S ∧ D evaluates to true, this rule grants PERMIT.
 */
final readonly class Rule
{
    public function __construct(
        /** Human-readable identifier for audit/explanations. */
        public string $name,
        private SubjectPredicate $S,
        private DomainPredicate $D,
        /** Optional reason string to describe the rule. */
        public ?string $reason = null,
    ) {}

    public function matches(Actor $u, Action $a, ObjectRef $o, Context $c): bool
    {
        // (S ∧ D)
        return ($this->S)($u, $a, $o, $c) && ($this->D)($u, $a, $o, $c);
    }
}
