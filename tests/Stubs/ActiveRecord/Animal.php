<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class Animal.
 *
 * @property int $id
 * @property string $type
 */
class Animal extends ActiveRecord
{
    public string $does;

    public static function tableName(): string
    {
        return 'animal';
    }

    public function __construct()
    {
        $this->type = static::class;
    }

    public function getDoes(): string
    {
        return $this->does;
    }

    public static function instantiate($row): ActiveRecord
    {
        $class = $row['type'];

        return new $class();
    }
}
