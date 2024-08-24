# Create Active Record Model

To create an Active Record model, you need to create a class that extends `Yiisoft\ActiveRecord\ActiveRecord` and define
class properties, the table name and relations.

## Properties

### Dynamic properties

Easiest way to define properties is to use dynamic properties. This way you don't need to define properties explicitly.

```php
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Entity User.
 *
 * @property int $id
 * @property string $username
 * @property string $email
 * 
 * The properties in PHPDoc are optional and used by static analysis and by IDEs for autocompletion, type hinting, 
 * code generation and inspection tools. This does not affect code execution.
 **/
#[\AllowDynamicProperties]
final class User extends ActiveRecord
{
    public function getTableName(): string
    {
        return '{{%user}}';
    }
}
```

Now you can use `$user->id`, `$user->username`, `$user->email` to access the properties.

```php
$user = new User($db);

$user->username = 'admin';
$user->email = 'admin@example.net';

$user->save();
```

Notes:
- It needs to use the `#[\AllowDynamicProperties]` attribute to enable dynamic properties;
- ❌ It does not use strict typing and can be a reason of hard-to-detect errors;
- ❌ It is slower than explicitly defined properties, it is not optimized by PHP opcache and uses more memory;

### Public properties

To define properties explicitly, you can use `public` properties.

```php
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Entity User.
 **/
final class User extends ActiveRecord
{
    public int $id;
    public string $username;
    public string $email;
    public string $status = 'active';

    public function getTableName(): string
    {
        return '{{%user}}';
    }
}
```

As with dynamic properties, you can use `$user->id`, `$user->username`, `$user->email` to access the properties.

Notes:
- ✔️ It allows to use strict typing and define default values for properties;
- ✔️ It works faster than dynamic properties, optimized by PHP opcache and uses less memory;

### Protected properties (recommended)

To protect properties from being accessed directly, you can use `protected` properties.

```php
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Entity User.
 **/
final class User extends ActiveRecord
{
    protected int $id;
    protected string $username;
    protected string $email;
    protected string $status = 'active';

    public function getTableName(): string
    {
        return '{{%user}}';
    }
    
    public function getId(): int|null
    {
        return $this->id ?? null;
    }
    
    public function getUsername(): string|null
    {
        return $this->username ?? null;
    }
    
    public function getEmail(): string|null
    {
        return $this->email ?? null;
    }
    
    public function setId(int $id): void
    {
        $this->setAttribute('id', $id);
    }
    
    public function setUsername(string $username): void
    {
        $this->username = $username;
    }
    
    public function setEmail(string $email): void
    {
        $this->email = $email;
    }
}
```

Now you can use `$user->getId()`, `$user->getUsername()`, `$user->getEmail()` to access the properties.

```php
$user = new User($db);

$user->setUsername('admin');
$user->setEmail('admin@example.net');

$user->save();
```

Notes:
- To access properties, you need to define getter and setter methods.
- ✔️ It allows to use strict typing and define default values for properties;
- ✔️ It allows to access uninitialized properties, using **null coalescing operator** `return $this->id ?? null;`
- ✔️ It allows to reset relations when setting the property, using `ActiveRecordInterface::setAttribute()` method.

### Private properties

To use `private` properties inside the model class, you need to copy `getAttributesInternal()` and `populateAttribute()` 
methods from the `ActiveRecord` class and adjust them to work with the `private` properties.

```php
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Entity User.
 **/
final class User extends ActiveRecord
{
    private int $id;
    private string $username;
    private string $email;
    private string $status = 'active';

    public function getTableName(): string
    {
        return '{{%user}}';
    }
    
    // Getters and setters as for protected properties
    // ...

    // Copied `getAttributesInternal()` and `populateAttribute()` methods from `ActiveRecord` class
    protected function getAttributesInternal(): array
    {
        return get_object_vars($this);
    }
    
    protected function populateAttribute(string $name, mixed $value): void
    {
        $this->$name = $value;
    }
}
```

Private properties have the same benefits as protected properties, and they are more secure.
    
### Magic properties

You can also use magic properties to access properties.

```php
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\MagicPropertiesTrait;

/**
 * Entity User.
 *
 * @property int $id
 * @property string $username
 * @property string $email
 * 
 * The properties in PHPDoc are optional and used by static analysis and by IDEs for autocompletion, type hinting, 
 * code generation and inspection tools. This does not affect code execution.
 **/
final class User extends ActiveRecord
{
    use MagicPropertiesTrait;

    public function getTableName(): string
    {
        return '{{%user}}';
    }
}
```

You can use `$user->id`, `$user->username`, `$user->email` to access the properties as with dynamic properties.

Notes:
- It needs to use the `MagicPropertiesTrait` to enable magic properties;
- Compared to dynamic properties, they are stored in the `private array $attributes` property;
- ✔️ It allows to access relations as properties;
- ❌ It does not use strict typing and can be a reason of hard-to-detect errors;
- ❌ It is slower than explicitly defined properties, it is not optimized by PHP opcache and uses more memory.
  In some cases it can be 100 times slower than explicitly defined properties;

## Relations

To define relations, use the `ActiveRecordInterface::relationQuery()` method. This method should return an instance of 
`ActiveQueryInterface` for the relation. You can then define a getter method to access the relation.

To get the related record, use the `ActiveRecordInterface::relation()` method. This method returns the related record(s)
or `null` (empty array for `AbstractActiveRecord::hasMany()` relation type) if the record(s) not found.

```php
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Entity User.
 **/
final class User extends ActiveRecord
{
    public int $id;
    public string $username;
    public string $email;
    public int $profile_id;
    
    public function getTableName(): string
    {
        return '{{%user}}';
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'profile' => $this->hasOne(Profile::class, ['id' => 'profile_id']),
            'orders' => $this->hasMany(Order::class, ['user_id' => 'id']),
            default => parent::relationQuery($name),
        };
    }
    
    public function getProfile(): Profile|null
    {
        return $this->relation('profile');
    }
    
    /** @return Order[] */
    public function getOrders(): array
    {
        return $this->relation('orders');
    }
}
```

Now you can use `$user->getProfile()` and `$user->getOrders()` to access the relations.

```php
use Yiisoft\ActiveRecord\ActiveQuery;

$userQuery = new ActiveQuery(User::class, $db);

$user = $userQuery->where(['id' => 1])->one();

$profile = $user->getProfile();
$orders = $user->getOrders();
```

Also see [Using Dependency Injection With Active Record Model](docs/using-di.md).

Back to [README](../README.md)
