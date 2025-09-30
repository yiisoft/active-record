# PrivatePropertiesTrait

`PrivatePropertiesTrait` allows using [private properties](../create-model.md#private-properties) in an Active Record class.

Private properties can be used to store model data securely, as they are not accessible from outside the class.

> [!NOTE]
> This trait is not required when using protected or public properties.

Without this trait, Active Record will not be able to access private properties for population and retrieval of values.

## Usage

```php
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\PrivatePropertiesTrait;

final class User extends ActiveRecord
{
    use PrivatePropertiesTrait;

    private int $id;
    private string $username;
    private string $email;
    private string $status = 'active';
    
    // Getters and setters as for the private properties
    // ...
}
```

Back to [Extending Functionality With Traits](traits.md).
