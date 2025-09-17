<?php

declare(strict_types=1);

namespace Tetthys\Cake\Contracts;

use Tetthys\Cake\Model\Actor;
use Tetthys\Cake\Model\Action;
use Tetthys\Cake\Model\ObjectRef;
use Tetthys\Cake\Model\Context;

/**
 * DomainPredicate represents D(u,a,o,c) -> bool.
 * It captures domain/business constraints (object state, relationships, timing).
 */
interface DomainPredicate
{
    public function __invoke(Actor $u, Action $a, ObjectRef $o, Context $c): bool;
}
