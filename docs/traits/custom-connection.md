# CustomConnectionTrait

`CustomConnectionTrait` allows using a custom database connection for a model class.

## Methods

The following method is provided by the `CustomConnectionTrait`:

- `withConnectionName()` clones the current instance of Active Record then sets the connection name for the new instance
  and returns the new instance.

## Usage

```php
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\CustomConnectionTrait;

final class User extends ActiveRecord
{
    use CustomConnectionTrait;
}

$user = new User();
$users = $user->createQuery()->all(); // Uses 'default' connection
$users = $user->withConnectionName('db2')->createQuery()->all(); // Uses 'db2' connection
```

Before using `db2` connection name, it should be configured in the application using
`\Yiisoft\ActiveRecord\ConnectionProvider::set($db2Connection, 'db2')` method where `$db2Connection` is an instance of 
`\Yiisoft\Db\Connection\ConnectionInterface`.

See how to [Define the Database Connection for Active Record](../define-connection.md).

Back to [Extending Functionality With Traits](traits.md).
