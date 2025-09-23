# ArrayAccessTrait

`ArrayAccessTrait` implements [ArrayAccess](https://www.php.net/manual/en/class.arrayaccess.php) interface and allows accessing model properties and relations using array
syntax.

## Methods

The following methods are provided by the `ArrayAccessTrait`:

- `offsetExists($offset)` checks if the specified property or relation exists and is not null;
- `offsetGet($offset)` retrieves the value of the specified property or relation;
- `offsetSet($offset, $value)` sets the value of the specified property or relation;
- `offsetUnset($offset)` unsets or sets to null the specified property or relation.

## Usage

```php
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\ArrayAccessTrait;

final class User extends ActiveRecord implements ArrayAccess
{
    use ArrayAccessTrait;
    
    protected string $username;
}

$user = new User();
$user['username'] = 'admin'; // Set the property value using array syntax
echo $user['username']; // Get the property value using array syntax
unset($user['username']); // Unset the property or set it to null using array syntax
isset($user['username']); // Check if the property exists and is not null using array syntax
```

Back to [Extending Functionality With Traits](traits.md).
