<?php

declare(strict_types=1);

use Tetthys\Cake\Integration\Laravel\Contracts\ActorResolver;
use Tetthys\Cake\Model\Actor;
use Tetthys\Cake\Rule\{Rule, RuleSet, Pred};
use function Tetthys\Cake\Integration\Laravel\cakeCan;

beforeEach(function () {
    // Default actor (u-1)
    $this->app->bind(ActorResolver::class, fn () => new class implements ActorResolver {
        public function fromRequest(\Illuminate\Http\Request $request): Actor
        {
            return new Actor('u-1', ['user']);
        }
    });

    // Define a simple Policy used for Class@method and auto-inference tests
    if (!class_exists(\App\Policies\PostRules::class)) {
        eval(<<<'PHP'
        namespace App\Policies;

        use Illuminate\Http\Request;
        use Tetthys\Cake\Rule\{Rule, RuleSet, Pred};

        final class PostRules
        {
            public function update(Request $request): RuleSet
            {
                return new RuleSet([
                    new Rule(
                        'OwnerDraft',
                        // ObjectRef is passed as $o → actual domain object is $o->data
                        Pred::S(fn($u, $a, $o, $c) => (string)$u->id === (string)$o->data->user_id),
                        Pred::D(fn($u, $a, $o, $c) => $o->data->status === 'draft'),
                    ),
                ]);
            }
        }
        PHP);
    }
});

test('cakeCan permits with explicit RuleSet when subject+domain match', function () {
    $post = (object)['user_id' => 'u-1', 'status' => 'draft'];

    $rules = new RuleSet([
        new Rule(
            'OwnerDraft',
            Pred::S(fn($u, $a, $o, $c) => (string)$u->id === (string)$o->data->user_id),
            Pred::D(fn($u, $a, $o, $c) => $o->data->status === 'draft'),
        ),
    ]);

    expect(cakeCan('post.update', $post, $rules))->toBeTrue();
});

test('cakeCan denies with Class@method when not owner or not draft', function () {
    // Switch actor → non-owner (u-2)
    $this->app->bind(ActorResolver::class, fn () => new class implements ActorResolver {
        public function fromRequest(\Illuminate\Http\Request $request): Actor
        {
            return new Actor('u-2', ['user']);
        }
    });

    $post = (object)['user_id' => 'u-1', 'status' => 'published']; // domain mismatch
    expect(cakeCan('post.update', $post, \App\Policies\PostRules::class.'@update'))->toBeFalse();
});

test('cakeCan auto-infers App\\Policies\\{Base}Rules@{method} from action+object', function () {
    // Switch actor → owner (u-9)
    $this->app->bind(ActorResolver::class, fn () => new class implements ActorResolver {
        public function fromRequest(\Illuminate\Http\Request $request): Actor
        {
            return new Actor('u-9', ['user']);
        }
    });

    // Minimal Post model for inference
    if (!class_exists(\App\Models\Post::class)) {
        eval(<<<'PHP'
        namespace App\Models;
        class Post { public string $user_id; public string $status; }
        PHP);
    }

    $post = new \App\Models\Post();
    $post->user_id = 'u-9';
    $post->status  = 'draft';

    // Should resolve to App\Policies\PostRules@update automatically
    expect(cakeCan('post.update', $post))->toBeTrue();
});
