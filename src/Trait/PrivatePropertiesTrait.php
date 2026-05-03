<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use Yiisoft\ActiveRecord\AbstractActiveRecord;

/**
 * Trait to handle private properties in Active Record classes.
 *
 * The trait required when using private properties inside the model class.
 *
 * @link https://github.com/yiisoft/active-record/blob/master/docs/create-model.md#private-properties
 *
 * @see AbstractActiveRecord::populateProperty()
 */
trait PrivatePropertiesTrait
{
    protected function propertyValueInternal(string $name): mixed
    {
        return $this->$name ?? null;
    }

    protected function populateProperty(string $name, mixed $value): void
    {
        $this->$name = $value;
    }
}
