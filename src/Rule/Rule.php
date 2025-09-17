<?php

declare(strict_types=1);

namespace Tetthys\Cake\Rule;

use Tetthys\Cake\Contracts\DomainPredicate;
use Tetthys\Cake\Contracts\SubjectPredicate;
use Tetthys\Cake\Model\Actor;
use Tetthys\Cake\Model\Action;
use Tetthys\Cake\Model\ObjectRef;
use Tetthys\Cake\Model\Context;

/**
 * A Rule is one disjunct in R = (S1 ∧ D1) ∨ (S2 ∧ D2) ∨ ... ∨ (Sn ∧ Dn).
 * If S ∧ D evaluates to true, the rule grants PERMIT with an explanatory reason.
 */
final class Rule
{
    /**
     * @param string $name A human-readable identifier for auditing/explanations.
     */
    public function __construct(
        public readonly string $name,
        private SubjectPredicate $S,
        private DomainPredicate $D,
        public readonly ?string $reason = null
    ) {}

    public function matches(Actor $u, Action $a, ObjectRef $o, Context $c): bool
    {
        return ($this->S)($u, $a, $o, $c) && ($this->D)($u, $a, $o, $c);
    }
}
