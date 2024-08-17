# Active Record Relations

## Define Relations

### Overriding `relationQuery()`

To define the relations in the Active Record model, you need to override the `relationQuery()` method. This method 
should return an instance of `ActiveQueryInterface` for the relation name.

```php
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\ActiveQueryInterface;

final class User extends ActiveRecord
{
    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'profile' => $this->hasOne(Profile::class, ['id' => 'profile_id']),
            'orders' => $this->hasMany(Order::class, ['user_id' => 'id']),
            default => parent::relationQuery($name),
        };
    }
}
```

Also, you can use the `relationQuery()` method to get a relation query by name.

```php
$user = new User();

$profileQuery = $user->relationQuery('profile');
$ordersQuery = $user->relationQuery('orders');
```

### Using `MagicRelationsTrait`

Alternatively, you can use the `MagicRelationsTrait` to define relations in the Active Record model. This trait allows
you to define relation methods directly in the model without overriding the `relationQuery()` method. The relation
methods should have a specific naming convention to be recognized by the trait. The method names should have prefix 
`get` and suffix `Query` and returns an object implementing the `ActiveQueryInterface`.

```php
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\MagicRelationsTrait;

final class User extends ActiveRecord
{
    use MagicRelationsTrait;
    
    public function getProfileQuery(): ActiveQueryInterface
    {
        return $this->hasOne(Profile::class, ['id' => 'profile_id']);
    }
    
    public function getOrdersQuery(): ActiveQueryInterface
    {
        return $this->hasMany(Order::class, ['user_id' => 'id']);
    }
}
```

## Relation Methods

`ActiveRecord` class has two methods to define different relation types: `hasOne()` and `hasMany()`. Both methods have 
the same signature:

```php
public function hasOne(string|ActiveRecordInterface|Closure $class, array $link): ActiveQueryInterface;

public function hasMany(string|ActiveRecordInterface|Closure $class, array $link): ActiveQueryInterface;
```

### Relation Class

The `$class` parameter is the class name of the related record, or an instance of the related record, or a Closure to
create an `ActiveRecordInterface` object. For example: `Profile::class`, `new Profile()`, or `fn() => new Profile()`.

### Relation Link

The `$link` parameter is an array that defines the foreign key constraint. The keys of the array refer to the attributes
of the record associated with the `$class` model, while the values of the array refer to the corresponding attributes 
in the current Active Record class.

For example: `['id' => 'profile_id']`. In the example, `id` attribute of the related record is linked to `profile_id` 
attribute of the current record.

## Relation Types

### One-to-one 

To define a **one-to-one** relation, use the `hasOne()` method.

```php
$this->hasOne(Profile::class, ['id' => 'profile_id'])
```

### One-to-many

To define a **one-to-many** relation, use the `hasMany()` method.

```php
$this->hasMany(Order::class, ['user_id' => 'id'])
```

### Many-to-one

This type of relation is the same as the **one-to-one** relation, but the related record has many records associated 
with it. Use the `hasOne()` method to define this relation.

### Many-to-many

This is a complex relation type that сan be implemented in several ways. To define this relation use the `hasMany()` 
method.

#### Junction Table

This is the most common way to implement a **many-to-many** relation. You need to create a junction table that contains
foreign keys that reference the primary keys of the related tables.

```php
$this->hasMany(Group::class, ['id' => 'group_id'])->viaTable('user_group', ['user_id' => 'id']);
```

In the example, `user_group` table contains two foreign keys: `user_id` and `group_id`. The `user_id` attribute
references the `id` attribute of the current record, and the `group_id` attribute references the `id` attribute of the
related record.

#### Junction Model

This is the most flexible way to implement a **many-to-many** relation. You need to create a junction model that
represents the junction table.

```php
$this->hasMany(Group::class, ['id' => 'group_id'])->via(UserGroup::class, ['user_id' => 'id']);
```

In the example, `UserGroup` model represents the junction table `user_group`.

#### Array of Related Keys

This is the simplest way to implement a **many-to-many** relation. You don't need to create a junction table or a 
junction model to represent the junction table. Instead of this, you can use an array of related keys.

```php
$this->hasMany(Group::class, ['id' => 'group_ids']);
```

In this example, the `group_ids` attribute of the current record is an array of foreign keys that reference the `id`
attribute of the related record. The array attribute can be represented in the database as an `array` type (currently 
supported by `PgSQL` driver only) or as a `JSON` type (currently supported by `MySQL`, `PgSql`, and `SQLite` drivers).

## Accessing Relations

To get the related record, use the `ActiveRecordInterface::relation()` method. This method returns the related record(s)
or `null` (empty array for `AbstractActiveRecord::hasMany()` relation type) if the record(s) not found.

You can define getter methods to access the relations.

```php
public function getProfile(): Profile|null
{
    return $this->relation('profile');
}

/** @return Order[] */
public function getOrders(): array
{
    return $this->relation('orders');
}
```

## Usage

Now you can use `$user->getProfile()` and `$user->getOrders()` to access the relations.

```php
use Yiisoft\ActiveRecord\ActiveQuery;

$user = new User();

$profile = $user->getProfile();
$orders = $user->getOrders();
```

Back to

- [Create Active Record Model](create-model.md).
- [README](../README.md)
