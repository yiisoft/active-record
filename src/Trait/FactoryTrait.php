<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

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

    public function createQuery(ActiveRecordInterface|string|null $modelClass = null): ActiveQueryInterface
    {
        if (!isset($this->factory)) {
            return parent::createQuery($modelClass);
        }

        $model = is_string($modelClass)
            ? $this->factory->create($modelClass)
            : $modelClass ?? $this;

        if (method_exists($model, 'withFactory')) {
            return parent::createQuery($model->withFactory($this->factory));
        }

        return parent::createQuery($model);
    }
}
