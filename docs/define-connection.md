# Define the Database Connection for Active Record

Active Record requires a DB connection to be configured.

> [!NOTE]
> If you are using `yiisoft/config` to configure the application, it will configure the DB connection automatically.

By default, the `yiisoft/config` plugin configures the `default` DB connection for Active Record using
`ConnectionInterface::class` from the container.

If you do not use `yiisoft/config`, or you need to configure a different connection instance, define the DB connection
for Active Record in one of the following ways:

## Using the bootstrap configuration

Add the following code to the configuration file, for example, in `config/common/bootstrap.php`:

```php
use Psr\Container\ContainerInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Connection\ConnectionInterface;

return [
    static function (ContainerInterface $container): void {
        ConnectionProvider::set($container->get(ConnectionInterface::class));
    }
];
```

To configure a named connection, pass its name as the second argument:

```php
ConnectionProvider::set($container->get(ConnectionInterface::class), 'db2');
```

To use a named connection in a model, use
[CustomConnectionTrait](traits/custom-connection.md) or override `db()`.

## Using DI container autowiring

You can set the DB connection for Active Record using DI container autowiring.

```php
use Psr\Http\Message\ResponseInterface;
use Yiisoft\Db\Connection\ConnectionProvider;
use Yiisoft\Db\Connection\ConnectionInterface;

final class SomeController
{
    public function someAction(ConnectionInterface $db): ResponseInterface
    {
        ConnectionProvider::set($db);

        // ...
    }
}
```

## Using dependency injection

Another way is to inject the DB connection into a specific model and override `db()`.

```php
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\Db\Connection\ConnectionInterface;

final class User extends ActiveRecord
{
    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    public function db(): ConnectionInterface
    {
        return $this->db;
    }
}
```

Then, you can create the model using the factory:

```php
/** @var \Yiisoft\Factory\Factory $factory */
$user = $factory->create(User::class);
```

Back to [README](../README.md)
