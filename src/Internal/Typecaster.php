<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Internal;

use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\Db\Schema\TableSchemaInterface;

/**
 * @internal
 */
final  class Typecaster
{
    /**
     * @psalm-param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public static function cast(array $values, ActiveRecordInterface $model): array
    {
        $columns = $model->tableSchema()->getColumns();
        $columnValues = array_intersect_key($values, $columns);

        foreach ($columnValues as $name => $value) {
            $values[$name] = $columns[$name]->phpTypecast($value);
        }

        return $values;
    }
}
