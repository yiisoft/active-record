<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

/**
 * StaticInstanceTrait provides methods to satisfy {@see StaticInstanceInterface} interface.
 *
 * @see StaticInstanceInterface
 */
trait StaticInstanceTrait
{
    /**
     * @var static[] static instances in format: `[className => object]`
     */
    private static array $instances = [];


    /**
     * Returns static class instance, which can be used to obtain meta information.
     *
     * @param bool $refresh whether to re-create static instance even, if it is already cached.
     *
     * @return self class instance.
     */
    public static function instance($refresh = false): self
    {
        $className = static::class;

        if ($refresh || !isset(self::$instances[$className])) {
            self::$instances[$className] = new $className();
        }

        return self::$instances[$className];
    }
}
