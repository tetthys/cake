# Tetthys/Cake

> Functional & Layered Authorization for Laravel — Declarative, Composable, Testable

---

## ⚙️ Installation

```bash
composer require tetthys/cake
```

Laravel auto-discovers the service provider:

```json
"extra": {
  "laravel": {
    "providers": [
      "Tetthys\\Cake\\Integration\\Laravel\\CakeServiceProvider"
    ]
  }
}
```

Once installed, Cake automatically registers:

* `@cakeCan` / `@cakeCannot` Blade directives
* `cake` middleware
* global helper `cakeCan()`

---

## 🚀 Quick Usage

### 🧩 1. Define Rules

```php
// app/Policies/PostRules.php
namespace App\Policies;

use Illuminate\Http\Request;
use Tetthys\Cake\Rule\{Rule, RuleSet, Pred, Combinators as C};

final class PostRules
{
    public function update(Request $request): RuleSet
    {
        $post = $request->route('post');

        return new RuleSet([
            new Rule(
                'OwnerOrAdmin_WhenDraft',
                C::S_or(
                    Pred::S(fn($u) => $u->id === $post->user_id),
                    Pred::S(fn($u) => in_array('admin', $u->roles, true))
                ),
                Pred::D(fn($u, $a, $o, $c) => $o->data->status === 'draft')
            ),
        ]);
    }
}
```

> * **S** → Subject condition (who)
> * **D** → Domain condition (when/what)
> * A `RuleSet` is a list of `(S ∧ D)` rules.
>   If any matches → **Permit**, otherwise → **Deny (by default)**

---

### 🧱 2. Controller Integration

Use the built-in trait `AuthorizesRequest`.

```php
use Tetthys\Cake\Integration\Laravel\AuthorizesRequest;
use App\Policies\PostRules;

class PostController
{
    use AuthorizesRequest;

    public function update(Request $request, Post $post)
    {
        $decision = $this->authorizeWithCake(
            $request,
            'post.update',
            $post,
            app(PostRules::class)->update($request)
        );

        // Decision implements isPermit() / isDeny()
        return response()->json(['ok' => $decision->isPermit()]);
    }
}
```

---

### 🪶 3. Blade Directives

```blade
@cakeCan('post.update', $post)
  <button>✏️ Edit</button>
@else
  <p>You cannot edit this post.</p>
@endcakeCan

@cakeCannot('post.update', $post)
  <p>❌ No permission</p>
@endcakeCannot
```

✅ Automatically infers `App\Policies\PostRules@update` from action name + object type.
You can also pass:

* Explicit `"App\\Policies\\PostRules@update"` string, or
* A pre-built `RuleSet` instance.

---

### ⚡ 4. Middleware (Route-Level)

You can authorize before the controller executes.

```php
// explicit
Route::put('/posts/{post}', [PostController::class, 'update'])
    ->middleware('cake:post.update,App\\Policies\\PostRules@update');
```

or just use the shorthand — automatic inference:

```php
// automatic inference -> App\Policies\PostRules@update
Route::put('/posts/{post}', [PostController::class, 'update'])
    ->middleware('cake:post.update');
```

> Cake finds your route model (`{post}`), infers `"App\Policies\PostRules@update"`,
> and denies (403) if no rule matches.

---

### 🧩 5. Helper Function

```php
use function Tetthys\Cake\Integration\Laravel\cakeCan;

if (cakeCan('post.update', $post)) {
    // Do something only if permitted
}
```

This helper can take:

* action string (`'post.update'`)
* model or object
* optional `RuleSet` or `"Class@method"` string

If omitted, Cake will infer the policy automatically.

---

### 🧪 6. Testing

```php
use Tetthys\Cake\Engine\Engine;
use Tetthys\Cake\Model\{Actor, Action, ObjectRef, Context};
use App\Policies\PostRules;

$engine = app(Engine::class);

$decision = $engine->decide(
    new Actor('u-1', ['user']),
    new Action('post.update'),
    new ObjectRef('Post', (object)['user_id' => 'u-1', 'status' => 'draft']),
    new Context(),
    app(PostRules::class)->update(request())
);

expect($decision->isPermit())->toBeTrue();
```

Because rules are **pure functions**, you can test them without HTTP or Laravel context.

---

## 🧠 Key Features

| Feature           | Description                                             |
| ----------------- | ------------------------------------------------------- |
| **Declarative**   | Express access as composable predicates, not `if` trees |
| **Composable**    | Combine `S` and `D` with AND / OR / NOT                 |
| **Secure**        | Deny-by-default — no implicit permits                   |
| **Laravel-Ready** | Works in controllers, Blade, middleware, and helpers    |
| **Functional**    | Stateless and testable, rule logic is pure PHP          |

---

## 🧾 License

MIT © Tetthys