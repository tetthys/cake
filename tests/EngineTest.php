<?php

use Tetthys\Cake\Contracts\DomainPredicate;
use Tetthys\Cake\Contracts\SubjectPredicate;
use Tetthys\Cake\Engine\Engine;
use Tetthys\Cake\Model\Action;
use Tetthys\Cake\Model\Actor;
use Tetthys\Cake\Model\Context;
use Tetthys\Cake\Model\ObjectRef;
use Tetthys\Cake\Rule\Rule;
use Tetthys\Cake\Rule\RuleSet;

it('permits when any (S ∧ D) branch matches and denies by default', function () {
    $S_true = new class implements SubjectPredicate {
        public function __invoke(Actor $u, Action $a, ObjectRef $o, Context $c): bool { return true; }
    };
    $S_false = new class implements SubjectPredicate {
        public function __invoke(Actor $u, Action $a, ObjectRef $o, Context $c): bool { return false; }
    };
    $D_true = new class implements DomainPredicate {
        public function __invoke(Actor $u, Action $a, ObjectRef $o, Context $c): bool { return true; }
    };
    $D_false = new class implements DomainPredicate {
        public function __invoke(Actor $u, Action $a, ObjectRef $o, Context $c): bool { return false; }
    };

    $engine = new Engine();
    $u = new Actor(1, ['user']);
    $a = new Action('thing.do');
    $o = new ObjectRef('Thing', (object)[]);
    $c = new Context([]);

    // Case 1: Permit (there is a matching (S ∧ D))
    $rules = new RuleSet([
        new Rule('Nope', $S_true,  $D_false),
        new Rule('Yes',  $S_true,  $D_true),
        new Rule('Unused', $S_false, $D_true),
    ]);
    $permit = $engine->decide($u, $a, $o, $c, $rules);
    expect($permit->isPermit())->toBeTrue();
    expect($permit->selectedRule)->toBe('Yes');

    // Case 2: Deny-by-default (no matching branch)
    $rules2 = new RuleSet([
        new Rule('Nope1', $S_true,  $D_false),
        new Rule('Nope2', $S_false, $D_true),
    ]);
    $deny = $engine->decide($u, $a, $o, $c, $rules2);
    expect($deny->isPermit())->toBeFalse();
});
