# Extending Functionality With Traits

The library provides several traits that can be used to extend the functionality of `ActiveRecord` models.
These traits can be included in your model classes to add specific behaviors or features.

- [ArrayableTrait](arrayable.md) provides `toArray()` method to convert a model to an array format;
- [ArrayAccessTrait](array-access.md) allows accessing model properties and relations using array syntax;
- [ArrayIteratorTrait](array-iterator.md) allows accessing model properties and relations iteratively;
- [CustomConnectionTrait](custom-connection.md) allows using a custom database connection for a model;
- [CustomTableNameTrait](custom-table-name.md) allows using a custom table name for a model;
- [EventsTrait](events.md) allows using events and handlers for a model;
- [FactoryTrait](factory.md) allows creating models and relations using [yiisoft/factory](https://github.com/yiisoft/factory);
- [MagicPropertiesTrait](magic-properties.md) stores properties in a private property and provides magic getters
  and setters for accessing the model properties and relations;
- [MagicRelationsTrait](magic-relations.md) allows using methods with prefix `get` and suffix `Query` to define
  relations (e.g. `getOrdersQuery()` for `orders` relation);
- [PrivatePropertiesTrait](private-properties.md) allows using [private properties](../create-model.md#private-properties) 
  in a model;
- [RepositoryTrait](repository.md) provides methods to interact with a model as a repository.

All traits are optional and can be used as needed. They can be combined to create models with the desired functionality.

For example, to create an Active Record class that supports array access and can be converted to an array:

```php
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\ArrayableTrait;
use Yiisoft\ActiveRecord\Trait\ArrayAccessTrait;
use Yiisoft\ActiveRecord\Trait\ArrayIteratorTrait;

class ArrayActiveRecord extends ActiveRecord
{
    use ArrayableTrait;
    use ArrayAccessTrait;
    use ArrayIteratorTrait;
}
```

Then you can create your model class by extending `ArrayActiveRecord`:

```php
final class User extends ArrayActiveRecord
{
    protected int $id;
    protected string $username;
    protected string $email;
    protected string $status = 'active';
}
```

Another example, to create an Active Record class that uses magic properties and relations:

```php
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\MagicPropertiesTrait;
use Yiisoft\ActiveRecord\Trait\MagicRelationsTrait;

class MagicActiveRecord extends ActiveRecord
{
    use MagicPropertiesTrait;
    use MagicRelationsTrait;
}
```

Then you can create your model class by extending `MagicActiveRecord`:

```php
/**
 * Entity User.
 *
 * @property int $id
 * @property string $username
 * @property string $email
 * @property string $status
 */
final class User extends MagicActiveRecord
{
}
```

Back to [README](../../README.md)
