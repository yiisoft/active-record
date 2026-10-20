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

// Not reported: one record reading the relation again after it was reset. Records are counted, not reads.
$customer = Customer::query()->findByPk(1);
$customer->getOrders();
$customer->refresh();
$customer->getOrders();
```

The model may use the trait through a parent class or through another trait of its own, which is where a
project-wide base trait usually puts it.

The mode and the logger are set statically:

```php
use Yiisoft\ActiveRecord\LazyLoadGuard;
use Yiisoft\ActiveRecord\LazyLoadGuardMode;

LazyLoadGuard::set(LazyLoadGuardMode::Log, $logger);
```

| Mode     | Behavior                                                                           |
|----------|------------------------------------------------------------------------------------|
| `Log`    | Default. Writes a PSR-3 warning; the context carries the model, relation and trace |
| `Strict` | Throws `LazyLoadException`                                                         |

Both modes report the same text, which names the relation, how many records of the result set read it and the
`with()` call that would replace them with one query:

```
Relation "App\Model\Customer::orders" is lazy loaded by 3 records of one result set.
Eager load it with "with('orders')" to run one query instead of 3.
```

In `Log` mode the logger is optional; when it's omitted the lazy loads are still counted and readable through
`LazyLoadGuard::getCounters()`, but nothing is written anywhere. `getCounters()` returns every lazy load per
relation, including the ones that weren't reported because they belong to result sets of their own.

The counters live until they're cleared. In long-running workers call `LazyLoadGuard::resetCounters()` after
each request, so that the result sets of one request aren't counted against the next; it keeps the mode and the
logger, so they're configured once:

```php
LazyLoadGuard::set(LazyLoadGuardMode::Log, $logger);

while ($request = $queue->next()) {
    $handler->handle($request);
    LazyLoadGuard::resetCounters();
}
```

In a test suite call `LazyLoadGuard::reset()` between tests, which clears the counters and restores the mode and
the logger to their defaults as well, so that the setup of one test doesn't leak into the next:

```php
protected function setUp(): void
{
    LazyLoadGuard::reset();
    LazyLoadGuard::set(LazyLoadGuardMode::Strict);
}
```

The guard is meant for development and testing, e.g. `Strict` in the test suite and `Log` on staging.

> [!NOTE]
> The trait overrides `retrieveRelation()` method. If another trait of the model overrides it too,
> resolve the conflict using `insteadof` and `as` operators.

Back to [Extending Functionality With Traits](traits.md).
