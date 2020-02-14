<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs;

/**
 * ProfileWithConstructor.
 *
 * @property int $id
 * @property string $description
 */
class ProfileWithConstructor extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'profile';
    }

    public function __construct($description)
    {
        $this->description = $description;
        parent::__construct();
    }

    public static function instance($refresh = false): ActiveRecord
    {
        return self::instantiate([]);
    }

    public static function instantiate($row): ActiveRecord
    {
        return (new \ReflectionClass(static::class))->newInstanceWithoutConstructor();
    }
}
