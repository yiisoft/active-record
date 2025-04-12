<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use Closure;
use Yiisoft\ActiveRecord\AbstractActiveRecord;
use Yiisoft\ActiveRecord\ActiveRecordInterface;

use function array_combine;
use function array_keys;

/**
 * Trait to implement {@see \Yiisoft\Arrays\ArrayableInterface} interface for ActiveRecord.
 *
 * @method string[] propertyNames()
 * @see ActiveRecordInterface::propertyNames()
 *
 * @method array getRelatedRecords()
 * @see AbstractActiveRecord::getRelatedRecords()
 */
trait ArrayableTrait
{
    use \Yiisoft\Arrays\ArrayableTrait;

    /**
     * @return array The default implementation returns the names of the relations that have been populated into this
     * record.
     */
    public function extraFields(): array
    {
        $fields = array_keys($this->getRelatedRecords());

        return array_combine($fields, $fields);
    }

    /**
     * @psalm-return array<string, string|Closure>
     */
    public function fields(): array
    {
        $fields = $this->propertyNames();

        return array_combine($fields, $fields);
    }
}
