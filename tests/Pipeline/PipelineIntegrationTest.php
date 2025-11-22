<?php

declare(strict_types=1);

use Tetthys\Cake\Engine\Engine;
use Tetthys\Cake\Model\{Action, Actor, Context, ObjectRef};
use Tetthys\Cake\Rule\{Pred as P, Rule, RuleSet};

describe(Engine::class . " pipeline", function () {
    it("denies as soon as a rule fails", function () {
        $calls = [];

        $rules = new RuleSet([
            new Rule(
                "First",
                P::S(function () use (&$calls) {
                    $calls[] = "first";
                    return false;
                }),
                P::D(fn() => true),
            ),
            new Rule(
                "Second",
                P::S(function () use (&$calls) {
                    $calls[] = "second";
                    return true;
                }),
                P::D(fn() => true),
            ),
        ]);

        $decision = (new Engine())->decide(
            new Actor("u", ["user"]),
            new Action("post.update"),
            new ObjectRef("Post", (object) []),
            new Context([]),
            $rules,
        );

        expect($decision->isDeny())->toBeTrue();
        expect($decision->trace)->toEqual(["[First] no-match"]);
        expect($calls)->toEqual(["first"]);
    });

    it("permits only when every rule matches", function () {
        $rules = new RuleSet([
            new Rule(
                "Owner",
                P::S(fn() => true),
                P::D(fn() => true),
            ),
            new Rule(
                "Recent",
                P::S(fn() => true),
                P::D(fn() => true),
            ),
        ]);

        $decision = (new Engine())->decide(
            new Actor("u", []),
            new Action("post.update"),
            new ObjectRef("Post", (object) []),
            new Context([]),
            $rules,
        );

        expect($decision->isPermit())->toBeTrue();
        expect($decision->selectedRule)->toBe("all-rules");
        expect($decision->trace)->toEqual([
            "[Owner] match",
            "[Recent] match",
        ]);
    });

    it("denies when no rules exist (deny by default)", function () {
        $decision = (new Engine())->decide(
            new Actor("u", []),
            new Action("post.update"),
            new ObjectRef("Post", (object) []),
            new Context([]),
            new RuleSet(),
        );

        expect($decision->isDeny())->toBeTrue();
        expect($decision->trace)->toBeEmpty();
        expect($decision->selectedRule)->toBeNull();
    });
});
