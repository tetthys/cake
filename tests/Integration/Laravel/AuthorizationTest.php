<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Tests\Support\FakeUser;
use Tetthys\Cake\Contracts\DomainPredicate;
use Tetthys\Cake\Contracts\SubjectPredicate;
use Tetthys\Cake\Integration\Laravel\AuthorizesRequest;
use Tetthys\Cake\Rule\Rule;
use Tetthys\Cake\Rule\RuleSet;

/**
 * Controller stub using AuthorizesRequest trait.
 * - Authorizes with a simple (S ∧ D) rule.
 * - Returns 200 JSON on permit; default responder throws JSON 403 on deny.
 */
class PostUpdateController
{
    use AuthorizesRequest;

    public function __invoke(\Illuminate\Http\Request $request)
    {
        // Subject: check roles via public property (no roles() method!)
        $S_userOrAdmin = new class implements SubjectPredicate {
            public function __invoke($u, $a, $o, $c): bool
            {
                // Actor exposes public array $roles
                return in_array('user', $u->roles, true) || in_array('admin', $u->roles, true);
            }
        };

        // Domain: the actor must own the object
        $D_owner = new class implements DomainPredicate {
            public function __invoke($u, $a, $o, $c): bool
            {
                // ObjectRef exposes public $data (no value() method)
                return method_exists($o->data, 'ownerId') && $o->data->ownerId() === (string) $u->id;
            }
        };

        $rules = new RuleSet([
            new Rule('Owner-Can-Update', $S_userOrAdmin, $D_owner),
        ]);

        // Minimal domain object that exposes ownerId()
        $post = new class($request->input('owner_id')) {
            public function __construct(private string $ownerId) {}
            public function ownerId(): string
            {
                return $this->ownerId;
            }
        };

        // Will return Decision on permit; default responder throws JSON 403 on deny.
        $this->authorizeWithCake($request, 'post.update', $post, $rules);

        return response()->json(['ok' => true, 'rule' => 'Owner-Can-Update'], 200);
    }
}

it('permits when subject+domain match, returns 200', function (): void {
    Route::post('/posts/update', PostUpdateController::class);

    // Inject authenticated user (no provider needed)
    $user = new FakeUser('u-1', ['user']);
    $this->be($user);

    $this->postJson('/posts/update', ['owner_id' => 'u-1'])
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'rule' => 'Owner-Can-Update',
        ]);
});

it('denies by default when no rule matches, returns 403 JSON', function (): void {
    Route::post('/posts/update', PostUpdateController::class);

    $user = new FakeUser('u-1', ['user']);
    $this->be($user);

    // Domain mismatch -> no (S ∧ D) branch matches -> deny-by-default
    $this->postJson('/posts/update', ['owner_id' => 'u-2'])
        ->assertStatus(403)
        ->assertJson(
            fn($json) =>
            $json->where('message', 'Forbidden')
                ->whereType('authorization', 'array')
                ->etc()
        );
});
