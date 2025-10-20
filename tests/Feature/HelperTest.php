<?php

declare(strict_types=1);

use Tetthys\Cake\Integration\Laravel\Contracts\ActorResolver;
use Tetthys\Cake\Model\Actor;
use Tetthys\Cake\Rule\{Rule, RuleSet, Pred};
use function Tetthys\Cake\Integration\Laravel\cakeCan;

beforeEach(function () {
    // 기본 액터: u-1
    $this->app->bind(ActorResolver::class, fn () => new class implements ActorResolver {
        public function fromRequest(\Illuminate\Http\Request $request): Actor
        {
            return new Actor('u-1', ['user']);
        }
    });

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
});

test('cakeCan permits with explicit RuleSet when subject+domain match', function () {
    $post = (object)['user_id' => 'u-1', 'status' => 'draft'];

    $rules = new RuleSet([
        new Rule(
            'OwnerDraft',
            // ✅ ObjectRef를 받으므로 data를 통해 접근해야 합니다.
            Pred::S(fn ($u, $a, $o) => (string) $u->id === (string) $o->data->user_id),
            Pred::D(fn ($u, $a, $o) => $o->data->status === 'draft'),
        ),
    ]);

    expect(cakeCan('post.update', $post, $rules))->toBeTrue();
});

test('cakeCan denies with Class@method when not owner or not draft', function () {
    // 비소유자 u-2로 교체
    $this->app->bind(ActorResolver::class, fn () => new class implements ActorResolver {
        public function fromRequest(\Illuminate\Http\Request $request): Actor
        {
            return new Actor('u-2', ['user']);
        }
    });

    $post = (object)['user_id' => 'u-1', 'status' => 'published']; // 도메인 불일치
    expect(cakeCan('post.update', $post, \App\Policies\PostRules::class.'@update'))->toBeFalse();
});

test('cakeCan auto-inferrs App\\Policies\\{Base}Rules@{method} from action+object', function () {
    // 소유자 u-9로 교체
    $this->app->bind(ActorResolver::class, fn () => new class implements ActorResolver {
        public function fromRequest(\Illuminate\Http\Request $request): Actor
        {
            return new Actor('u-9', ['user']);
        }
    });

    // 자동 추론용 Post 클래스
    if (!class_exists(\App\Models\Post::class)) {
        eval(<<<'PHP'
        namespace App\Models;
        class Post { public string $user_id; public string $status; }
        PHP);
    }

    $post = new \App\Models\Post();
    $post->user_id = 'u-9';
    $post->status  = 'draft';

    expect(cakeCan('post.update', $post))->toBeTrue(); // auto: App\Policies\PostRules@update
});
