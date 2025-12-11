# MagicPropertiesTrait (Optional)

`MagicPropertiesTrait` is an **optional** trait that allows to use magic getters and setters to access model properties
and relations. It stores properties in the `private array $propertyValues` property and provides magic methods for
accessing them.

It also allows to call getter and setter methods as a property if they are defined in the model class
(e.g. `getFullName()` and `setFullName($fullName)` for `fullName` property).

> [!NOTE]
> **This trait is optional and not required** when using private, protected, public or dynamic properties.
> For better performance and type safety, prefer explicit property definitions.

> [!IMPORTANT]
> - ✔️ It allows accessing relations as properties;
> - ❌ It doesn't use strict typing and can be a reason of hard-to-detect errors; 
> - ❌ It is slower than explicitly defined properties, it is not optimized by PHP opcache and uses more memory.
> Sometimes it can be 100 times slower than explicitly defined properties;

## Methods

The following methods are provided by the `MagicPropertiesTrait`:

- `__get()` retrieves the value of the specified property or relation using magic getter;
- `__set()` sets the value of the specified property or relation using magic setter;
- `__isset()` checks if the specified property or relation exists and is not null using magic isset;
- `__unset()` unsets or sets to null the specified property or relation using magic unset;
- `hasRelationQuery()` checks if the specified relation query exists;
- `isProperty()` checks if the specified property exists;
- `canGetProperty()` checks if the specified property can be gotten;
- `canSetProperty()` checks if the specified property can be set.

## Usage

```php
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\MagicPropertiesTrait;

/**
 * Entity User.
 *
 * @property int $id
 * @property string $firstName
 * @property string $lastName
 * @property string $fullName
 * 
 * The properties in PHPDoc are optional and used by static analysis and by IDEs for autocompletion, type hinting, 
 * code generation, and inspection tools. This doesn't affect code execution.
 **/
final class User extends ActiveRecord
{
    use MagicPropertiesTrait;
    
    public function getProfileQuery(): ActiveQueryInterface
    {
        return $this->hasOne(Profile::class, ['id' => 'profile_id']);
    }
    
    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }
    
    public function setFullName(string $fullName): void
    {
        [$this->firstName, $this->lastName] = explode(' ', $fullName, 2);
    }
}

$user = new User();
$user->firstName = 'John'; // Set the property value using magic setter
$user->fullName = 'John Smith'; // Set the property values using setter method
echo $user->firstName; // Get the property value using magic getter
echo $user->fullName; // Get the property value using getter method
unset($user->firstName); // Unset the property or set it to null using magic unset
isset($user->firstName); // Check if the property exists and is not null using magic isset

$user->hasRelationQuery('profile') // Check if the relation query exists
$user->isProperty('fullName') // Check if the property exists (can be read or set)
$user->canGetProperty('fullName') // Check if the property can be read
$user->canSetProperty('fullName'); // Check if the property can be set
```

Usually `MagicPropertiesTrait` and [MagicRelationsTrait](magic-relations.md) are used together to provide full magic functionality 
for properties and relations.

```php
final class User extends ActiveRecord
{
    use MagicPropertiesTrait;
    use MagicRelationsTrait;
}
```

See more how to [Create Active Record Model](../create-model.md).

Back to [Extending Functionality With Traits](traits.md).
