<?php

declare(strict_types=1);

use Tetthys\Cake\Model\Actor;
use Tetthys\Cake\Model\Action;
use Tetthys\Cake\Model\Context;
use Tetthys\Cake\Model\ObjectRef;
use Tetthys\Cake\Rule\Pred;

it("builds a SubjectPredicate from a closure", function () {
    $u = new Actor("u-1", ["admin"]);
    $a = new Action("post.update");
    $o = new ObjectRef("Post", new stdClass());
    $c = new Context(["ip" => "127.0.0.1"]);

    // S: admin-only
    $S_adminOnly = Pred::S(fn(Actor $u) => in_array("admin", $u->roles, true));

    expect($S_adminOnly($u, $a, $o, $c))->toBeTrue();

    $u2 = new Actor("u-2", ["user"]);
    expect($S_adminOnly($u2, $a, $o, $c))->toBeFalse();
});

it("builds a DomainPredicate from a closure", function () {
    $ownerId = "u-1";
    $post = new class($ownerId) {
        public function __construct(private string $ownerId) {}
        public function ownerId(): string
        {
            return $this->ownerId;
        }
    };

    $u = new Actor("u-1", ["user"]);
    $a = new Action("post.update");
    $o = new ObjectRef("Post", $post);
    $c = new Context([]);

    // D: owner can modify
    $D_owner = Pred::D(
        fn(Actor $u, Action $a, ObjectRef $o) => method_exists(
            $o->data,
            "ownerId",
        ) && $o->data->ownerId() === (string) $u->id,
    );

    expect($D_owner($u, $a, $o, $c))->toBeTrue();

    $u2 = new Actor("u-2", ["user"]);
    expect($D_owner($u2, $a, $o, $c))->toBeFalse();
});

it("supports combining S and D via closures for readability", function () {
    $u = new Actor("u-9", ["manager"]);
    $a = new Action("order.approve");

    $order = new class("u-9", "paid") {
        public function __construct(
            private string $approverId,
            private string $status,
        ) {}
        public function approverId(): string
        {
            return $this->approverId;
        }
        public function status(): string
        {
            return $this->status;
        }
    };
    $o = new ObjectRef("Order", $order);
    $c = new Context(["tenant" => "acme"]);

    $S_manager = Pred::S(fn(Actor $u) => in_array("manager", $u->roles, true));
    $D_paid = Pred::D(
        fn(Actor $u, Action $a, ObjectRef $o) => method_exists(
            $o->data,
            "status",
        ) && $o->data->status() === "paid",
    );
    $D_canApprove = Pred::D(
        fn(Actor $u, Action $a, ObjectRef $o) => method_exists(
            $o->data,
            "approverId",
        ) && $o->data->approverId() === (string) $u->id,
    );

    expect($S_manager($u, $a, $o, $c))->toBeTrue();
    expect($D_paid($u, $a, $o, $c))->toBeTrue();
    expect($D_canApprove($u, $a, $o, $c))->toBeTrue();
});
