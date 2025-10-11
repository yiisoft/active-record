# MagicRelationsTrait

`MagicRelationsTrait` allows using methods with the prefix `get` and suffix `Query` to define relations 
in an Active Record model. For example, a method named `getOrdersQuery()` can be used to define a relation named `orders`.

## Methods

The following method is provided by the `MagicRelationsTrait`:

- `relationNames()` returns names of all relations defined in the Active Record class using getter methods with
  `get` prefix and `Query` suffix.

## Usage

```php
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\MagicRelationsTrait;

final class User extends ActiveRecord
{
    use MagicRelationsTrait;
    
    public function getProfileQuery(): ActiveQueryInterface
    {
        return $this->hasOne(Profile::class, ['id' => 'profile_id']);
    }
    
    public function getOrdersQuery(): ActiveQueryInterface
    {
        return $this->hasMany(Order::class, ['user_id' => 'id']);
    }
}

$user = new User();
$orders = $user->relation('orders'); // Access the "orders" relation defined by the getOrdersQuery() method
$user->relationNames(); // Returns ['profile', 'orders']
```

See more how to [Define Relations](../define-relations.md).

Back to [Extending Functionality With Traits](traits.md).
