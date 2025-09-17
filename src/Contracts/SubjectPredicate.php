<?php

declare(strict_types=1);

namespace Tetthys\Cake\Contracts;

use Tetthys\Cake\Model\Actor;
use Tetthys\Cake\Model\Action;
use Tetthys\Cake\Model\ObjectRef;
use Tetthys\Cake\Model\Context;

/**
 * SubjectPredicate represents S(u,a,o,c) -> bool.
 * It captures subject-centric requirements (identity, roles, coarse capabilities).
 * This may reference object/context when modeling relations (owner-of, member-of).
 */
interface SubjectPredicate
{
    public function __invoke(Actor $u, Action $a, ObjectRef $o, Context $c): bool;
}
