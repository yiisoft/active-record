# FactoryTrait

`FactoryTrait` allows creating models and relations using [yiisoft/factory](https://github.com/yiisoft/factory).
This is required for [Using Dependency Injection With Active Record](../using-di.md).

## Methods

The following method is provided by the `FactoryTrait`:

- `withFactory()` clones the current instance of Active Record then sets the factory for the new instance and returns
  the new instance.

## Usage

```php
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\FactoryTrait;

final class User extends ActiveRecord
{
    use FactoryTrait;
    
    public function __construct(Factory $factory, private MyService $myService)
    {
        $this->factory = $factory;
    }
}

$user = $factory->create(User::class); // returns a new User instance with an initialized `Factory` and `MyService` instances.
```

If the `$factory` property is initialized, then the defined relations will be created using the same factory. 

See also

- [Using Dependency Injection With Active Record](../using-di.md);
- [Dependency Injection](https://github.com/yiisoft/docs/blob/master/guide/en/concept-di-container.md);
- [Factory](https://github.com/yiisoft/factory).

Back to [Extending Functionality With Traits](traits.md).
