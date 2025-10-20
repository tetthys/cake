<?php
// src/Rule/Pred.php

declare(strict_types=1);

namespace Tetthys\Cake\Rule;

use Closure;
use Tetthys\Cake\Contracts\DomainPredicate;
use Tetthys\Cake\Contracts\SubjectPredicate;
use Tetthys\Cake\Model\{Actor, Action, ObjectRef, Context};

/**
 * Adapters to build Subject/Domain predicates from Closures.
 * Keeps app code terse and testable.
 */
final readonly class Pred
{
    /** Build a SubjectPredicate from a Closure. */
    public static function S(Closure $fn): SubjectPredicate
    {
        return new class($fn) implements SubjectPredicate {
            public function __construct(private Closure $fn) {}

            public function __invoke(
                Actor $u,
                Action $a,
                ObjectRef $o,
                Context $c,
            ): bool {
                return ($this->fn)($u, $a, $o, $c);
            }
        };
    }

    /** Build a DomainPredicate from a Closure. */
    public static function D(Closure $fn): DomainPredicate
    {
        return new class($fn) implements DomainPredicate {
            public function __construct(private Closure $fn) {}

            public function __invoke(
                Actor $u,
                Action $a,
                ObjectRef $o,
                Context $c,
            ): bool {
                return ($this->fn)($u, $a, $o, $c);
            }
        };
    }
}
