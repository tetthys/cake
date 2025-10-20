# Tetthys/Cake

> Functional & Layered Business Authorization for Laravel

Cake is a **functional, composable authorization layer** for Laravel.
It replaces imperative `if` checks and tangled policy logic with **declarative, testable RuleSets**.

---

## ⚙️ Installation

```bash
composer require tetthys/cake
```

Laravel auto-discovers the provider:

```json
"extra": {
  "laravel": {
    "providers": [
      "Tetthys\\Cake\\Integration\\Laravel\\CakeServiceProvider"
    ]
  }
}
```

---

## 🪄 Basic Usage

### 1️⃣ Define Rules

```php
// app/Policies/PostRules.php
namespace App\Policies;

use Illuminate\Http\Request;
use Tetthys\Cake\Rule\{Rule, RuleSet, Pred, Combinators as C};

final class PostRules
{
    public function update(Request $request): RuleSet
    {
        return new RuleSet([
            new Rule(
                'OwnerOrAdmin_WhenDraft',
                C::S_or(
                    Pred::S(fn($u) => $u->id === $request->route('post')->user_id),
                    Pred::S(fn($u) => in_array('admin', $u->roles))
                ),
                Pred::D(fn($u, $a, $o, $c) => $o->data->status === 'draft')
            ),
        ]);
    }
}
```

* **`S`** = Subject condition (who)
* **`D`** = Domain condition (when/what)
* `RuleSet` = a collection of `(S ∧ D)` rules
  → if any rule matches, it **permits**; otherwise it **denies**.

---

### 2️⃣ Use in Controllers

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

        if (! $decision->isPermit()) {
            abort(403, 'Forbidden by Cake rules');
        }

        // Continue...
        return response()->json(['ok' => true]);
    }
}
```

---

### 3️⃣ Or Use in Blade

```blade
@cakeCan('post.update', $post)
  <button>✏️ Edit</button>
@else
  <p class="text-gray">You cannot edit this post</p>
@endcakeCan

@cakeCannot('post.update', $post)
  <p>❌ No permission</p>
@endcakeCannot
```

* Blade directives resolve `App\Policies\PostRules@update` automatically.
* You can also pass explicit `Class@method` or a `RuleSet` object.

---

### 4️⃣ Quick Helper

```php
use function Tetthys\Cake\Integration\Laravel\cakeCan;

if (cakeCan('post.update', $post)) {
    // permit
}
```

Cake auto-detects your policy class and rule method from the action name and object type.

---

## 🧪 Testing

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

---

## 🔧 Middleware Integration

```php
Route::put('/posts/{post}', [PostController::class, 'update'])
    ->middleware('cake:post.update,App\\Policies\\PostRules@update');
```

Automatically authorizes the request before hitting the controller.

---

## 🧩 Advanced Examples

### Combine predicates dynamically

```php
use Tetthys\Cake\Rule\{Pred, Combinators as C};

$isOwner = Pred::S(fn($u, $a, $o) => $o->data->user_id === $u->id);
$isAdmin = Pred::S(fn($u) => in_array('admin', $u->roles));
$isDraft = Pred::D(fn($u, $a, $o) => $o->data->status === 'draft');

$rule = new Rule('AdminOrOwner_WhenDraft', C::S_or($isOwner, $isAdmin), $isDraft);
```

---

## 🧠 Key Principles

| Principle         | Description                                        |
| ----------------- | -------------------------------------------------- |
| **Declarative**   | Rules describe access, not enforce it directly     |
| **Composable**    | Combine S/D predicates with AND / OR / NOT         |
| **Testable**      | Functional & pure, no global state                 |
| **Secure**        | Deny-by-default: no fallback “permit”              |
| **Laravel Ready** | Use via Blade, controllers, middleware, or helpers |

---

## 🧾 License

MIT © Tetthys