<?php

use Tetthys\Cake\Contracts\DomainPredicate;
use Tetthys\Cake\Contracts\SubjectPredicate;
use Tetthys\Cake\Engine\Engine;
use Tetthys\Cake\Model\Action;
use Tetthys\Cake\Model\Actor;
use Tetthys\Cake\Model\Context;
use Tetthys\Cake\Model\ObjectRef;
use Tetthys\Cake\Rule\Combinators as C;
use Tetthys\Cake\Rule\Rule;
use Tetthys\Cake\Rule\RuleSet;

require __DIR__ . '/../vendor/autoload.php';

// Domain dummy "Order"
$order = (object)['id' => 10, 'status' => 'PENDING', 'amount' => 12000000, 'department' => 'A', 'owner_id' => 7];

$uManager: SubjectPredicate = new class implements SubjectPredicate {
    public function __invoke(Actor $u, Action $a, ObjectRef $o, Context $c): bool
    {
        return in_array('manager', $u->roles, true);
    }
};

$uOwnerOrAdmin: SubjectPredicate = new class implements SubjectPredicate {
    public function __invoke(Actor $u, Action $a, ObjectRef $o, Context $c): bool
    {
        $isOwner = ($u->id === ($o->data->owner_id ?? null));
        $isAdmin = in_array('admin', $u->roles, true);
        return $isOwner || $isAdmin;
    }
};

$dApprovable: DomainPredicate = new class implements DomainPredicate {
    public function __invoke(Actor $u, Action $a, ObjectRef $o, Context $c): bool
    {
        return ($o->data->status ?? null) === 'PENDING'
            && ($o->data->amount ?? 0) >= 10_000_000
            && ($o->data->department ?? null) === ($u->attrs['department'] ?? null);
    }
};

$rules = new RuleSet([
    new Rule('ManagerCanApproveLargeInDept', $uManager, $dApprovable, 'Managers can approve large orders in their department.'),
    new Rule('OwnerOrAdminIfApprovable', $uOwnerOrAdmin, $dApprovable, 'Owner/Admin may approve if order is approvable.'),
]);

$engine   = new Engine();
$actor    = new Actor(id: 7, roles: ['user', 'manager'], attrs: ['department' => 'A']);
$action   = new Action('order.approve');
$object   = new ObjectRef('Order', $order);
$context  = new Context(['now' => date(DATE_ATOM)]);

$decision = $engine->decide($actor, $action, $object, $context, $rules);

echo $decision->outcome . PHP_EOL;
print_r($decision->trace);
