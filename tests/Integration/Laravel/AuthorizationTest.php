<?php

declare(strict_types=1);

namespace Tests\Integration\Laravel;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Tetthys\Cake\Integration\Laravel\AuthorizesRequest;
use Tetthys\Cake\Integration\Laravel\Contracts\ActorResolver;
use Tetthys\Cake\Model\Actor;
use Tetthys\Cake\Rule\{Rule, RuleSet, Pred};
use Tests\TestCase;

final class PostUpdateController extends Controller
{
    use AuthorizesRequest;

    public function __invoke(Request $request)
    {
        // Real domain object (avoid casting to array)
        $post = (object)[
            'user_id' => (string)$request->input('owner_id'),
            'status'  => (string)($request->input('status') ?: 'draft'),
        ];

        $rules = new RuleSet([
            new Rule(
                'OwnerDraft',
                // ObjectRef is passed as $o → actual domain object is $o->data
                Pred::S(fn($u, $a, $o, $c) => (string)$u->id === (string)$o->data->user_id),
                Pred::D(fn($u, $a, $o, $c) => $o->data->status === 'draft'),
            ),
        ]);

        // Pass $post directly (Trait wraps it in ObjectRef internally)
        $decision = $this->authorizeWithCake($request, 'post.update', $post, $rules);

        return response()->json(['ok' => $decision->isPermit()]);
    }
}

uses(TestCase::class);

it('permits when subject+domain match, returns 200', function (): void {
    // Fake ActorResolver (u-1)
    $this->app->bind(ActorResolver::class, fn () => new class implements ActorResolver {
        public function fromRequest(\Illuminate\Http\Request $request): Actor
        {
            return new Actor('u-1', ['user']);
        }
    });

    Route::post('/posts/update', PostUpdateController::class);

    $this->postJson('/posts/update', ['owner_id' => 'u-1', 'status' => 'draft'])
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it('denies by default when no rule matches, returns 403 JSON', function (): void {
    // Fake ActorResolver (u-1)
    $this->app->bind(ActorResolver::class, fn () => new class implements ActorResolver {
        public function fromRequest(\Illuminate\Http\Request $request): Actor
        {
            return new Actor('u-1', ['user']);
        }
    });

    Route::post('/posts/update', PostUpdateController::class);

    // Domain mismatch → no (S ∧ D) match → deny by default
    $this->postJson('/posts/update', ['owner_id' => 'u-2', 'status' => 'published'])
        ->assertStatus(403)
        ->assertJsonPath('authorization.action', 'post.update');
});
