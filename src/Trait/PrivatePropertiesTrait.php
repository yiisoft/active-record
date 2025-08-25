<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use Yiisoft\ActiveRecord\AbstractActiveRecord;

use function get_object_vars;

/**
 * Trait to handle private properties in Active Record classes.
 *
 * The trait required when using private properties inside the model class.
 *
 * @link https://github.com/yiisoft/active-record/blob/master/docs/create-model.md#private-properties
 *
 * @see AbstractActiveRecord::propertyValuesInternal()
 * @see AbstractActiveRecord::populateProperty()
 */
trait PrivatePropertiesTrait
{
    protected function propertyValuesInternal(): array
    {
        return get_object_vars($this);
    }

    protected function populateProperty(string $name, mixed $value): void
    {
        $this->$name = $value;
    }
}
