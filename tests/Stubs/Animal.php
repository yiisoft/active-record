<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs;

/**
 * Class Animal.
 *
 * @property int $id
 * @property string $type
 */
class Animal extends ActiveRecord
{
    public $does;

    public static function tableName(): string
    {
        return 'animal';
    }

    public function __construct()
    {
        $this->type = static::class;
    }

    public function getDoes()
    {
        return $this->does;
    }

    /**
     * @param array|object $row
     *
     * @return Animal
     */
    public static function instantiate($row): ActiveRecord
    {
        $class = $row['type'];

        return new $class();
    }
}
