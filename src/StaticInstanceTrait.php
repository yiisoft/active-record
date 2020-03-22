<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

/**
 * StaticInstanceTrait provides methods to satisfy [[StaticInstanceInterface]] interface.
 *
 * @see StaticInstanceInterface
 */
trait StaticInstanceTrait
{
    /**
     * @var static[] static instances in format: `[className => object]`
     */
    private static $instances = [];


    /**
     * Returns static class instance, which can be used to obtain meta information.
     *
     * @param bool $refresh whether to re-create static instance even, if it is already cached.
     * @return static class instance.
     */
    public static function instance($refresh = false)
    {
        $className = \get_called_class();

        if ($refresh || !isset(self::$instances[$className])) {
            self::$instances[$className] = new $className();
        }

        return self::$instances[$className];
    }
}
