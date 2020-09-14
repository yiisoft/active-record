<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use ReflectionClass;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * ProfileWithConstructor.
 *
 * @property int $id
 * @property string $description
 */
final class ProfileWithConstructor extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'profile';
    }

    public function __construct(string $description)
    {
        $this->description = $description;
    }

    public static function instance($refresh = false): ActiveRecord
    {
        return self::instantiate([]);
    }

    public static function instantiate($row): ActiveRecord
    {
        return (new ReflectionClass(static::class))->newInstanceWithoutConstructor();
    }
}
