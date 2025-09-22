# ArrayIteratorTrait

`ArrayIteratorTrait` implements [IteratorAggregate](https://www.php.net/manual/en/class.iteratoraggregate.php) interface and allows accessing model properties iteratively.

## Methods

The following method is provided by the `ArrayIteratorTrait`:

- `getIterator()` returns an `ArrayIterator` instance that allows iterating over the model's properties.

## Usage

```php
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\ArrayIteratorTrait;

final class User extends ActiveRecord implements IteratorAggregate
{
    use ArrayIteratorTrait;

    public string $username;
    public string $email;
}

$user = new User();
$user->username = 'admin';
$user->email = 'admin@example.com';

foreach ($user as $property => $value) {
    echo "$property: $value\n"; // Iterates over the model's properties
}
```

Back to [Extending with traits](traits.md).
