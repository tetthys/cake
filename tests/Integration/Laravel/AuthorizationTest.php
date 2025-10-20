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
 * Controller stub that uses AuthorizesRequest trait.
 * It authorizes the request with given rules and returns 200 on permit.
 */
class PostUpdateController
{
    use AuthorizesRequest;

    public function __invoke(\Illuminate\Http\Request $request)
    {
        // Build rules inline for test
        $S_user = new class implements SubjectPredicate {
            public function __invoke($u, $a, $o, $c): bool
            {
                return in_array("user", $u->roles(), true) ||
                    in_array("admin", $u->roles(), true);
            }
        };
        $D_owner = new class implements DomainPredicate {
            public function __invoke($u, $a, $o, $c): bool
            {
                return method_exists($o->value(), "ownerId") &&
                    $o->value()->ownerId() === $u->id();
            }
        };
        $rules = new RuleSet([new Rule("Owner-Can-Update", $S_user, $D_owner)]);

        $post = new class($request->input("owner_id")) {
            public function __construct(private string $ownerId) {}
            public function ownerId(): string
            {
                return $this->ownerId;
            }
        };

        // Will throw HttpResponseException(403 JSON) on DENY by default responder.
        $this->authorizeWithCake($request, "post.update", $post, $rules);

        return response()->json(["ok" => true, "rule" => "Owner-Can-Update"], 200);
    }
}

it("permits when subject+domain match, returns 200", function () {
    // Define the route for this test case
    Route::post("/posts/update", PostUpdateController::class);

    // User owns the post
    $user = new FakeUser("u-1", ["user"]);

    $this->actingAs($user)
        ->postJson("/posts/update", ["owner_id" => "u-1"])
        ->assertOk()
        ->assertJson([
            "ok" => true,
            "rule" => "Owner-Can-Update",
        ]);
});

it("denies by default when no rule matches, returns 403 JSON", function () {
    Route::post("/posts/update", PostUpdateController::class);

    // User does not own the post
    $user = new FakeUser("u-1", ["user"]);

    $this->actingAs($user)
        ->postJson("/posts/update", ["owner_id" => "u-2"])
        ->assertStatus(403)
        ->assertJson(
            fn($json) => $json
                ->where("message", "Forbidden")
                ->whereType("authorization", "array")
                ->etc(),
        );
});
