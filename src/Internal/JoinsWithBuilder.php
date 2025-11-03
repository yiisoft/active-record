<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Internal;

use LogicException;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\JoinWith;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Definitions\Exception\CircularReferenceException;
use Yiisoft\Definitions\Exception\NotInstantiableException;

use function array_key_exists;
use function is_array;
use function is_int;

/**
 * Builds {@see ActiveQuery} joins added with `*join*` methods.
 *
 * @internal
 */
final class JoinsWithBuilder
{
    /**
     * @throws CircularReferenceException
     * @throws InvalidConfigException
     * @throws NotInstantiableException
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     */
    public static function build(ActiveQueryInterface $query): void
    {
        $joins = $query->getJoins();
        $query->setJoins([]);

        $model = $query->getModel();

        foreach ($query->getJoinsWith() as $joinWith) {
            self::joinWithRelations($query, $model, $joinWith);
            $query->with($joinWith->getWith());
        }

        /**
         * Remove duplicated joins added by {@see joinWithRelations()} that may be added, for example, when joining a relation
         * and a via relation at the same time.
         */
        $uniqueJoins = [];
        foreach ($query->getJoins() as $join) {
            $uniqueJoins[serialize($join)] = $join;
        }
        $query->setJoins(array_values($uniqueJoins));

        /**
         * @link https://github.com/yiisoft/yii2/issues/16092
         */
        $uniqueJoinsByTableName = [];

        foreach ($query->getJoins() as $join) {
            $tableName = serialize($join[1]);
            if (!array_key_exists($tableName, $uniqueJoinsByTableName)) {
                $uniqueJoinsByTableName[$tableName] = $join;
            }
        }

        $query->setJoins(array_values($uniqueJoinsByTableName));

        if (!empty($joins)) {
            /**
             * Append explicit join to {@see ActiveQuery::joinWith()} {@link https://github.com/yiisoft/yii2/issues/2880}
             */
            $queryJoins = $query->getJoins();
            $query->setJoins(
                empty($queryJoins) ? $joins : array_merge($queryJoins, $joins)
            );
        }
    }

    /**
     * Modifies the current query by adding join fragments based on the given relations.
     *
     * @param ActiveRecordInterface $model The primary model.
     * @param JoinWith $joinWith
     *
     * @throws CircularReferenceException
     * @throws InvalidConfigException
     * @throws NotInstantiableException
     * @throws \Yiisoft\Definitions\Exception\InvalidConfigException
     */
    private static function joinWithRelations(
        ActiveQueryInterface $query,
        ActiveRecordInterface $model,
        JoinWith $joinWith,
    ): void {
        $relations = [];

        foreach ($joinWith->relations as $name => $callback) {
            if (is_int($name)) {
                $name = $callback;
                $callback = null;
            }
            /** @var string $name */

            $primaryModel = $model;
            $parent = $query;
            $prefix = '';

            while (($pos = strpos($name, '.')) !== false) {
                $childName = substr($name, $pos + 1);
                $name = substr($name, 0, $pos);
                $fullName = $prefix === '' ? $name : "$prefix.$name";

                if (!isset($relations[$fullName])) {
                    $relations[$fullName] = $relation = $primaryModel->relationQuery($name);
                    self::joinWithRelation($query, $parent, $relation, $joinWith->getJoinType($fullName));
                } else {
                    $relation = $relations[$fullName];
                }

                if ($relation instanceof ActiveQueryInterface) {
                    $primaryModel = $relation->getModel();
                    $parent = $relation;
                }

                $prefix = $fullName;
                $name = $childName;
            }

            $fullName = $prefix === '' ? $name : "$prefix.$name";

            if (!isset($relations[$fullName])) {
                $relations[$fullName] = $relation = $primaryModel->relationQuery($name);

                if ($callback !== null) {
                    $callback($relation);
                }

                if ($relation instanceof ActiveQueryInterface && !empty($relation->getJoinsWith())) {
                    self::build($relation);
                }

                if ($relation instanceof ActiveQueryInterface) {
                    self::joinWithRelation($query, $parent, $relation, $joinWith->getJoinType($fullName));
                }
            }
        }
    }

    /**
     * Joins a parent query with a child query.
     *
     * The current query object will be modified so.
     *
     * @param ActiveQueryInterface $parent The parent query.
     * @param ActiveQueryInterface $child The child query.
     * @param string $joinType The join type.
     *
     * @throws CircularReferenceException
     * @throws NotInstantiableException
     * @throws InvalidConfigException
     */
    private static function joinWithRelation(
        ActiveQueryInterface $query,
        ActiveQueryInterface $parent,
        ActiveQueryInterface $child,
        string $joinType
    ): void {
        if (!empty($child->getHaving())
            || !empty($child->getGroupBy())
            || !empty($child->getUnions())
        ) {
            throw new LogicException('Joining with a relation that has GROUP BY, HAVING, or UNION is not supported.');
        }

        $via = $child->getVia();
        $child->resetVia();

        if ($via instanceof ActiveQueryInterface) {
            // via table
            self::joinWithRelation($query, $parent, $via, $joinType);
            self::joinWithRelation($query, $via, $child, $joinType);
            return;
        }

        if (is_array($via)) {
            // via relation
            self::joinWithRelation($query, $parent, $via[1], $joinType);
            self::joinWithRelation($query, $via[1], $child, $joinType);
            return;
        }

        [, $parentAlias] = TableNameAndAliasResolver::resolve($parent);
        [$childTable, $childAlias] = TableNameAndAliasResolver::resolve($child);

        if (!empty($child->getLink())) {
            if (!str_contains($parentAlias, '{{')) {
                $parentAlias = '{{' . $parentAlias . '}}';
            }

            if (!str_contains($childAlias, '{{')) {
                $childAlias = '{{' . $childAlias . '}}';
            }

            $on = [];

            foreach ($child->getLink() as $childColumn => $parentColumn) {
                $on[] = "$parentAlias.[[$parentColumn]] = $childAlias.[[$childColumn]]";
            }

            $on = implode(' AND ', $on);

            if (!empty($child->getOn())) {
                $on = ['and', $on, $child->getOn()];
            }
        } else {
            $on = $child->getOn();
        }

        $query->join(
            $joinType,
            empty($child->getFrom()) ? $childTable : $child->getFrom(),
            $on ?? '',
        );

        $where = $child->getWhere();
        if (!empty($where)) {
            $query->andWhere($where);
        }

        if (!empty($child->getOrderBy())) {
            $query->addOrderBy($child->getOrderBy());
        }

        if (!empty($child->getParams())) {
            $query->addParams($child->getParams());
        }

        $childJoins = $child->getJoins();
        if (!empty($childJoins)) {
            $query->setJoins(array_merge($query->getJoins(), $childJoins));
        }
    }
}
