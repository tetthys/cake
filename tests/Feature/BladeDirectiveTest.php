<?php

declare(strict_types=1);

use Tetthys\Cake\Integration\Laravel\Contracts\ActorResolver;
use Tetthys\Cake\Model\Actor;

beforeEach(function () {
    // 기본 액터(u-1)
    $this->app->bind(ActorResolver::class, fn () => new class implements ActorResolver {
        public function fromRequest(\Illuminate\Http\Request $request): Actor
        {
            return new Actor('u-1', ['user']);
        }
    });

    // 자동 추론 및 Class@method 테스트용 Policy
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
                        Pred::S(fn($u,$a,$o) => (string)$u->id === (string)$o->data->user_id),
                        Pred::D(fn($u,$a,$o) => $o->data->status === 'draft'),
                    ),
                ]);
            }
        }
        PHP);
    }

    // 자동 추론에서 사용할 Post 클래스
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
    // 액터를 소유자가 아니도록 교체
    $this->app->bind(ActorResolver::class, fn () => new class implements ActorResolver {
        public function fromRequest(\Illuminate\Http\Request $request): Actor
        {
            return new Actor('u-2', ['user']);
        }
    });

    $post = new \App\Models\Post();
    $post->user_id = 'u-1';
    $post->status  = 'published'; // 도메인 불일치

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
