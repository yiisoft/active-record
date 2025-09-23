# ArrayableTrait

`ArrayableTrait` implements the [\Yiisoft\Arrays\ArrayableInterface](https://github.com/yiisoft/arrays/blob/master/src/ArrayableInterface.php) interface and provides methods to convert 
an `ActiveRecord` instance to an array format, using the `fields()` and `extraFields()` methods to determine which 
properties and relations should be included in the output.

## Methods

The following methods are provided by the `ArrayableTrait`:

- `extraFields()` returns an associative array of relation names that can be included in the array representation 
  of the record, with keys equal to values;
- `fields()` returns an associative array of the record's property names, with keys equal to values;
- `toArray()` converts the `ActiveRecord` instance to an array using the defined fields and extra fields.

## Usage

```php
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\ArrayableTrait;
use Yiisoft\Arrays\ArrayableInterface;

final class User extends ActiveRecord implements ArrayableInterface
{
    use ArrayableTrait;

    // implement your own fields() and extraFields() if needed
}

$user = new User();
$data = $user->toArray(); // Converts the user instance to an array
```

## Overrides

You can override the `fields()` and `extraFields()` methods in your `ActiveRecord` class to customize which fields
and relations are included in the array representation.

Both `fields()` and `extraFields()` may return values that are either strings or `Closure` instances.

Back to [Extending with traits](traits.md).
