<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use ReflectionException;
use Throwable;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\NotSupportedException;
use Yiisoft\Definitions\Exception\InvalidConfigException;

use function is_array;
use function is_int;
use function reset;
use function strpos;
use function substr;

trait ActiveQueryTrait
{
    private bool|null $asArray = null;

    /**
     * Sets the {@see asArray} property.
     *
     * @param bool $value whether to return the query results in terms of arrays instead of Active Records.
     *
     * @return static the query object itself.
     */
    public function asArray(bool|null $value = true): static
    {
        $this->asArray = $value;
        return $this;
    }

    /**
     * Specifies the relations with which this query should be performed.
     *
     * The parameters to this method can be either one or multiple strings, or a single array of relation names and the
     * optional callbacks to customize the relations.
     *
     * A relation name can refer to a relation defined in {@see modelClass} or a sub-relation that stands for a relation
     * of a related record.
     *
     * For example, `orders.address` means the `address` relation defined in the model class corresponding to the
     * `orders` relation.
     *
     * The following are some usage examples:
     *
     * ```php
     * // Create active query
     * CustomerQuery = new ActiveQuery(Customer::class, $db);
     * // find customers together with their orders and country
     * CustomerQuery->with('orders', 'country')->all();
     * // find customers together with their orders and the orders' shipping address
     * CustomerQuery->with('orders.address')->all();
     * // find customers together with their country and orders of status 1
     * CustomerQuery->with([
     *     'orders' => function (ActiveQuery $query) {
     *         $query->andWhere('status = 1');
     *     },
     *     'country',
     * ])->all();
     * ```
     *
     * You can call `with()` multiple times. Each call will add relations to the existing ones.
     *
     * For example, the following two statements are equivalent:
     *
     * ```php
     * CustomerQuery->with('orders', 'country')->all();
     * CustomerQuery->with('orders')->with('country')->all();
     * ```
     *
     * @return static the query object itself.
     */
    public function with(array|string ...$with): static
    {
        if (isset($with[0]) && is_array($with[0])) {
            /** the parameter is given as an array */
            $with = $with[0];
        }

        if (empty($this->with)) {
            $this->with = $with;
        } elseif (!empty($with)) {
            foreach ($with as $name => $value) {
                if (is_int($name)) {
                    /** repeating relation is fine as normalizeRelations() handle it well */
                    $this->with[] = $value;
                } else {
                    $this->with[$name] = $value;
                }
            }
        }

        return $this;
    }

    /**
     * Converts found rows into model instances.
     *
     * @throws InvalidConfigException
     */
    protected function createModels(array $rows): array|null
    {
        if ($this->asArray) {
            return $rows;
        }

        $arClassInstance = [];

        foreach ($rows as $row) {
            $arClass = $this->getARInstance();

            if (method_exists($arClass, 'instantiate')) {
                $arClass = $arClass->instantiate($row);
            }

            $arClass->populateRecord($row);

            $arClassInstance[] = $arClass;
        }

        return $arClassInstance;
    }

    /**
     * Finds records corresponding to one or multiple relations and populates them into the primary models.
     *
     * @param array $with a list of relations that this query should be performed with. Please refer to {@see with()}
     * for details about specifying this parameter.
     * @param ActiveRecord[]|array $models the primary models (can be either AR instances or arrays)
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws NotSupportedException
     * @throws ReflectionException
     * @throws Throwable
     */
    public function findWith(array $with, array &$models): void
    {
        $primaryModel = reset($models);

        if (!$primaryModel instanceof ActiveRecordInterface) {
            $primaryModel = $this->getARInstance();
        }

        $relations = $this->normalizeRelations($primaryModel, $with);

        foreach ($relations as $name => $relation) {
            if ($relation->asArray === null) {
                /** inherit asArray from primary query */
                $relation->asArray($this->asArray);
            }

            $relation->populateRelation($name, $models);
        }
    }

    private function normalizeRelations(ActiveRecordInterface $model, array $with): array
    {
        $relations = [];

        foreach ($with as $name => $callback) {
            if (is_int($name)) {
                $name = $callback;
                $callback = null;
            }

            if (($pos = strpos($name, '.')) !== false) {
                /** with sub-relations */
                $childName = substr($name, $pos + 1);
                $name = substr($name, 0, $pos);
            } else {
                $childName = null;
            }

            if (!isset($relations[$name])) {
                /** @var ActiveQuery $relation */
                $relation = $model->getRelation($name);
                $relation->primaryModel = null;
                $relations[$name] = $relation;
            } else {
                $relation = $relations[$name];
            }

            if (isset($childName)) {
                $relation->with[$childName] = $callback;
            } elseif ($callback !== null) {
                $callback($relation);
            }
        }

        return $relations;
    }

    public function isAsArray(): bool|null
    {
        return $this->asArray;
    }

    public function getWith(): array
    {
        return $this->with;
    }
}
