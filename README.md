# Tetthys/Cake

> Functional & Layered Business Authorization for Laravel

**Cake** brings a functional approach to complex business authorization.
Instead of scattering `if` statements across controllers or policies, you declare composable **rules** that describe **who** can do **what** under **which conditions**.

It’s simple, expressive, and safe — **deny-by-default**.

---

## 🧩 Concept Overview

Authorization in Cake is expressed as:

```
Decision = OR_i (Sᵢ ∧ Dᵢ)
```

* **S (SubjectPredicate)** → Who is the actor? (roles, identity, membership)
* **D (DomainPredicate)** → Under what conditions? (object state, context, timing)

If **any** `(S ∧ D)` pair is true, access is granted (`PERMIT`);
otherwise, it’s denied by default (`DENY`).

---

## 🚀 Why Cake?

* ✅ **Separation of concerns** — keep “who” vs “when/what” clearly distinct
* ✅ **Composable** — use functional `Combinators` for OR / AND / NOT
* ✅ **Framework-agnostic core**, with **Laravel integration** out of the box
* ✅ **Testable** — pure functions, no global state
* ✅ **Secure by design** — deny-by-default, no accidental leaks

---

## ⚙️ Installation

```bash
composer require tetthys/cake
```

Laravel will auto-discover the service provider:

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

## 🧠 Core Idea: S ∧ D → Decision

Every `Rule` combines:

| Component | Meaning                       | Example                        |
| --------- | ----------------------------- | ------------------------------ |
| `S`       | Subject predicate (who?)      | `$u->hasRole('admin')`         |
| `D`       | Domain predicate (when/what?) | `$o->data->status === 'draft'` |

Access is **granted** if any rule’s S∧D returns true.

---

## 🪄 Quick Start (Blog Example)

### 1. Define Rules

```php
// app/Policies/PostRules.php
namespace App\Policies;

use Illuminate\Http\Request;
use Tetthys\Cake\Rule\{Rule, RuleSet, Combinators as C, Pred};

final class PostRules
{
    /** Anyone can view if published; owner can always view */
    public function view(Request $request): RuleSet
    {
        return new RuleSet([
            new Rule(
                'Owner_Always',
                Pred::S(fn($u, $a, $o, $c) => $u->id === $o->data->user_id),
                Pred::D(fn() => true)
            ),
            new Rule(
                'Published_ForAll',
                Pred::S(fn() => true),
                Pred::D(fn($u, $a, $o, $c) => $o->data->status === 'published')
            ),
        ]);
    }

    /** Only owner or admin can update when post is draft */
    public function update(Request $request): RuleSet
    {
        $post = $request->route('post');

        return new RuleSet([
            new Rule(
                'OwnerOrAdmin_WhenDraft',
                C::S_or(
                    Pred::S(fn($u) => $u->id === $post->user_id),
                    Pred::S(fn($u) => $u?->isAdmin() ?? false)
                ),
                Pred::D(fn($u, $a, $o, $c) => $o->data->status === 'draft')
            ),
        ]);
    }
}
```

---

### 2. Apply in Controllers or Middleware

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

        // Continue only if authorized
        return response()->json(['status' => 'ok', 'authorized' => $decision->isPermit()]);
    }
}
```

Or attach via middleware:

```php
Route::put('posts/{post}', [PostController::class, 'update'])
    ->middleware('cake:post.update,App\\Policies\\PostRules@update')
    ->name('post.update');
```

---

## 🧱 Built-in Helpers

### Predicates

Wrap any closure into a functional predicate:

```php
Pred::S(fn($u, $a, $o, $c) => $u->id === $o->data->user_id); // SubjectPredicate
Pred::D(fn($u, $a, $o, $c) => $o->data->status === 'draft'); // DomainPredicate
```

### Combinators

Compose predicates:

```php
use Tetthys\Cake\Rule\Combinators as C;

C::S_or($s1, $s2);  // subject1 OR subject2
C::S_and($s1, $s2); // both must hold
C::D_not($d);       // negate a domain predicate
```

---

## 🧪 Testing Rules

Cake is designed for **pure, unit-testable logic**.

```php
use Tetthys\Cake\Engine\Engine;
use Tetthys\Cake\Model\{Actor, Action, ObjectRef, Context};
use App\Policies\PostRules;

$engine = app(Engine::class);

$decision = $engine->decide(
    new Actor('u-1', ['user']),
    new Action('post.update'),
    new ObjectRef('Post', (object)['user_id' => 'u-1', 'status' => 'draft']),
    new Context(['ip' => '127.0.0.1']),
    app(PostRules::class)->update(request())
);

expect($decision->isPermit())->toBeTrue();
expect($decision->selectedRule)->toBe('OwnerOrAdmin_WhenDraft');
```

---

## 🔍 Example Decisions

| Situation               | Expected | Rule Triggered           |
| ----------------------- | -------- | ------------------------ |
| Owner updating draft    | ✅ Permit | `OwnerOrAdmin_WhenDraft` |
| Admin updating draft    | ✅ Permit | `OwnerOrAdmin_WhenDraft` |
| User updating published | ❌ Deny   | (no rule matched)        |

---

## 🧩 Advanced Usage

### Combine Predicates Dynamically

```php
use Tetthys\Cake\Rule\{Pred, Combinators as C};

$isAdmin   = Pred::S(fn($u) => $u->role === 'admin');
$isOwner   = Pred::S(fn($u, $a, $o) => $o->data->user_id === $u->id);
$isDraft   = Pred::D(fn($u, $a, $o) => $o->data->status === 'draft');

$rule = new Rule('AdminOrOwner_WhenDraft', C::S_or($isAdmin, $isOwner), $isDraft);
```

---

## 🧭 Integration Points

| Layer                 | How to Integrate                                                  |
| --------------------- | ----------------------------------------------------------------- |
| **Controllers**       | `use AuthorizesRequest` trait                                     |
| **Middleware**        | `cake:action,Policy@method`                                       |
| **Custom Resolvers**  | Implement `ActorResolver` to derive actor from JWT, API key, etc. |
| **Custom Responders** | Replace `DefaultJson403Responder` for custom error formats        |

---

## ✅ Summary

| Principle         | Description                                                   |
| ----------------- | ------------------------------------------------------------- |
| **Declarative**   | Express access logic in `RuleSet`, not imperative `if` chains |
| **Composable**    | Build logic with `Combinators` and reusable `Pred` blocks     |
| **Secure**        | Deny-by-default for unhandled cases                           |
| **Laravel-Ready** | Works with routes, middleware, controllers                    |

---

## 🧾 License

MIT © Tetthys