<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use Closure;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordModelInterface;
use Yiisoft\Factory\Factory;

use function is_string;
use function method_exists;

/**
 * Trait to add factory support to ActiveRecordModel.
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

    public function instantiateQuery(string|ActiveRecordModelInterface|Closure $modelClass): ActiveQueryInterface
    {
        if (!isset($this->factory)) {
            return new ActiveQuery($modelClass);
        }

        if (is_string($modelClass)) {
            if (method_exists($modelClass, 'withFactory')) {
                return new ActiveQuery(
                    fn (): ActiveRecordModelInterface => $this->factory->create($modelClass)->withFactory($this->factory)
                );
            }

            return new ActiveQuery(fn (): ActiveRecordModelInterface => $this->factory->create($modelClass));
        }

        if ($modelClass instanceof ActiveRecordModelInterface && method_exists($modelClass, 'withFactory')) {
            return new ActiveQuery($modelClass->withFactory($this->factory));
        }

        return new ActiveQuery($modelClass);
    }
}
