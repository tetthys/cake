<?php

declare(strict_types=1);

use Tetthys\Cake\Engine\Engine;
use Tetthys\Cake\Model\{Actor, Action, ObjectRef, Context};
use Tetthys\Cake\Rule\{Rule, RuleSet, Combinators as C, Pred as P};

describe("Pipeline: (u,a,o,c) -> RuleSet -> Engine -> Decision", function () {
    it(
        "selects the first matching (S ∧ D) rule and records trace in order",
        function () {
            // Arrange
            $u = new Actor("u-1", ["user"]);
            $a = new Action("post.update");
            $o = new ObjectRef(
                "Post",
                new class("u-1", "draft") {
                    public function __construct(
                        private string $ownerId,
                        private string $status,
                    ) {}
                    public function ownerId(): string
                    {
                        return $this->ownerId;
                    }
                    public function status(): string
                    {
                        return $this->status;
                    }
                },
            );
            $c = new Context(["ip" => "127.0.0.1"]);

            $S_user = P::S(fn(Actor $u) => in_array("user", $u->roles, true));
            $S_admin = P::S(fn(Actor $u) => in_array("admin", $u->roles, true));

            $D_owner = P::D(
                fn(Actor $u, Action $a, ObjectRef $o) => method_exists(
                    $o->data,
                    "ownerId",
                ) && $o->data->ownerId() === (string) $u->id,
            );
            $D_published = P::D(
                fn(Actor $_u, Action $_a, ObjectRef $o) => method_exists(
                    $o->data,
                    "status",
                ) && $o->data->status() === "published",
            );

            $rules = new RuleSet([
                new Rule("Admin-Can-Update-Published", $S_admin, $D_published),
                new Rule("Owner-Can-Update", $S_user, $D_owner),
                new Rule("Never-Reached", $S_user, $D_published),
            ]);

            // Act
            $decision = (new Engine())->decide($u, $a, $o, $c, $rules);

            // Assert
            expect($decision->isPermit())->toBeTrue();
            expect($decision->selectedRule)->toBe("Owner-Can-Update");

            expect($decision->trace)->toHaveCount(2); // only first 2 evaluated
            expect($decision->trace[0])->toContain(
                "[Admin-Can-Update-Published]",
                "no-match",
            );
            expect($decision->trace[1])->toContain("[Owner-Can-Update]", "match");
        },
    );

    it("denies by default when no rule matches (safety by default)", function () {
        // Arrange
        $u = new Actor("u-2", ["user"]);
        $a = new Action("post.update");
        $o = new ObjectRef(
            "Post",
            new class("u-1", "archived") {
                public function __construct(
                    private string $ownerId,
                    private string $status,
                ) {}
                public function ownerId(): string
                {
                    return $this->ownerId;
                }
                public function status(): string
                {
                    return $this->status;
                }
            },
        );
        $c = new Context(["ip" => "127.0.0.1"]);

        $S_any = C::S_or(
            P::S(fn(Actor $u) => in_array("user", $u->roles, true)),
            P::S(fn(Actor $u) => in_array("admin", $u->roles, true)),
        );

        $D_owner = P::D(
            fn(Actor $u, Action $a, ObjectRef $o) => method_exists(
                $o->data,
                "ownerId",
            ) && $o->data->ownerId() === (string) $u->id,
        );
        $D_published = P::D(
            fn(Actor $_u, Action $_a, ObjectRef $o) => method_exists(
                $o->data,
                "status",
            ) && $o->data->status() === "published",
        );

        $rules = new RuleSet([
            new Rule(
                "Owner-And-Published-Required",
                C::S_and($S_any),
                C::D_and($D_owner, $D_published),
            ),
        ]);

        // Act
        $decision = (new Engine())->decide($u, $a, $o, $c, $rules);

        // Assert
        expect($decision->isPermit())->toBeFalse();
        expect($decision->selectedRule)->toBeNull();

        expect($decision->trace)->toHaveCount(1);
        expect($decision->trace[0])->toContain(
            "[Owner-And-Published-Required]",
            "no-match",
        );
    });

    it("honors short-circuiting in both S and D compositions", function () {
        // Arrange
        $u = new Actor("manager-1", ["manager"]);
        $a = new Action("order.approve");
        $o = new ObjectRef(
            "Order",
            new class("manager-1", "paid") {
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
            },
        );
        $c = new Context(["tenant" => "acme"]);

        $S_manager = P::S(fn(Actor $u) => in_array("manager", $u->roles, true));
        $S_admin = P::S(fn(Actor $u) => in_array("admin", $u->roles, true));
        $S_managerOrAdmin = C::S_or($S_manager, $S_admin); // true at first predicate

        $D_paid = P::D(
            fn(Actor $_u, Action $_a, ObjectRef $o) => method_exists(
                $o->data,
                "status",
            ) && $o->data->status() === "paid",
        );
        $D_approver = P::D(
            fn(Actor $u, Action $_a, ObjectRef $o) => method_exists(
                $o->data,
                "approverId",
            ) && $o->data->approverId() === (string) $u->id,
        );
        $D_all = C::D_and($D_paid, $D_approver); // both must be true

        $rules = new RuleSet([
            new Rule("Manager-Approves-Paid-Orders", $S_managerOrAdmin, $D_all),
        ]);

        // Act
        $decision = (new Engine())->decide($u, $a, $o, $c, $rules);

        // Assert
        expect($decision->isPermit())->toBeTrue();
        expect($decision->selectedRule)->toBe("Manager-Approves-Paid-Orders");

        expect($decision->trace)->toHaveCount(1);
        expect($decision->trace[0])->toContain(
            "[Manager-Approves-Paid-Orders]",
            "match",
        );
    });
});
