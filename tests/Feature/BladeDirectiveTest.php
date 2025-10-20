<?php

declare(strict_types=1);

use Tetthys\Cake\Integration\Laravel\Contracts\ActorResolver;
use Tetthys\Cake\Model\Actor;

beforeEach(function () {
    // Default actor (u-1)
    $this->app->bind(ActorResolver::class, fn () => new class implements ActorResolver {
        public function fromRequest(\Illuminate\Http\Request $request): Actor
        {
            return new Actor('u-1', ['user']);
        }
    });

    // Policy for auto-inference and explicit Class@method tests
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
                        // IMPORTANT: ObjectRef is passed as $o; the real domain object is in $o->data
                        Pred::S(fn($u, $a, $o, $c) => (string)$u->id === (string)$o->data->user_id),
                        Pred::D(fn($u, $a, $o, $c) => $o->data->status === 'draft'),
                    ),
                ]);
            }
        }
        PHP);
    }

    // Minimal Post class for auto-inference scenario
    if (!class_exists(\App\Models\Post::class)) {
        eval(<<<'PHP'
        namespace App\Models;
        class Post { public string $user_id; public string $status; }
        PHP);
    }
});

test('cake can renders PERMIT branch on true', function () {
    $post = new \App\Models\Post();
    $post->user_id = 'u-1';
    $post->status  = 'draft';

    $view = <<<'BLADE'
@php
    /** @var \App\Models\Post $post */
@endphp

@cakeCan('post.update', $post)  {{-- auto: App\Policies\PostRules@update --}}
PERMIT
@else
DENY
@endcakeCan
BLADE;

    $this->blade($view, compact('post'))
        ->assertSee('PERMIT', false)
        ->assertDontSee('DENY', false);
});

test('cake cannot renders when permission is false', function () {
    // Swap actor so that they are NOT the owner
    $this->app->bind(ActorResolver::class, fn () => new class implements ActorResolver {
        public function fromRequest(\Illuminate\Http\Request $request): Actor
        {
            return new Actor('u-2', ['user']);
        }
    });

    $post = new \App\Models\Post();
    $post->user_id = 'u-1';
    $post->status  = 'published'; // Domain mismatch (not draft)

    $view = <<<'BLADE'
@php
    /** @var \App\Models\Post $post */
@endphp

@cakeCannot('post.update', $post)  {{-- auto: App\Policies\PostRules@update --}}
DENY
@else
PERMIT
@endcakeCannot
BLADE;

    $this->blade($view, compact('post'))
        ->assertSee('DENY', false)
        ->assertDontSee('PERMIT', false);
});

test('cake can allows explicit Class@method string in blade', function () {
    $post = new \App\Models\Post();
    $post->user_id = 'u-1';
    $post->status  = 'draft';

    $view = <<<'BLADE'
@php
    /** @var \App\Models\Post $post */
@endphp

@cakeCan('post.update', $post, \App\Policies\PostRules::class . '@update')
PERMIT
@else
DENY
@endcakeCan
BLADE;

    $this->blade($view, compact('post'))
        ->assertSee('PERMIT', false)
        ->assertDontSee('DENY', false);
});
