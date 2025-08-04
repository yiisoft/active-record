<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use Closure;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\Factory\Factory;

use function is_string;
use function method_exists;

/**
 * Trait to add factory support to ActiveRecord.
 *
 * @see AbstractActiveRecord::createQuery()
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

    public function createQuery(ActiveRecordInterface|Closure|null|string $modelClass = null): ActiveQueryInterface
    {
        if (!isset($this->factory) || !$this->factory instanceof Factory) {
            return parent::createQuery($modelClass);
        }

        if (is_string($modelClass)) {
            if (method_exists($modelClass, 'withFactory')) {
                return parent::createQuery(
                    fn (): ActiveRecordInterface => $this->factory->create($modelClass)->withFactory($this->factory)
                );
            }

            return parent::createQuery(fn (): ActiveRecordInterface => $this->factory->create($modelClass));
        }

        $modelClass ??= $this;

        if ($modelClass instanceof ActiveRecordInterface && method_exists($modelClass, 'withFactory')) {
            return parent::createQuery($modelClass->withFactory($this->factory));
        }

        return parent::createQuery($modelClass);
    }
}
