# CustomTableNameTrait

`CustomTableNameTrait` allows using a custom table name for a model class.

## Methods

The following method is provided by the `CustomTableNameTrait`:

- `withTableName()` clones the current instance of Active Record then sets the table name for the new instance
  and returns the new instance.

## Usage

```php
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\CustomTableNameTrait;

final class User extends ActiveRecord
{
    use CustomTableNameTrait;
}

$user = new User();
$users = $user->createQuery()->all(); // Selects data from 'user' table
$users = $user->withTableName('custom_user_table')->createQuery()->all(); // Selects data from 'custom_user_table' table
```

Back to [Extending Functionality With Traits](traits.md).
