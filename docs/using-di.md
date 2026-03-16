# Using Dependency Injection With Active Record

Using [dependency injection](https://github.com/yiisoft/di) in the Active Record model allows injecting dependencies 
into the model and using them in the model methods.

To create an Active Record model with dependency injection, you need to use 
a [factory](https://github.com/yiisoft/factory) that will create an instance of the model and inject the dependencies 
into it.

## Define the Factory for Active Record

To use dependency injection with Active Record, you need to define the factory in `ActiveRecordFactory` class using one
of the following ways:

### Using the bootstrap configuration

Add the following code to the configuration file, for example, in `config/common/bootstrap.php`:

```php
use Psr\Container\ContainerInterface;
use Yiisoft\ActiveRecord\ActiveRecordFactory;
use Yiisoft\Factory\Factory;

return [
    static function (ContainerInterface $container): void {
        ActiveRecordFactory::setFactory($container->get(Factory::class));
    }
];
```

### Using DI container autowiring

You can set the factory for Active Record using the DI container autowiring.

```php
use Psr\Http\Message\ResponseInterface;
use Yiisoft\ActiveRecord\ActiveRecordFactory;
use Yiisoft\Factory\Factory;

final class SomeController
{
    public function someAction(Factory $factory): ResponseInterface
    {
        ActiveRecordFactory::setFactory($factory);
    
        // ...
    }
}
```

## Define The Active Record Model

Yii Active Record provides a [FactoryTrait](traits/factory.md) trait that allows using the factory with the Active Record class.

```php
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\FactoryTrait;

#[\AllowDynamicProperties]
final class User extends ActiveRecord
{
    use FactoryTrait;
    
    public function __construct(private MyService $myService) {}
}
```

Now you can create the Active Record instance or `ActiveQuery` instance using the factory.

```php
$user = User::instantiate();
$userQuery = User::query();
```

Back to [Create Active Record Model](create-model.md)
