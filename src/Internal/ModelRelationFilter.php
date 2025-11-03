<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Internal;

use LogicException;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\Db\Constant\ColumnType;
use Yiisoft\Db\Expression\ExpressionInterface;
use Yiisoft\Db\Expression\Value\ArrayValue;
use Yiisoft\Db\QueryBuilder\Condition\ArrayOverlaps;
use Yiisoft\Db\QueryBuilder\Condition\In;
use Yiisoft\Db\QueryBuilder\Condition\JsonOverlaps;

use function count;
use function is_array;
use function is_string;

/**
 * @internal
 */
final class ModelRelationFilter
{
    /**
     * @param ActiveRecordInterface[]|array[] $models
     */
    public static function apply(ActiveQueryInterface $query, array $models): void
    {
        $link = $query->getLink();
        $columnNames = self::qualifyColumnNames($query, array_keys($link));

        $model = reset($models);
        $values = [];

        if (count($columnNames) === 1) {
            /** @var string $linkedProperty Single key */
            $linkedProperty = reset($link);

            if ($model instanceof ActiveRecordInterface) {
                /** @var ActiveRecordInterface $model */
                foreach ($models as $model) {
                    $value = $model->get($linkedProperty);
                    if ($value !== null) {
                        if (is_array($value)) {
                            $values = [...$values, ...$value];
                        } else {
                            $values[] = $value;
                        }
                    }
                }
            } else {
                /** @var array $model */
                foreach ($models as $model) {
                    if (isset($model[$linkedProperty])) {
                        $value = $model[$linkedProperty];
                        if (is_array($value)) {
                            $values = [...$values, ...$value];
                        } else {
                            $values[] = $value;
                        }
                    }
                }
            }

            if (empty($values)) {
                $query->emulateExecution();
                $query->andWhere('1=0');
                return;
            }

            $scalarValues = array_filter($values, is_scalar(...));
            $nonScalarValues = array_diff_key($values, $scalarValues);

            $scalarValues = array_unique($scalarValues);
            $values = [...$scalarValues, ...$nonScalarValues];

            $columnName = reset($columnNames);
            /** @var string $propertyName */
            $propertyName = array_key_first($link);
            $column = $query->getModel()->column($propertyName);

            match ($column->getType()) {
                ColumnType::ARRAY => $query->andWhere(new ArrayOverlaps($columnName, new ArrayValue($values, $column))),
                ColumnType::JSON => $query->andWhere(new JsonOverlaps($columnName, $values)),
                default => $query->andWhere(new In($columnName, $values)),
            };

            return;
        }

        $nulls = array_fill_keys($link, null);

        if ($model instanceof ActiveRecordInterface) {
            /** @var ActiveRecordInterface $model */
            foreach ($models as $model) {
                $value = $model->propertyValues($query->getLink());
                if (!empty($value)) {
                    $values[] = array_combine($columnNames, array_merge($nulls, $value));
                }
            }
        } else {
            /** @var array $model */
            foreach ($models as $model) {
                $value = array_intersect_key($model, $nulls);
                if (!empty($value)) {
                    $values[] = array_combine($columnNames, array_merge($nulls, $value));
                }
            }
        }

        if (empty($values)) {
            $query->emulateExecution();
            $query->andWhere('1=0');
            return;
        }

        $query->andWhere(new In($columnNames, $values));
    }

    /**
     * @param string[] $columnNames
     * @return string[]
     */
    private static function qualifyColumnNames(ActiveQueryInterface $query, array $columnNames): array
    {
        if (empty($query->getJoins()) && empty($query->getJoinsWith())) {
            return $columnNames;
        }

        $from = $query->getFrom();
        if (empty($from)) {
            $alias = $query->getModel()->tableName();
        } else {
            $alias = array_key_first($from);
            if (!is_string($alias)) {
                $alias = reset($from);
                if ($alias instanceof ExpressionInterface) {
                    throw new LogicException('Alias must be set for a table specified by an expression.');
                }
            }
        }

        return array_map(
            static fn($name) => "$alias.$name",
            $columnNames
        );
    }
}
