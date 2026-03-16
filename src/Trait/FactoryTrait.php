<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use Yiisoft\ActiveRecord\ActiveRecordFactory;

/**
 * Trait to add factory support to an active record model by using {@see ActiveRecordFactory} class.
 *
 * @see AbstractActiveRecord::instantiate()
 */
trait FactoryTrait
{
    /**
     * Creates a new instance of the active record class.
     */
    public static function instantiate(): static
    {
        /** @var static */
        return ActiveRecordFactory::create(static::class);
    }
}
