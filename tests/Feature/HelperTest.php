<?php

declare(strict_types=1);

use Tetthys\Cake\Integration\Laravel\Contracts\ActorResolver;
use Tetthys\Cake\Model\Actor;
use Tetthys\Cake\Rule\{Rule, RuleSet, Pred};
use function Tetthys\Cake\Integration\Laravel\cake;

beforeEach(function () {
    // Default actor (u-1)
    $this->app->bind(ActorResolver::class, fn() => new class implements ActorResolver {
        public function fromRequest(\Illuminate\Http\Request $request): Actor
        {
            return new Actor('u-1', ['user']);
        }
    });

    // Define a simple Policy used for auto-inference only.
    // Auto-infer target: App\Policies\PostRules@update
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

    // Minimal Post model for inference (class_basename(Post) => "Post")
    if (!class_exists(\App\Models\Post::class)) {
        eval(<<<'PHP'
        namespace App\Models;

        class Post {
            public string $user_id;
            public string $status;
        }
        PHP);
    }
});

test('cake permits when subject+domain match via auto-inferred policy', function () {
    // Actor is u-1 by default
    $post = new \App\Models\Post();
    $post->user_id = 'u-1';
    $post->status  = 'draft';

    // Auto-infers App\Policies\PostRules@update
    expect(cake('post.update', $post))->toBeTrue();
});

test('cake denies when not owner or not draft via auto-inferred policy', function () {
    // Switch actor → non-owner (u-2)
    $this->app->bind(ActorResolver::class, fn() => new class implements ActorResolver {
        public function fromRequest(\Illuminate\Http\Request $request): Actor
        {
            return new Actor('u-2', ['user']);
        }
    });

    $post = new \App\Models\Post();
    $post->user_id = 'u-1';
    $post->status  = 'published';

    // Still auto-infers App\Policies\PostRules@update
    expect(cake('post.update', $post))->toBeFalse();
});

test('cake auto-infers App\\Policies\\{Base}Rules@{method} from action+object', function () {
    // Switch actor → owner (u-9)
    $this->app->bind(ActorResolver::class, fn() => new class implements ActorResolver {
        public function fromRequest(\Illuminate\Http\Request $request): Actor
        {
            return new Actor('u-9', ['user']);
        }
    });

    $post = new \App\Models\Post();
    $post->user_id = 'u-9';
    $post->status  = 'draft';

    // Should resolve to App\Policies\PostRules@update automatically
    expect(cake('post.update', $post))->toBeTrue();
});
