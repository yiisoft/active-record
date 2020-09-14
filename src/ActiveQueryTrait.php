<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use ReflectionException;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\NotSupportedException;

use function get_class;
use function is_array;
use function is_int;
use function reset;
use function strpos;
use function substr;

trait ActiveQueryTrait
{
    private array $with = [];
    private ?bool $asArray = null;

    /**
     * Sets the {@see asArray} property.
     *
     * @param bool $value whether to return the query results in terms of arrays instead of Active Records.
     *
     * @return $this the query object itself.
     */
    public function asArray(?bool $value = true): self
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
     * // find customers together with their orders and country
     * Customer::find()->with('orders', 'country')->all();
     * // find customers together with their orders and the orders' shipping address
     * Customer::find()->with('orders.address')->all();
     * // find customers together with their country and orders of status 1
     * Customer::find()->with([
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
     * Customer::find()->with('orders', 'country')->all();
     * Customer::find()->with('orders')->with('country')->all();
     * ```
     * @param array|string $with
     *
     * @return $this the query object itself
     */
    public function with(...$with): self
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
     * @param array $rows
     *
     * @return ActiveRecord[]|array|null
     */
    protected function createModels(array $rows): ?array
    {
        if ($this->asArray) {
            return $rows;
        } else {
            $models = [];

            /* @var $class ActiveRecord */
            $class = $this->modelClass;

            foreach ($rows as $row) {
                $model = $class::instantiate($row);

                $modelClass = get_class($model);

                $modelClass::populateRecord($model, $row);

                $models[] = $model;
            }

            return $models;
        }
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
     * @throws InvalidConfigException
     * @throws NotSupportedException
     * @throws ReflectionException
     */
    public function findWith(array $with, array &$models): void
    {
        $primaryModel = reset($models);

        if (!$primaryModel instanceof ActiveRecordInterface) {
            $primaryModel = $this->modelClass::instance();
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

    /**
     * @param ActiveRecord $model
     * @param array $with
     *
     * @throws ReflectionException
     * @throws InvalidArgumentException
     *
     * @return ActiveQuery[]|array
     */
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

    public function isAsArray():? bool
    {
        return $this->asArray;
    }

    public function getWith(): array
    {
        return $this->with;
    }
}
