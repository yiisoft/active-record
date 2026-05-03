# FactoryTrait

`FactoryTrait` allows creating models and relations using [yiisoft/factory](https://github.com/yiisoft/factory).
This is required for [Using Dependency Injection With Active Record](../using-di.md).

## Methods

The following method is provided by the `FactoryTrait`:

- `instantiate()` creates a new instance of the model with initialized dependencies using [yiisoft/factory](https://github.com/yiisoft/factory).

## Usage

```php
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\ActiveRecordFactory;
use Yiisoft\ActiveRecord\Trait\FactoryTrait;

final class User extends ActiveRecord
{
    use FactoryTrait;
    
    public function __construct(private MyService $myService) {}
}

$user = User::instantiate(); // returns a new User instance with an initialized `MyService` instance.
```

## See also

- [Using Dependency Injection With Active Record](../using-di.md);
- [Dependency Injection](https://github.com/yiisoft/docs/blob/master/guide/en/concept-di-container.md);
- [Factory](https://github.com/yiisoft/factory).

Back to [Extending Functionality With Traits](traits.md).
