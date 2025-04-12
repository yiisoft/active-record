<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use Closure;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\Factory\Factory;

use function is_string;
use function method_exists;

/**
 * Trait to add factory support to ActiveRecord.
 *
 * @see AbstractActiveRecord::instantiateQuery()
 */
trait FactoryTrait
{
    private Factory $factory;

    /**
     * Set the factory to use for creating new instances.
     */
    public function withFactory(Factory $factory): static
    {
        $new = clone $this;
        $new->factory = $factory;
        return $new;
    }

    public function instantiateQuery(string|ActiveRecordInterface|Closure $arClass): ActiveQueryInterface
    {
        if (!isset($this->factory)) {
            return new ActiveQuery($arClass);
        }

        if (is_string($arClass)) {
            if (method_exists($arClass, 'withFactory')) {
                return new ActiveQuery(
                    fn (): ActiveRecordInterface => $this->factory->create($arClass)->withFactory($this->factory)
                );
            }

            return new ActiveQuery(fn (): ActiveRecordInterface => $this->factory->create($arClass));
        }

        if ($arClass instanceof ActiveRecordInterface && method_exists($arClass, 'withFactory')) {
            return new ActiveQuery($arClass->withFactory($this->factory));
        }

        return new ActiveQuery($arClass);
    }
}
