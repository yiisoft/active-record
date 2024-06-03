# Create Active Record Model

To create an Active Record model, you need to create a class that extends `Yiisoft\ActiveRecord\ActiveRecord` and define 
the table name and properties.

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
- ❌ It is not supported by static analysis, IDEs for autocompletion, type hinting, code generation and inspection tools;
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

    public function getTableName(): string
    {
        return '{{%user}}';
    }
}
```

As with dynamic properties, you can use `$user->id`, `$user->username`, `$user->email` to access the properties.

### Protected properties

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
- Do not use `private` properties, as it will cause an error when populating the model from the database or saving it 
  to the database;
- ✔️ It allows access to uninitialized properties, using **null coalescing operator** `return $this->id ?? null;`
- ✔️ It allows to reset relations when setting the property {@see ActiveRecordInterface::setAttribute()}.

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
- ❌ It is not supported by static analysis, IDEs for autocompletion, type hinting, code generation and inspection tools;
- ❌ It is slower than explicitly defined properties, it is not optimized by PHP opcache and uses more memory.
  In some cases it can be 200 times slower than explicitly defined properties;

## Relations

To define relations, use the {@see ActiveRecordInterface::relationQuery()} method. This method should return an
instance of {@see ActiveQueryInterface} for the relation. You can then define a getter method to access the relation.

To get the related record, use the {@see ActiveRecordInterface::relation()} method. This method returns the related 
record(s) or `null` (empty array for {@see AbstractActiveRecord::hasMany()} relation type) if the record(s) not found.

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

Now you can use `$user->getProfile()` and `$user->getOrders()` to access the relation.

```php
use Yiisoft\ActiveRecord\ActiveQuery;

$userQuery = new ActiveQuery(User::class, $db);

$user = $userQuery->where(['id' => 1])->onePopulate();

$profile = $user->getProfile();
$orders = $user->getOrders();
```
