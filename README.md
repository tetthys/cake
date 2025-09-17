# Tetthys/Cake

> Functional & Layered Business Authorization for Laravel

Cake provides a **simple but rigorous authorization model** for business rules.  
Instead of scattering `if` checks everywhere, you define **rules** (`RuleSet`) that combine:

- **S (SubjectPredicate)** → Who is trying to do the action?  
- **D (DomainPredicate)** → Under what conditions on the object/context?  

Access is granted **only if** S ∧ D is satisfied.  
Otherwise: **deny-by-default**.

---

## Why Cake?

- ✅ Clear separation of **who** (roles, ownership) vs **when/what** (domain state)  
- ✅ Composable rules (use `Combinators` for OR/AND/NOT)  
- ✅ Laravel-ready: works with middleware & policies  
- ✅ Testable: `RuleSet` is pure logic, easy to unit test  
- ✅ Deny-by-default: safe by construction

---

## Quick Example (Blog Post)

### 1. Define Rules

```php
// app/Policies/PostRules.php
namespace App\Policies;

use Illuminate\Http\Request;
use Tetthys\Cake\Rule\{Rule, RuleSet, Combinators as C};
use Tetthys\Cake\Rule\Pred;

final class PostRules
{
    /**
     * Action: post.view
     * - Anyone can view if post is published
     * - Owner can always view
     */
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

    /**
     * Action: post.update
     * - Only owner or admin, when post is still a draft
     */
    public function update(Request $request): RuleSet
    {
        $user = $request->user();

        return new RuleSet([
            new Rule(
                'OwnerOrAdmin_WhenDraft',
                C::S_or(
                    Pred::S(fn($u) => $u->id === $request->route('post')->user_id),
                    Pred::S(fn() => $user?->isAdmin() ?? false)
                ),
                Pred::D(fn($u, $a, $o, $c) => $o->data->status === 'draft')
            ),
        ]);
    }
}
````

### 2. Use in Routes

```php
use App\Http\Controllers\PostController;

Route::get('posts/{post}', [PostController::class, 'show'])
    ->middleware('cake:post.view,App\\Policies\\PostRules@view')
    ->name('post.view');

Route::put('posts/{post}', [PostController::class, 'update'])
    ->middleware('cake:post.update,App\\Policies\\PostRules@update')
    ->name('post.update');
```

---

## Built-in Helpers

You can compose rules with small building blocks.

### Predicates

```php
Pred::S(fn($u, $a, $o, $c) => $u->id === $o->data->user_id)   // Subject
Pred::D(fn($u, $a, $o, $c) => $o->data->status === 'draft')   // Domain
```

### Combinators

```php
C::S_or($s1, $s2)  // subject1 OR subject2
C::S_and($s1, $s2) // both must hold
C::D_not($d)       // domain negation
```

---

## End-to-End Example

### Owner can always view

### Everyone can view only when published

```php
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
```

---

## Installation

```bash
composer require tetthys/cake:^0.0.2
```

Laravel will auto-discover the service provider.

---

## Testing a Rule

```php
use Tetthys\Cake\Engine\Engine;
use Tetthys\Cake\Model\{Actor, Action, ObjectRef, Context};

$engine = app(Engine::class);

$decision = $engine->decide(
    new Actor('1', [], []),
    new Action('post.view'),
    new ObjectRef('Post', $post),
    new Context([]),
    app(\App\Policies\PostRules::class)->view(request())
);

if ($decision->isPermit()) {
    // allowed
} else {
    // denied
}
```

---

## Summary

* **Declarative**: describe who + when in one place
* **Composable**: reuse predicates, combine them with `Combinators`
* **Safe**: deny-by-default, so you never forget an edge case
* **Laravel-friendly**: drop-in middleware `cake:action,Policy@method`

Cake makes your **authorization layer as clean and testable** as the rest of your code.

---

## License

MIT © Tetthys