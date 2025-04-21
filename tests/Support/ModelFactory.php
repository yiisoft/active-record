<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Support;

use Yiisoft\ActiveRecord\ActiveRecordModel;

class ModelFactory
{
    public static function create(array $rows): array
    {
        $models = [];

        foreach ($rows as $row) {
            $class = $row['type'];

            /** @var ActiveRecordModel $model */
            $model = new $class();
            $model->activeRecord()->populateRecord($row);
            $model->initialize();

            $models[] = $model;
        }

        return $models;
    }
}
