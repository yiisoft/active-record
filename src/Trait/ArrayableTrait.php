<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use Yiisoft\ActiveRecord\BaseActiveRecord;

use function array_combine;
use function array_keys;

/**
 * Trait to implement {@see \Yiisoft\Arrays\ArrayableTrait} interface for ActiveRecord.
 *
 * @method array attributes()
 * @see BaseActiveRecord::attributes() for more info.
 *
 * @method array getRelatedRecords()
 * @see BaseActiveRecord::getRelatedRecords() for more info.
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
        $fields = $this->attributes();

        return array_combine($fields, $fields);
    }
}
