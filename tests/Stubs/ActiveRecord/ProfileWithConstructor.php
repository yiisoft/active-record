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
    public function tableName(): string
    {
        return 'profile';
    }

    public function __construct(string $description)
    {
        $this->description = $description;
    }

    public function instance($refresh = false): ActiveRecord
    {
        return self::instantiate([]);
    }

    public function instantiate($row): ActiveRecord
    {
        $class = new ReflectionClass(static::class);

        return $class->newInstanceWithoutConstructor();
    }
}
