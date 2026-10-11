# LazyLoadGuardTrait

`LazyLoadGuardTrait` allows detecting N+1 queries caused by lazy loading of relations, i.e. reading a relation
that wasn't eager-loaded by `with()`.

```php
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\LazyLoadGuardTrait;

final class Customer extends ActiveRecord
{
    use LazyLoadGuardTrait;
}
```

Lazy loads are counted by [LazyLoadGuard](../../src/LazyLoadGuard.php) per result set, and reported according to
the mode starting from the second record of the same result set that reads the relation. One record reading a
relation isn't an N+1 problem, and neither are records that were each fetched by a query of their own, since there
is nothing to eager load across them:

```php
// Reported: one query for the customers, then one query per customer for the orders.
foreach (Customer::query()->all() as $customer) {
    $customer->getOrders();
}

// Not reported: each customer came from a query of its own.
Customer::query()->findByPk(1)->getOrders();
Customer::query()->findByPk(2)->getOrders();
```

The mode and the logger are set statically:

```php
use Yiisoft\ActiveRecord\LazyLoadGuard;
use Yiisoft\ActiveRecord\LazyLoadGuardMode;

LazyLoadGuard::set(LazyLoadGuardMode::Log, $logger);
```

| Mode     | Behavior                                                                                             |
|----------|------------------------------------------------------------------------------------------------------|
| `Log`    | Default. Reports a PSR-3 warning with the relation name, the count within the result set and a trace |
| `Strict` | Throws `LogicException`                                                                              |

In `Log` mode the logger is optional; when it's omitted the lazy loads are still counted and readable through
`LazyLoadGuard::getCounters()`, but nothing is written anywhere. `getCounters()` returns every lazy load per
relation, including the ones that weren't reported because they belong to result sets of their own.
`LazyLoadGuard::reset()` restores the defaults and clears the counters.

The counters live until `LazyLoadGuard::reset()` is called. In long-running workers call it after each request.

The guard is meant for development and testing, e.g. `Strict` in the test suite and `Log` on staging.

> [!NOTE]
> The trait overrides `retrieveRelation()` method. If another trait of the model overrides it too,
> resolve the conflict using `insteadof` and `as` operators.

Back to [Extending Functionality With Traits](traits.md).
