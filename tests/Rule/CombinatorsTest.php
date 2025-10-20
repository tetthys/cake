<?php

declare(strict_types=1);

use Tetthys\Cake\Model\Actor;
use Tetthys\Cake\Model\Action;
use Tetthys\Cake\Model\Context;
use Tetthys\Cake\Model\ObjectRef;
use Tetthys\Cake\Rule\Combinators;
use Tetthys\Cake\Rule\Pred;

it(
    "S_or returns false for zero predicates (identity) and short-circuits on first true",
    function () {
        $u = new Actor("u-1", ["user"]);
        $a = new Action("thing.do");
        $o = new ObjectRef("Thing", new stdClass());
        $c = new Context([]);

        // Identity with zero predicates
        $S0 = Combinators::S_or();
        expect($S0($u, $a, $o, $c))->toBeFalse();

        // Short-circuit behavior
        $hits = [];
        $S_false = Pred::S(function () use (&$hits) {
            $hits[] = "S_false";
            return false;
        });
        $S_true = Pred::S(function () use (&$hits) {
            $hits[] = "S_true";
            return true;
        });
        $S_never = Pred::S(function () use (&$hits) {
            $hits[] = "S_never";
            return true;
        });

        $Sor = Combinators::S_or($S_false, $S_true, $S_never);
        expect($Sor($u, $a, $o, $c))->toBeTrue();
        expect($hits)->toBe(["S_false", "S_true"]); // S_never not evaluated
    },
);

it(
    "S_and returns true for zero predicates (identity) and short-circuits on first false",
    function () {
        $u = new Actor("u-1", ["user"]);
        $a = new Action("thing.do");
        $o = new ObjectRef("Thing", new stdClass());
        $c = new Context([]);

        // Identity with zero predicates
        $S0 = Combinators::S_and();
        expect($S0($u, $a, $o, $c))->toBeTrue();

        // Short-circuit behavior
        $hits = [];
        $S_true = Pred::S(function () use (&$hits) {
            $hits[] = "S_true";
            return true;
        });
        $S_false = Pred::S(function () use (&$hits) {
            $hits[] = "S_false";
            return false;
        });
        $S_never = Pred::S(function () use (&$hits) {
            $hits[] = "S_never";
            return true;
        });

        $Sand = Combinators::S_and($S_true, $S_false, $S_never);
        expect($Sand($u, $a, $o, $c))->toBeFalse();
        expect($hits)->toBe(["S_true", "S_false"]); // S_never not evaluated
    },
);

it("S_not negates the predicate result", function () {
    $u = new Actor("u-1", ["admin"]);
    $a = new Action("thing.do");
    $o = new ObjectRef("Thing", new stdClass());
    $c = new Context([]);

    $S_true = Pred::S(fn() => true);
    $S_false = Pred::S(fn() => false);

    expect(Combinators::S_not($S_true)($u, $a, $o, $c))->toBeFalse();
    expect(Combinators::S_not($S_false)($u, $a, $o, $c))->toBeTrue();
});

it(
    "D_or returns false for zero predicates (identity) and short-circuits on first true",
    function () {
        $u = new Actor("u-1", ["user"]);
        $a = new Action("thing.do");
        $o = new ObjectRef("Thing", new stdClass());
        $c = new Context([]);

        // Identity with zero predicates
        $D0 = Combinators::D_or();
        expect($D0($u, $a, $o, $c))->toBeFalse();

        // Short-circuit behavior
        $hits = [];
        $D_false = Pred::D(function () use (&$hits) {
            $hits[] = "D_false";
            return false;
        });
        $D_true = Pred::D(function () use (&$hits) {
            $hits[] = "D_true";
            return true;
        });
        $D_never = Pred::D(function () use (&$hits) {
            $hits[] = "D_never";
            return true;
        });

        $Dor = Combinators::D_or($D_false, $D_true, $D_never);
        expect($Dor($u, $a, $o, $c))->toBeTrue();
        expect($hits)->toBe(["D_false", "D_true"]); // D_never not evaluated
    },
);

it(
    "D_and returns true for zero predicates (identity) and short-circuits on first false",
    function () {
        $u = new Actor("u-1", ["user"]);
        $a = new Action("thing.do");
        $o = new ObjectRef("Thing", new stdClass());
        $c = new Context([]);

        // Identity with zero predicates
        $D0 = Combinators::D_and();
        expect($D0($u, $a, $o, $c))->toBeTrue();

        // Short-circuit behavior
        $hits = [];
        $D_true = Pred::D(function () use (&$hits) {
            $hits[] = "D_true";
            return true;
        });
        $D_false = Pred::D(function () use (&$hits) {
            $hits[] = "D_false";
            return false;
        });
        $D_never = Pred::D(function () use (&$hits) {
            $hits[] = "D_never";
            return true;
        });

        $Dand = Combinators::D_and($D_true, $D_false, $D_never);
        expect($Dand($u, $a, $o, $c))->toBeFalse();
        expect($hits)->toBe(["D_true", "D_false"]); // D_never not evaluated
    },
);

it("D_not negates the predicate result", function () {
    $u = new Actor("u-1", ["user"]);
    $a = new Action("thing.do");
    $o = new ObjectRef("Thing", new stdClass());
    $c = new Context([]);

    $D_true = Pred::D(fn() => true);
    $D_false = Pred::D(fn() => false);

    expect(Combinators::D_not($D_true)($u, $a, $o, $c))->toBeFalse();
    expect(Combinators::D_not($D_false)($u, $a, $o, $c))->toBeTrue();
});
