# Using Dependency Injection With Active Record

Using [dependency injection](https://github.com/yiisoft/di) in the Active Record model allows injecting dependencies 
into the model and use them in the model methods.

To create an Active Record model with dependency injection, you need to use 
a [factory](https://github.com/yiisoft/factory) that will create an instance of the model and inject the dependencies 
into it.

## Define The Active Record Model

Yii Active Record provides a `FactoryTrait` trait that allows to use the factory with the Active Record class.

```php
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\FactoryTrait;

#[\AllowDynamicProperties]
final class User extends ActiveRecord
{
    use FactoryTrait;
    
    public function __construct(private MyService $myService)
    {
    }
}
```

When you use dependency injection in the Active Record model, you need to create the Active Record instance using 
the factory.

```php
/** @var \Yiisoft\Factory\Factory $factory */
$user = $factory->create(User::class);
```

To create `ActiveQuery` instance you also need to use the factory to create the Active Record model.

```php
$userQuery = new ActiveQuery($factory->create(User::class)->withFactory($factory));
```

## Factory Parameter In The Constructor

Optionally, you can define the factory parameter in the constructor of the Active Record class.

```php
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\FactoryTrait;

#[\AllowDynamicProperties]
final class User extends ActiveRecord
{
    use FactoryTrait;
    
    public function __construct(Factory $factory, private MyService $myService)
    {
        $this->factory = $factory;
    }
}
```

This will allow creating the `ActiveQuery` instance without calling `ActiveRecord::withFactory()` method.

```php
$userQuery = new ActiveQuery($factory->create(User::class));
```

Back to [Create Active Record Model](create-model.md)
