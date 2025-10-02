# RepositoryTrait

`RepositoryTrait` provides methods to interact with a model as a repository to find one or multiple records.

## Methods

The following methods are provided by the `RepositoryTrait`:

- `find()` creates a new query instance with the given condition;
- `findAll()` finds all records matching the given condition;
- `findAllOrFail()` finds all records matching the given condition or throws an exception if none are found;
- `findByPk()` finds a record by its primary key;
- `findByPkOrFail()` finds a record by its primary key or throws an exception if not found;
- `findBySql()` creates a new query instance with the given SQL statement;
- `findOne()` finds a single record matching the given condition;
- `findOneOrFail()` finds a single record matching the given condition or throws an exception if not found.

## Usage

```php
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\RepositoryTrait;
use Yiisoft\ActiveRecord\NotFoundException;

final class User extends ActiveRecord
{
    use RepositoryTrait;
 
    public int $id;
    public bool $is_active;
}

$user = User::find(['id' => 1])->one();
$users = User::find(['is_active' => true])->limit(5)->all();

$user = User::findByPk(1);
$user = User::findByPkOrFail(1); // throws NotFoundException if not found

$user = User::findOne(['id' => 1]);
$user = User::findOneOrFail(['id' => 1]); // throws NotFoundException if not found

$users = User::findAll(['is_active' => true]);
$users = User::findAllOrFail(['is_active' => true]); // throws NotFoundException if not found

$users = User::findBySql('SELECT * FROM user')->all();
```

Back to [Extending Functionality With Traits](traits.md).
