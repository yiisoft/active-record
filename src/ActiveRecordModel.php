<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use function get_object_vars;

/**
 * Active Record class which implements {@see ActiveRecordModelInterface} interface with the minimum set of methods.
 *
 * Active Record implements the [Active Record design pattern](https://en.wikipedia.org/wiki/Active_record).
 *
 * The premise behind Active Record is that an individual {@see ActiveRecord} object is associated with a specific row
 * in a database table. The object's properties are mapped to the columns of the corresponding table.
 *
 * Referencing an Active Record property is equivalent to accessing the corresponding table column for that record.
 *
 * As an example, say that the `Customer` ActiveRecord class is associated with the `customer` table.
 *
 * This would mean that the class's `name` property is automatically mapped to the `name` column in `customer` table.
 * Thanks to Active Record, assuming the variable `$customer` is an object of type `Customer`, to get the value of the
 * `name` column for the table row, you can use the expression `$customer->name`.
 *
 * In this example, Active Record is providing an object-oriented interface for accessing data stored in the database.
 * But Active Record provides much more functionality than this.
 *
 * To declare an ActiveRecord class, you need to extend {@see ActiveRecordModel} and implement the `tableName()` method:
 *
 * ```php
 * class Customer extends ActiveRecordModel
 * {
 *     public static function tableName(): string
 *     {
 *         return 'customer';
 *     }
 * }
 * ```
 *
 * The `tableName()` method only has to return the name of the database table associated with the class.
 *
 * Class instances are obtained in one of two ways:
 *
 * Using the `new` operator to create a new, empty object.
 * Using a method to fetch an existing record (or records) from the database.
 *
 * Below is an example showing some typical usage of ActiveRecord:
 *
 * ```php
 * $user = new User();
 * $user->name = 'Qiang';
 * $user->save(); // a new row is inserted into user table
 *
 * // the following will retrieve the user 'CeBe' from the database
 * $userQuery = new ActiveQuery(User::class);
 * $user = $userQuery->where(['name' => 'CeBe'])->one();
 *
 * // this will get related records from orders table when relation is defined
 * $orders = $user->orders;
 * ```
 *
 * For more details and usage information on ActiveRecord,
 * {@see the [guide article on ActiveRecord](guide:db-active-record)}
 *
 * @psalm-suppress ClassMustBeFinal
 */
class ActiveRecordModel extends AbstractActiveRecordModel
{
    public function populateProperty(string $name, mixed $value): void
    {
        $this->$name = $value;
    }

    public function propertyValues(): array
    {
        return get_object_vars($this);
    }
}
