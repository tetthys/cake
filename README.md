# tetthys/cake

**Functional, Layered Business Authorization for PHP & Laravel**

`tetthys/cake` provides a reusable authorization engine based on the idea of  
**layering subject-level checks (who the user is) and domain-level checks (what the object/context allows)**.  
It is framework-agnostic but ships with helpers for Laravel integration.

---

## 🔑 Core Idea

Every authorization rule is defined as:

$$
R(u, a, o, c) = S(u, a, o, c) \; \land \; D(u, a, o, c)
$$

- **S** → Subject condition (user role, identity, authentication, global permissions)  
- **D** → Domain condition (object state, relationships, business rules, context)  
- **R** → Authorization rule. Permit only if both S and D are true.  

Multiple rules can be combined:

$$
R = (S_1 \land D_1) \; \lor \; (S_2 \land D_2) \; \lor \; \dots \; \lor \; (S_n \land D_n)
$$

If no rule matches → **deny by default**.

---

## 🏗️ Layered Architecture

```mermaid
flowchart TD
    A[Request Entry] --> B[Middleware Layer]
    B -->|Check S (Subject)| C[Business Service Layer]
    C -->|Check D (Domain)| D[Permit or Deny]

    B -.->|Fail| X[403 Forbidden]
    C -.->|Fail| X
````

* **Middleware layer** → subject checks (roles, auth, global flags)
* **Service/domain layer** → domain checks (object state, business rules)
* **Defense in depth** → both layers must succeed.

---

## ✨ Features

* **Formal but simple model**: `R = S ∧ D`
* **Composable**: combine rules with AND / OR / NOT
* **Deny-by-default**: safety guaranteed
* **Functional style**: policies are pure functions, easy to test
* **Explainable decisions**: trace shows why access was allowed/denied
* **Laravel ready**: middleware and traits included

---

## 📦 Installation

```bash
composer require tetthys/cake
```

---

## 🚀 Quick Example

```php
use Tetthys\Cake\Model\Actor;
use Tetthys\Cake\Model\Action;
use Tetthys\Cake\Model\ObjectRef;
use Tetthys\Cake\Model\Context;
use Tetthys\Cake\Engine\Engine;
use Tetthys\Cake\Rule\Rule;
use Tetthys\Cake\Rule\RuleSet;
use Tetthys\Cake\Contracts\SubjectPredicate;
use Tetthys\Cake\Contracts\DomainPredicate;

$isManager = new class implements SubjectPredicate {
    public function __invoke($u, $a, $o, $c): bool {
        return in_array('manager', $u->roles, true);
    }
};

$isLargePendingOrder = new class implements DomainPredicate {
    public function __invoke($u, $a, $o, $c): bool {
        return ($o->data->status ?? null) === 'PENDING'
            && ($o->data->amount ?? 0) >= 10_000_000;
    }
};

$rules = new RuleSet([
    new Rule('ManagerCanApprove', $isManager, $isLargePendingOrder),
]);

$engine = new Engine();

$actor   = new Actor(id: 1, roles: ['manager']);
$action  = new Action('order.approve');
$object  = new ObjectRef('Order', (object)['status' => 'PENDING', 'amount' => 12_000_000]);
$context = new Context([]);

$decision = $engine->decide($actor, $action, $object, $context, $rules);

echo $decision->outcome; // "PERMIT"
print_r($decision->trace);
```

---

## 🧩 Laravel Integration

### 1. Register middleware

```php
// app/Http/Kernel.php
protected $routeMiddleware = [
    'cake' => \Tetthys\Cake\Integration\Laravel\AuthorizationMiddleware::class,
];
```

### 2. Define rules factory

```php
final class OrderRules {
    public function approve(Request $request): RuleSet {
        // return a RuleSet with subject/domain predicates
    }
}
```

### 3. Protect a route

```php
Route::post('/orders/{order}/approve', [OrderController::class, 'approve'])
    ->middleware('cake:order.approve,App\\Policies\\OrderRules@approve');
```

---

## 🧪 Testing

This repo uses [Pest](https://pestphp.com).
We include Docker setup for isolated test runs:

```bash
bash ./run/test.sh
```

Filter tests:

```bash
bash ./run/test.sh --filter Engine
```

---

## 📝 License

MIT © Tetthys