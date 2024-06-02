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
 * Database fields:
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

### Public properties

To define properties explicitly, you can use public properties.

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

To protect properties from being accessed directly, you can use protected properties.

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
    
    public function getId(): int
    {
        return $this->id;
    }
    
    public function getUsername(): string
    {
        return $this->username;
    }
    
    public function getEmail(): string
    {
        return $this->email;
    }
    
    public function setId(int $id): void
    {
        $this->id = $id;
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

### Magic properties

You can also use magic properties to access properties.

```php
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\MagicPropertiesTrait;

/**
 * Entity User.
 *
 * Database fields:
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

## Relations

To define relations, you can use the {@see ActiveRecordInterface::relationQuery()} method. This method should return an
instance of {@see ActiveQueryInterface} for the relation. You can then define a getter method to access the relation.

To get the related record, you can use the {@see ActiveRecordInterface::relation()} method. This method should return 
the related record(s) or `null` (empty array for {@see AbstractActiveRecord::hasMany()} relation type) if the record(s) 
not found.

```php
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Entity User.
 *
 * Database fields:
 * @property int $id
 * @property string $username
 * @property string $email
 * @property int $profile_id
 **/
#[\AllowDynamicProperties]
final class User extends ActiveRecord
{
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

Now you can use `$user->getProfile()` to access the relation.

```php
use Yiisoft\ActiveRecord\ActiveQuery;

$userQuery = new ActiveQuery(User::class, $db);

$user = $userQuery->where(['id' => 1])->onePopulate();

$profile = $user->getProfile();

$orders = $user->getOrders();
```
