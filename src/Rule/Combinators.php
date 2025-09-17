<?php
// src/Rule/Combinators.php

declare(strict_types=1);

namespace Tetthys\Cake\Rule;

use Tetthys\Cake\Contracts\DomainPredicate;
use Tetthys\Cake\Contracts\SubjectPredicate;
use Tetthys\Cake\Model\{Actor, Action, ObjectRef, Context};

/**
 * Functional combinators for Subject/Domain predicates.
 * Short-circuiting OR/AND/NOT for S and D.
 */
final class Combinators
{
    public static function S_or(SubjectPredicate ...$ps): SubjectPredicate
    {
        return new class($ps) implements SubjectPredicate {
            public function __construct(private array $ps) {}
            public function __invoke(Actor $u, Action $a, ObjectRef $o, Context $c): bool
            {
                foreach ($this->ps as $p) if ($p($u, $a, $o, $c)) return true;
                return false;
            }
        };
    }

    public static function S_and(SubjectPredicate ...$ps): SubjectPredicate
    {
        return new class($ps) implements SubjectPredicate {
            public function __construct(private array $ps) {}
            public function __invoke(Actor $u, Action $a, ObjectRef $o, Context $c): bool
            {
                foreach ($this->ps as $p) if (!$p($u, $a, $o, $c)) return false;
                return true;
            }
        };
    }

    public static function S_not(SubjectPredicate $p): SubjectPredicate
    {
        return new class($p) implements SubjectPredicate {
            public function __construct(private SubjectPredicate $p) {}
            public function __invoke(Actor $u, Action $a, ObjectRef $o, Context $c): bool
            {
                return !$this->p($u, $a, $o, $c);
            }
        };
    }

    public static function D_or(DomainPredicate ...$ps): DomainPredicate
    {
        return new class($ps) implements DomainPredicate {
            public function __construct(private array $ps) {}
            public function __invoke(Actor $u, Action $a, ObjectRef $o, Context $c): bool
            {
                foreach ($this->ps as $p) if ($p($u, $a, $o, $c)) return true;
                return false;
            }
        };
    }

    public static function D_and(DomainPredicate ...$ps): DomainPredicate
    {
        return new class($ps) implements DomainPredicate {
            public function __construct(private array $ps) {}
            public function __invoke(Actor $u, Action $a, ObjectRef $o, Context $c): bool
            {
                foreach ($this->ps as $p) if (!$p($u, $a, $o, $c)) return false;
                return true;
            }
        };
    }

    public static function D_not(DomainPredicate $p): DomainPredicate
    {
        return new class($p) implements DomainPredicate {
            public function __construct(private DomainPredicate $p) {}
            public function __invoke(Actor $u, Action $a, ObjectRef $o, Context $c): bool
            {
                return !$this->p($u, $a, $o, $c);
            }
        };
    }
}
