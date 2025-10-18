# Define the Database Connection for Active Record

To use the Active Record, you need to define the DB connection in one of the following ways:

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

## Using DI container autowiring

You can set the DB connection for Active Record using the DI container autowiring.

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

Another way to define the DB connection for Active Record is to use dependency injection.

```php
use Yiisoft\Db\Connection\ConnectionInterface;

class User extends ActiveRecord
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

```php
/** @var \Yiisoft\Factory\Factory $factory */
$user = $factory->create(User::class);
```

Back to [README](../README.md)
