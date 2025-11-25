<?php

declare(strict_types=1);

use Tetthys\Cake\Engine\Engine;
use Tetthys\Cake\Model\{Action, Actor, Context, ObjectRef};
use Tetthys\Cake\Rule\{Pred as P, Rule, RuleSet};

describe(Engine::class . ' pipeline with RuleSet modes', function () {
    // ---------------------------------------------------------------------
    // AllMustMatch (AND semantics)
    // ---------------------------------------------------------------------
    describe('AllMustMatch (AND semantics)', function () {
        it('denies as soon as a rule fails', function () {
            $calls = [];

            // AND-combined rules: all must match
            $rules = RuleSet::allOf([
                new Rule(
                    'First',
                    P::S(function () use (&$calls) {
                        // record evaluation order
                        $calls[] = 'first';
                        return false; // first rule fails
                    }),
                    P::D(fn() => true),
                ),
                new Rule(
                    'Second',
                    P::S(function () use (&$calls) {
                        $calls[] = 'second';
                        return true;
                    }),
                    P::D(fn() => true),
                ),
            ]);

            $decision = (new Engine())->decide(
                new Actor('u', ['user']),
                new Action('post.update'),
                new ObjectRef('Post', (object) []),
                new Context([]),
                $rules,
            );

            // As soon as "First" fails, engine denies and stops evaluating.
            expect($decision->isDeny())->toBeTrue();
            expect($decision->trace)->toEqual(['[First] no-match']);
            expect($calls)->toEqual(['first']);
        });

        it('permits only when every rule matches', function () {
            $rules = RuleSet::allOf([
                new Rule(
                    'Owner',
                    P::S(fn() => true),
                    P::D(fn() => true),
                ),
                new Rule(
                    'Recent',
                    P::S(fn() => true),
                    P::D(fn() => true),
                ),
            ]);

            $decision = (new Engine())->decide(
                new Actor('u', []),
                new Action('post.update'),
                new ObjectRef('Post', (object) []),
                new Context([]),
                $rules,
            );

            // All rules matched -> permit with "all-rules" marker.
            expect($decision->isPermit())->toBeTrue();
            expect($decision->selectedRule)->toBe('all-rules');
            expect($decision->trace)->toEqual([
                '[Owner] match',
                '[Recent] match',
            ]);
        });

        it('denies when no rules exist (deny by default)', function () {
            // Empty RuleSet in AND mode -> deny-by-default.
            $rules = RuleSet::allOf();

            $decision = (new Engine())->decide(
                new Actor('u', []),
                new Action('post.update'),
                new ObjectRef('Post', (object) []),
                new Context([]),
                $rules,
            );

            expect($decision->isDeny())->toBeTrue();
            expect($decision->trace)->toBeEmpty();
            expect($decision->selectedRule)->toBeNull();
        });
    });

    // ---------------------------------------------------------------------
    // AnyMayMatch (OR semantics)
    // ---------------------------------------------------------------------
    describe('AnyMayMatch (OR semantics)', function () {
        it('permits as soon as a rule matches (short-circuit)', function () {
            $calls = [];

            // OR-combined rules: permit if any rule matches
            $rules = RuleSet::anyOf([
                new Rule(
                    'First',
                    P::S(function () use (&$calls) {
                        $calls[] = 'first';
                        return false; // no match
                    }),
                    P::D(fn() => true),
                ),
                new Rule(
                    'Second',
                    P::S(function () use (&$calls) {
                        $calls[] = 'second';
                        return true; // first match
                    }),
                    P::D(fn() => true),
                ),
                new Rule(
                    'Third',
                    P::S(function () use (&$calls) {
                        // should never be called because of short-circuit
                        $calls[] = 'third';
                        return true;
                    }),
                    P::D(fn() => true),
                ),
            ]);

            $decision = (new Engine())->decide(
                new Actor('u', ['user']),
                new Action('post.update'),
                new ObjectRef('Post', (object) []),
                new Context([]),
                $rules,
            );

            // Engine should stop at "Second" (first match) and not evaluate "Third".
            expect($decision->isPermit())->toBeTrue();
            expect($decision->selectedRule)->toBe('Second');
            expect($decision->trace)->toEqual([
                '[First] no-match',
                '[Second] match',
            ]);
            expect($calls)->toEqual(['first', 'second']);
        });

        it('denies when none of the rules match', function () {
            $rules = RuleSet::anyOf([
                new Rule(
                    'A',
                    P::S(fn() => false),
                    P::D(fn() => true),
                ),
                new Rule(
                    'B',
                    P::S(fn() => false),
                    P::D(fn() => true),
                ),
            ]);

            $decision = (new Engine())->decide(
                new Actor('u', []),
                new Action('post.update'),
                new ObjectRef('Post', (object) []),
                new Context([]),
                $rules,
            );

            // No rule matched -> deny.
            expect($decision->isDeny())->toBeTrue();
            expect($decision->selectedRule)->toBeNull();
            expect($decision->trace)->toEqual([
                '[A] no-match',
                '[B] no-match',
            ]);
        });

        it('denies when no rules exist (deny by default)', function () {
            // Empty RuleSet in OR mode also denies by default.
            $rules = RuleSet::anyOf();

            $decision = (new Engine())->decide(
                new Actor('u', []),
                new Action('post.update'),
                new ObjectRef('Post', (object) []),
                new Context([]),
                $rules,
            );

            expect($decision->isDeny())->toBeTrue();
            expect($decision->trace)->toBeEmpty();
            expect($decision->selectedRule)->toBeNull();
        });
    });
});
