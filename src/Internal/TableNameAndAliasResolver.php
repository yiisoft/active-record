<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Internal;

use LogicException;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\Db\Expression\ExpressionInterface;

use function is_string;

/**
 * @internal
 */
final class TableNameAndAliasResolver
{
    /**
     * Returns the table name and the table alias.
     *
     * @psalm-return list{ExpressionInterface|string, string}
     */
    public static function resolve(ActiveQueryInterface $query): array
    {
        $from = $query->getFrom();
        if (empty($from)) {
            $tableName = $query->getModel()->tableName();
        } else {
            $alias = array_key_first($from);
            $tableName = $from[$alias];
            if (is_string($alias)) {
                return [$tableName, $alias];
            }
            if ($tableName instanceof ExpressionInterface) {
                throw new LogicException('Alias must be set for a table specified by an expression.');
            }
        }

        $alias = preg_match('/^(.*?)\s+({{\w+}}|\w+)$/', $tableName, $matches)
            ? $matches[2]
            : $tableName;

        return [$tableName, $alias];
    }
}
