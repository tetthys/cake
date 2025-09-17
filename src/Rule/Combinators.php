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
 * Functional predicate combinators for S and D.
 * These mirror the paper's guidance to build policies compositionally. 
 * See: R = S ∧ D; multi-branch as OR over (Si ∧ Di). 
 */
final class Combinators
{
    /** @return SubjectPredicate */
    public static function S_and(SubjectPredicate $a, SubjectPredicate $b): SubjectPredicate
    {
        return new class($a, $b) implements SubjectPredicate {
            public function __construct(private SubjectPredicate $x, private SubjectPredicate $y) {}
            public function __invoke(Actor $u, Action $a, ObjectRef $o, Context $c): bool
            {
                return ($this->x)($u, $a, $o, $c) && ($this->y)($u, $a, $o, $c);
            }
        };
    }

    /** @return SubjectPredicate */
    public static function S_or(SubjectPredicate $a, SubjectPredicate $b): SubjectPredicate
    {
        return new class($a, $b) implements SubjectPredicate {
            public function __construct(private SubjectPredicate $x, private SubjectPredicate $y) {}
            public function __invoke(Actor $u, Action $a, ObjectRef $o, Context $c): bool
            {
                return ($this->x)($u, $a, $o, $c) || ($this->y)($u, $a, $o, $c);
            }
        };
    }

    /** @return SubjectPredicate */
    public static function S_not(SubjectPredicate $s): SubjectPredicate
    {
        return new class($s) implements SubjectPredicate {
            public function __construct(private SubjectPredicate $inner) {}
            public function __invoke(Actor $u, Action $a, ObjectRef $o, Context $c): bool
            {
                return !($this->inner)($u, $a, $o, $c);
            }
        };
    }

    /** @return DomainPredicate */
    public static function D_and(DomainPredicate $a, DomainPredicate $b): DomainPredicate
    {
        return new class($a, $b) implements DomainPredicate {
            public function __construct(private DomainPredicate $x, private DomainPredicate $y) {}
            public function __invoke(Actor $u, Action $a, ObjectRef $o, Context $c): bool
            {
                return ($this->x)($u, $a, $o, $c) && ($this->y)($u, $a, $o, $c);
            }
        };
    }

    /** @return DomainPredicate */
    public static function D_or(DomainPredicate $a, DomainPredicate $b): DomainPredicate
    {
        return new class($a, $b) implements DomainPredicate {
            public function __construct(private DomainPredicate $x, private DomainPredicate $y) {}
            public function __invoke(Actor $u, Action $a, ObjectRef $o, Context $c): bool
            {
                return ($this->x)($u, $a, $o, $c) || ($this->y)($u, $a, $o, $c);
            }
        };
    }

    /** @return DomainPredicate */
    public static function D_not(DomainPredicate $d): DomainPredicate
    {
        return new class($d) implements DomainPredicate {
            public function __construct(private DomainPredicate $inner) {}
            public function __invoke(Actor $u, Action $a, ObjectRef $o, Context $c): bool
            {
                return !($this->inner)($u, $a, $o, $c);
            }
        };
    }
}
